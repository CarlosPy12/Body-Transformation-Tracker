<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Import\MeasurementFingerprint;
use App\Support\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Questo script deve essere eseguito da terminale.\n");
    exit(1);
}

$options = getopt('', ['file:', 'user-id:', 'medication::', 'dry-run']);
$file = isset($options['file']) ? (string) $options['file'] : '';
$userId = isset($options['user-id']) ? (int) $options['user-id'] : 0;
$medicationName = trim((string) ($options['medication'] ?? 'Mounjaro'));
$dryRun = array_key_exists('dry-run', $options);

if ($file === '' || $userId <= 0) {
    fwrite(STDERR, "Uso: php database/seeds/import_history_csv.php --user-id=1 --file=/percorso/storico.csv [--medication=Mounjaro] [--dry-run]\n");
    exit(1);
}

if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "File non leggibile: {$file}\n");
    exit(1);
}

$handle = fopen($file, 'rb');
if ($handle === false) {
    fwrite(STDERR, "Impossibile aprire il file: {$file}\n");
    exit(1);
}

$firstLine = fgets($handle);
if ($firstLine === false) {
    fwrite(STDERR, "CSV vuoto.\n");
    exit(1);
}

$delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
rewind($handle);

$header = fgetcsv($handle, 0, $delimiter);
if ($header === false) {
    fwrite(STDERR, "Header CSV non valido.\n");
    exit(1);
}

$columns = map_columns($header);
$required = ['data', 'ora'];
foreach ($required as $key) {
    if (!array_key_exists($key, $columns)) {
        fwrite(STDERR, "Colonna obbligatoria mancante: {$key}\n");
        exit(1);
    }
}

$pdo = null;
$measurementStmt = null;
$injectionFindStmt = null;
$injectionInsertStmt = null;
$medicationId = null;

if (!$dryRun) {
    $pdo = Database::pdo();
    $measurementStmt = $pdo->prepare(
        'INSERT IGNORE INTO body_measurements (
            user_id, measured_at, weight_kg, bmi, body_fat, body_water, muscle, bone,
            left_arm_body_fat, left_arm_muscle, right_arm_body_fat, right_arm_muscle,
            left_leg_body_fat, left_leg_muscle, right_leg_body_fat, right_leg_muscle,
            trunk_body_fat, trunk_muscle, metabolic_age, heart_rate_bpm, visceral_fat,
            source, measurement_hash, import_id
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            "historical_csv", ?, NULL
        )'
    );
    $medicationId = medication_id($pdo, $userId, $medicationName);
    $injectionFindStmt = $pdo->prepare(
        'SELECT id FROM glp1_injections
         WHERE user_id = ? AND medication_id = ? AND scheduled_at = ? AND planned_dose_mg = ?
         LIMIT 1'
    );
    $injectionInsertStmt = $pdo->prepare(
        'INSERT INTO glp1_injections(
            user_id, medication_id, scheduled_at, administered_at, planned_dose_mg,
            administered_dose_mg, status, notes
         ) VALUES (?, ?, ?, ?, ?, ?, "completed", "Import storico CSV")'
    );
}

