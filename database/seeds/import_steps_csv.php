<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Support\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Questo script deve essere eseguito da terminale.\n");
    exit(1);
}

$options = getopt('', ['file:', 'user-id:', 'dry-run']);
$file = isset($options['file']) ? (string) $options['file'] : '';
$userId = isset($options['user-id']) ? (int) $options['user-id'] : 0;
$dryRun = array_key_exists('dry-run', $options);

if ($file === '' || $userId <= 0) {
    fwrite(STDERR, "Uso: php database/seeds/import_steps_csv.php --user-id=1 --file=/percorso/passi.csv [--dry-run]\n");
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

$normalizedHeader = array_map(static fn ($value) => normalize_header((string) $value), $header);
$dateIndex = find_column($normalizedHeader, ['data', 'date', 'giorno']);
$stepsIndex = find_column($normalizedHeader, ['passi', 'steps']);

if ($dateIndex === null || $stepsIndex === null) {
    fwrite(STDERR, "Il CSV deve avere due colonne con header data,passi oppure date,steps.\n");
    exit(1);
}

$stmt = null;
if (!$dryRun) {
    $pdo = Database::pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO daily_steps(user_id, step_date, steps, source, source_file_name, source_modified_at, synced_at)
         VALUES (?, ?, ?, "manual_csv", ?, FROM_UNIXTIME(?), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
           steps = VALUES(steps),
           source = VALUES(source),
           source_file_name = VALUES(source_file_name),
           source_modified_at = VALUES(source_modified_at),
           synced_at = UTC_TIMESTAMP()'
    );
}

$rows = 0;
$imported = 0;
$errors = [];
$sourceName = basename($file);
$sourceModifiedAt = filemtime($file) ?: time();

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $rows++;
    $dateRaw = trim((string) ($row[$dateIndex] ?? ''));
    $stepsRaw = trim((string) ($row[$stepsIndex] ?? ''));

    if ($dateRaw === '' && $stepsRaw === '') {
        continue;
    }

    $date = parse_date($dateRaw);
    $steps = parse_steps($stepsRaw);

    if ($date === null || $steps === null) {
        $errors[] = "Riga " . ($rows + 1) . ": data o passi non validi ({$dateRaw}, {$stepsRaw})";
        continue;
    }

    if (!$dryRun && $stmt !== null) {
        $stmt->execute([$userId, $date, $steps, $sourceName, $sourceModifiedAt]);
    }

    $imported++;
}

fclose($handle);

echo ($dryRun ? 'DRY RUN: ' : '') . "Righe lette: {$rows}\n";
echo ($dryRun ? 'Righe valide: ' : 'Righe importate/aggiornate: ') . "{$imported}\n";

if ($errors !== []) {
    echo "Righe ignorate: " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 20) as $error) {
        echo "- {$error}\n";
    }
    if (count($errors) > 20) {
        echo "- ... altre " . (count($errors) - 20) . " righe non mostrate\n";
    }
}

function normalize_header(string $value): string
{
    $value = trim(strtolower($value));
    $value = str_replace(["\xEF\xBB\xBF", ' '], ['', '_'], $value);
    return preg_replace('/[^a-z0-9_]/', '', $value) ?: '';
}

/** @param list<string> $candidates */
function find_column(array $header, array $candidates): ?int
{
    foreach ($candidates as $candidate) {
        $index = array_search($candidate, $header, true);
        if ($index !== false) {
            return (int) $index;
        }
    }

    return null;
}

function parse_date(string $value): ?string
{
    $value = trim($value);
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y.m.d', 'd.m.Y'];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

function parse_steps(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    $value = preg_replace('/[.,](?=\d{3}(\D|$))/', '', $value) ?? $value;
    $value = preg_replace('/[^\d]/', '', $value) ?? $value;

    if ($value === '') {
        return null;
    }

    return max(0, (int) $value);
}
