<?php

declare(strict_types=1);

namespace App\Services;

use App\Import\MeasurementFingerprint;
use App\Import\ScaleCsvParser;
use App\Repositories\MeasurementRepository;
use App\Support\Env;
use PDO;

final class ImportService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function preview(int $userId, string $fileName, string $content): array
    {
        $rows = (new ScaleCsvParser())->parse($content);
        $recent = (new MeasurementRepository($this->pdo))->recentWeights($userId);
        $avg = count($recent) ? array_sum($recent) / count($recent) : null;
        $threshold = Env::float('ANOMALY_RELATIVE_THRESHOLD', 0.12);

        $preview = [];
        foreach ($rows as $row) {
            $flagged = false;
            $warnings = [];
            if ($avg && $row['weight_kg'] !== null && abs(((float) $row['weight_kg'] - $avg) / $avg) > $threshold) {
                $flagged = true;
                $warnings[] = 'Rilevazione anomala rispetto allo storico recente';
            }
            $preview[] = [
                'row' => $row,
                'measurement_hash' => MeasurementFingerprint::hash($userId, $row),
                'flagged' => $flagged,
                'warnings' => $warnings,
            ];
        }

        return [
            'file_name' => $fileName,
            'file_hash' => hash('sha256', $content),
            'rows_found' => count($rows),
            'rows_flagged' => count(array_filter($preview, static fn ($item) => $item['flagged'])),
            'preview' => $preview,
        ];
    }

    /** @param array<int,string> $acceptedHashes */
    public function import(int $userId, string $fileName, string $content, array $acceptedHashes): array
    {
        $preview = $this->preview($userId, $fileName, $content);
        $stmt = $this->pdo->prepare('INSERT INTO measurement_imports(user_id, file_name, file_hash, rows_found, rows_flagged, status) VALUES (?, ?, ?, ?, ?, "analysed")');
        $stmt->execute([$userId, $fileName, $preview['file_hash'], $preview['rows_found'], $preview['rows_flagged']]);
        $importId = (int) $this->pdo->lastInsertId();

        $repo = new MeasurementRepository($this->pdo);
        $imported = 0;
        $duplicates = 0;
        $rejected = 0;
        foreach ($preview['preview'] as $item) {
            if ($item['flagged'] && !in_array($item['measurement_hash'], $acceptedHashes, true)) {
                $rejected++;
                continue;
            }
            $repo->insertIfNew($userId, $item['row'], $importId) ? $imported++ : $duplicates++;
        }

        $this->pdo->prepare('UPDATE measurement_imports SET rows_imported = ?, rows_duplicates = ?, rows_rejected = ?, status = "completed", completed_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([$imported, $duplicates, $rejected, $importId]);

        return [
            'import_id' => $importId,
            'rows_found' => $preview['rows_found'],
            'rows_imported' => $imported,
            'rows_duplicates' => $duplicates,
            'rows_flagged' => $preview['rows_flagged'],
            'rows_rejected' => $rejected,
        ];
    }
}