$rows = 0;
$measurementsImported = 0;
$measurementsSkipped = 0;
$injectionsImported = 0;
$injectionsSkipped = 0;
$errors = [];

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $rows++;
    if (row_is_empty($row)) {
        continue;
    }

    try {
        $measuredAt = measured_at(value($row, $columns, 'data'), value($row, $columns, 'ora'));
        $measurement = [
            'measured_at' => $measuredAt,
            'weight_kg' => decimal(value($row, $columns, 'kg')),
            'bmi' => decimal(value($row, $columns, 'imc')),
            'body_fat' => decimal(value($row, $columns, 'massa_grassa')),
            'body_water' => decimal(value($row, $columns, 'acqua')),
            'muscle' => decimal(value($row, $columns, 'muscoli')),
            'bone' => decimal(value($row, $columns, 'ossa')),
            'left_arm_body_fat' => decimal(value($row, $columns, 'braccio_sinistro_massa_grassa')),
            'left_arm_muscle' => decimal(value($row, $columns, 'braccio_sinistro_muscoli')),
            'right_arm_body_fat' => decimal(value($row, $columns, 'braccio_destro_massa_grassa')),
            'right_arm_muscle' => decimal(value($row, $columns, 'braccio_destro_muscoli')),
            'left_leg_body_fat' => decimal(value($row, $columns, 'gamba_sinistra_massa_grassa')),
            'left_leg_muscle' => decimal(value($row, $columns, 'gamba_sinistra_muscoli')),
            'right_leg_body_fat' => decimal(value($row, $columns, 'gamba_destra_massa_grassa')),
            'right_leg_muscle' => decimal(value($row, $columns, 'gamba_destra_muscoli')),
            'trunk_body_fat' => decimal(value($row, $columns, 'tronco_massa_grassa')),
            'trunk_muscle' => decimal(value($row, $columns, 'tronco_muscoli')),
            'metabolic_age' => decimal(value($row, $columns, 'eta_metabolica')),
            'heart_rate_bpm' => decimal(value($row, $columns, 'battito')),
            'visceral_fat' => decimal(value($row, $columns, 'grasso_viscerale')),
        ];
        $measurement['measurement_hash'] = MeasurementFingerprint::hash($userId, $measurement);

        if ($dryRun) {
            $measurementsImported++;
        } elseif ($measurementStmt !== null) {
            $measurementStmt->execute(array_merge([$userId], array_values($measurement)));
            $measurementStmt->rowCount() === 1 ? $measurementsImported++ : $measurementsSkipped++;
        }

        $doseFlag = decimal(value($row, $columns, 'dose_mj'));
        $doseMg = decimal(value($row, $columns, 'mj_mg'));
        if (($doseFlag !== null && $doseFlag > 0) || ($doseMg !== null && $doseMg > 0)) {
            if ($doseMg === null || $doseMg <= 0) {
                throw new RuntimeException('Dose MJ presente ma MJ Mg mancante o non valido.');
            }

            if ($dryRun) {
                $injectionsImported++;
            } elseif ($injectionFindStmt !== null && $injectionInsertStmt !== null && $medicationId !== null) {
                $injectionFindStmt->execute([$userId, $medicationId, $measuredAt, $doseMg]);
                if ($injectionFindStmt->fetchColumn()) {
                    $injectionsSkipped++;
                } else {
                    $injectionInsertStmt->execute([$userId, $medicationId, $measuredAt, $measuredAt, $doseMg, $doseMg]);
                    $injectionsImported++;
                }
            }
        }
    } catch (Throwable $error) {
        $errors[] = 'Riga ' . ($rows + 1) . ': ' . $error->getMessage();
    }
}

fclose($handle);

echo ($dryRun ? 'DRY RUN: ' : '') . "Righe lette: {$rows}\n";
echo ($dryRun ? 'Misurazioni valide: ' : 'Misurazioni importate: ') . "{$measurementsImported}\n";
if (!$dryRun) {
    echo "Misurazioni gia presenti: {$measurementsSkipped}\n";
}
echo ($dryRun ? 'Iniezioni valide: ' : 'Iniezioni importate: ') . "{$injectionsImported}\n";
if (!$dryRun) {
    echo "Iniezioni gia presenti: {$injectionsSkipped}\n";
}

if ($errors !== []) {
    echo "Righe con errore: " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 30) as $error) {
        echo "- {$error}\n";
    }
    if (count($errors) > 30) {
        echo "- ... altre " . (count($errors) - 30) . " righe non mostrate\n";
    }
}

