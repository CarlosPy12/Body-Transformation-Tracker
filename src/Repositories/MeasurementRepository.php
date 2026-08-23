<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Import\MeasurementFingerprint;
use PDO;

final class MeasurementRepository
{
    private const METRIC_COLUMNS = [
        'peso' => 'weight_kg',
        'bmi' => 'bmi',
        'massa_grassa' => 'body_fat',
        'acqua' => 'body_water',
        'muscoli' => 'muscle',
        'ossa' => 'bone',
        'grasso_viscerale' => 'visceral_fat',
        'eta_metabolica' => 'metabolic_age',
        'battito' => 'heart_rate_bpm',
        'braccio_sx_massa_grassa' => 'left_arm_body_fat',
        'braccio_sx_muscoli' => 'left_arm_muscle',
        'braccio_dx_massa_grassa' => 'right_arm_body_fat',
        'braccio_dx_muscoli' => 'right_arm_muscle',
        'gamba_sx_massa_grassa' => 'left_leg_body_fat',
        'gamba_sx_muscoli' => 'left_leg_muscle',
        'gamba_dx_massa_grassa' => 'right_leg_body_fat',
        'gamba_dx_muscoli' => 'right_leg_muscle',
        'tronco_massa_grassa' => 'trunk_body_fat',
        'tronco_muscoli' => 'trunk_muscle',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $row */
    public function insertIfNew(int $userId, array $row, ?int $importId): bool
    {
        $row['measurement_hash'] = MeasurementFingerprint::hash($userId, $row);
        $fields = array_keys($row);
        $columns = implode(',', array_merge(['user_id'], $fields, ['source', 'import_id']));
        $marks = implode(',', array_fill(0, count($fields) + 3, '?'));
        $sql = "INSERT IGNORE INTO body_measurements ({$columns}) VALUES ({$marks})";
        $values = array_merge([$userId], array_values($row), ['scale_csv', $importId]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount() === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public function series(int $userId, string $metric, string $fromDate): array
    {
        $column = self::METRIC_COLUMNS[$metric] ?? 'weight_kg';
        $stmt = $this->pdo->prepare("SELECT measured_at AS date, {$column} AS value FROM body_measurements WHERE user_id = ? AND measured_at >= ? AND {$column} IS NOT NULL ORDER BY measured_at");
        $stmt->execute([$userId, $fromDate]);
        return $stmt->fetchAll();
    }

    public function latest(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM body_measurements WHERE user_id = ? ORDER BY measured_at DESC, id DESC LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,float> */
    public function recentWeights(int $userId, int $limit = 8): array
    {
        $stmt = $this->pdo->prepare('SELECT weight_kg FROM body_measurements WHERE user_id = ? AND weight_kg IS NOT NULL ORDER BY measured_at DESC LIMIT ' . $limit);
        $stmt->execute([$userId]);
        return array_map('floatval', array_column($stmt->fetchAll(), 'weight_kg'));
    }
}
