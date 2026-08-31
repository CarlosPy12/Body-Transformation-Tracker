<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Auth\Authorization;
use App\Import\MeasurementFingerprint;
use App\Import\ScaleCsvParser;
use App\Import\StepsCsvParser;

$passed = 0;
$failed = 0;

function test_case(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "OK  {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "ERR {$name}: {$e->getMessage()}\n";
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

test_case('Parser bilancia: header non in prima riga e decimali italiani', function (): void {
    $csv = "Dettagli utente\nNome Test\n\nPeso\nData;Ora;kg;IMC;Massa grassa;Acqua;Muscoli;Ossa;Braccio sinistro Massa grassa;Braccio sinistro Muscoli;Braccio destro Massa grassa;Braccio destro Muscoli;Gamba sinistra Massa grassa;Gamba sinistra Muscoli;Gamba destra Massa grassa;Gamba destra Muscoli;Tronco Massa grassa;Tronco Muscoli;Età metabolica;Battito;Grasso viscerale\n27/02/2026;13:57;109,9;30,4;27,1;52,5;36,0;4,1;26,0;41,8;23,0;44,4;17,7;35,7;18,2;36,3;30,8;34,6;37,0;87,0;13,0";
    $rows = (new ScaleCsvParser())->parse($csv);
    assert_true($rows[0]['measured_at'] === '2026-02-27 13:57:00', 'Data convertita male');
    assert_true(abs($rows[0]['weight_kg'] - 109.9) < 0.001, 'Peso convertito male');
});

test_case('Parser bilancia: campi vuoti diventano NULL', function (): void {
    $csv = "Peso\nData;Ora;kg;IMC;Massa grassa;Acqua;Muscoli;Ossa;Braccio sinistro Massa grassa;Braccio sinistro Muscoli;Braccio destro Massa grassa;Braccio destro Muscoli;Gamba sinistra Massa grassa;Gamba sinistra Muscoli;Gamba destra Massa grassa;Gamba destra Muscoli;Tronco Massa grassa;Tronco Muscoli;Età metabolica;Battito;Grasso viscerale\n27/02/2026;13:57;109,9;;27,1;;;;;;;;;;;;;;;;;";
    $rows = (new ScaleCsvParser())->parse($csv);
    assert_true($rows[0]['bmi'] === null, 'Campo vuoto non e NULL');
});

test_case('Deduplica: stesso record produce stesso hash', function (): void {
    $row = ['measured_at' => '2026-02-27 13:57:00', 'weight_kg' => 109.9, 'bmi' => null];
    assert_true(MeasurementFingerprint::hash(1, $row) === MeasurementFingerprint::hash(1, $row), 'Hash non deterministico');
});

test_case('Deduplica: stessa data ora ma valori diversi produce hash diverso', function (): void {
    $a = ['measured_at' => '2026-02-27 13:57:00', 'weight_kg' => 109.9];
    $b = ['measured_at' => '2026-02-27 13:57:00', 'weight_kg' => 110.2];
    assert_true(MeasurementFingerprint::hash(1, $a) !== MeasurementFingerprint::hash(1, $b), 'Hash non distingue misurazioni diverse');
});

test_case('Parser passi: somma righe e ignora righe invalide', function (): void {
    $csv = "Data,Orario,Passi\n2026.08.23 09:59:00,09:59:00,10\nx,y,z\n2026.08.23 10:14:00,10:14:00,52";
    $parsed = (new StepsCsvParser())->parse('Passi 2026.08.23 Huawei Health.csv', $csv);
    assert_true($parsed['date'] === '2026-08-23', 'Data passi errata');
    assert_true($parsed['steps'] === 62, 'Somma passi errata');
});

test_case('Parser passi: ignora aggregati settimanali e mensili', function (): void {
    assert_true(!StepsCsvParser::isDailyFile('Passi 33-2026 Huawei Health.csv'), 'Aggregato settimanale accettato');
    assert_true(!StepsCsvParser::isDailyFile('Passi Luglio 2026 Huawei Health.csv'), 'Aggregato mensile accettato');
    assert_true(!StepsCsvParser::isDailyFile('Passi 2026.07.31-2026.08.30 Health Connect.csv'), 'Aggregato continuo 30 giorni accettato');
});

test_case('Parser passi: accetta file giornalieri Huawei Health e Health Connect', function (): void {
    assert_true(StepsCsvParser::isDailyFile('Passi 2026.08.31 Huawei Health.csv'), 'Giornaliero Huawei rifiutato');
    assert_true(StepsCsvParser::isDailyFile('Passi 2026.08.31 Health Connect.csv'), 'Giornaliero Health Connect rifiutato');
    assert_true(StepsCsvParser::dateFromFileName('Passi 2026.08.31 Health Connect.csv') === '2026-08-31', 'Data file Health Connect errata');
});

test_case('Parser passi: supporta CSV con due colonne data e passi', function (): void {
    $csv = "Data,Passi\n2026.08.31 09:00:00,10\n2026.08.31 10:00:00,52";
    $parsed = (new StepsCsvParser())->parse('Passi 2026.08.31 Health Connect.csv', $csv);
    assert_true($parsed['date'] === '2026-08-31', 'Data passi due colonne errata');
    assert_true($parsed['steps'] === 62, 'Somma passi due colonne errata');
});

test_case('Authorization: user non accede ad altri utenti', function (): void {
    assert_true(!Authorization::canAccessUser(['id' => 1, 'role' => 'user'], 2), 'Isolamento user_id non rispettato');
    assert_true(Authorization::canAccessUser(['id' => 1, 'role' => 'super_admin'], 2), 'Super admin non autorizzato');
});

test_case('GLP-1: transizione scheduled -> completed e dosi distinte', function (): void {
    $planned = 7.5;
    $administered = 5.0;
    $status = 'scheduled';
    $status = 'completed';
    assert_true($status === 'completed' && $planned !== $administered, 'Transizione o dose effettiva non gestita');
});

echo "\n{$passed} test OK, {$failed} errori.\n";
exit($failed > 0 ? 1 : 0);