/** @param array<int,string|null> $header */
function map_columns(array $header): array
{
    $aliases = [
        'data' => ['data', 'date'],
        'ora' => ['ora', 'orario', 'time'],
        'kg' => ['kg', 'peso'],
        'imc' => ['imc', 'bmi'],
        'massa_grassa' => ['massa_grassa', 'massagrassa', 'body_fat'],
        'acqua' => ['acqua', 'body_water'],
        'muscoli' => ['muscoli', 'muscle'],
        'ossa' => ['ossa', 'bone'],
        'braccio_sinistro_massa_grassa' => ['braccio_sinistro_massa_grassa', 'bracciosinistromassagrassa'],
        'braccio_sinistro_muscoli' => ['braccio_sinistro_muscoli', 'bracciosinistromuscoli'],
        'braccio_destro_massa_grassa' => ['braccio_destro_massa_grassa', 'bracciodestromassagrassa'],
        'braccio_destro_muscoli' => ['braccio_destro_muscoli', 'bracciodestromuscoli'],
        'gamba_sinistra_massa_grassa' => ['gamba_sinistra_massa_grassa', 'gambasinistramassagrassa'],
        'gamba_sinistra_muscoli' => ['gamba_sinistra_muscoli', 'gambasinistramuscoli'],
        'gamba_destra_massa_grassa' => ['gamba_destra_massa_grassa', 'gambadestramassagrassa'],
        'gamba_destra_muscoli' => ['gamba_destra_muscoli', 'gambadestramuscoli'],
        'tronco_massa_grassa' => ['tronco_massa_grassa', 'troncomassagrassa'],
        'tronco_muscoli' => ['tronco_muscoli', 'troncomuscoli'],
        'eta_metabolica' => ['eta_metabolica', 'et_metabolica', 'etametabolica', 'metabolic_age'],
        'battito' => ['battito', 'heart_rate', 'bpm'],
        'grasso_viscerale' => ['grasso_viscerale', 'grassoviscerale', 'visceral_fat'],
        'dose_mj' => ['dose_mj', 'dosemj'],
        'mj_mg' => ['mj_mg', 'mjmg', 'mg', 'dose_mg'],
    ];

    $normalized = [];
    foreach ($header as $index => $name) {
        $normalized[normalize_header((string) $name)] = $index;
    }

    $mapped = [];
    foreach ($aliases as $key => $names) {
        foreach ($names as $name) {
            if (array_key_exists($name, $normalized)) {
                $mapped[$key] = $normalized[$name];
                break;
            }
        }
    }

    return $mapped;
}

function normalize_header(string $value): string
{
    $value = str_replace("\xEF\xBB\xBF", '', $value);
    $value = strtolower(trim($value));
    $value = strtr($value, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return trim($value, '_');
}

function value(array $row, array $columns, string $key): ?string
{
    if (!array_key_exists($key, $columns)) {
        return null;
    }

    return isset($row[$columns[$key]]) ? trim((string) $row[$columns[$key]]) : null;
}

function measured_at(?string $dateValue, ?string $timeValue): string
{
    $dateValue = trim((string) $dateValue);
    $timeValue = trim((string) $timeValue);
    if ($timeValue === '') {
        $timeValue = '00:00';
    }

    $dateFormats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'Y.m.d', 'd.m.Y'];
    $timeFormats = ['H:i:s', 'H:i'];

    foreach ($dateFormats as $dateFormat) {
        foreach ($timeFormats as $timeFormat) {
            $format = '!' . $dateFormat . ' ' . $timeFormat;
            $date = DateTimeImmutable::createFromFormat($format, "{$dateValue} {$timeValue}");
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }
    }

    $timestamp = strtotime("{$dateValue} {$timeValue}");
    if ($timestamp === false) {
        throw new RuntimeException("Data/ora non valida: {$dateValue} {$timeValue}");
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function decimal(?string $value): ?float
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = str_replace(["\xc2\xa0", ' ', '%'], '', $value);
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
    }
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float) $value : null;
}

function row_is_empty(array $row): bool
{
    foreach ($row as $cell) {
        if (trim((string) $cell) !== '') {
            return false;
        }
    }

    return true;
}

function medication_id(PDO $pdo, int $userId, string $name): int
{
    $find = $pdo->prepare('SELECT id FROM glp1_medications WHERE user_id = ? AND name = ? LIMIT 1');
    $find->execute([$userId, $name]);
    $id = $find->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $insert = $pdo->prepare('INSERT INTO glp1_medications(user_id, name, active_ingredient, is_active) VALUES (?, ?, "tirzepatide", 1)');
    $insert->execute([$userId, $name]);

    return (int) $pdo->lastInsertId();
}
