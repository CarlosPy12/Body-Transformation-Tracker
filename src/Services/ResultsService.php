<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MeasurementRepository;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class ResultsService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function metric(int $userId, string $metric, string $range, ?string $startDate = null, ?string $endDate = null): array
    {
        $from = $startDate ? $this->dateStart($startDate) : $this->rangeStart($range);
        $to = $endDate ? $this->dateEnd($endDate) : null;
        if ($metric === 'passi') {
            return $this->stepsMetric($userId, $from, $to);
        }

        $series = $this->bodySeries($userId, $metric, $from, $to);
        $values = array_map('floatval', array_column($series, 'value'));
        $target = $this->goalValue($userId, $metric);
        return [
            'series' => $series,
            'kpi' => $this->bodyKpi($series, $values, $target, $metric),
            'glp1_overlay' => $this->glp1Overlay($userId, $from, $to),
        ];
    }

    private function rangeStart(string $range): string
    {
        $now = new DateTimeImmutable('now');
        return match ($range) {
            '1m' => $now->sub(new DateInterval('P1M'))->format('Y-m-d 00:00:00'),
            '3m' => $now->sub(new DateInterval('P3M'))->format('Y-m-d 00:00:00'),
            '6m' => $now->sub(new DateInterval('P6M'))->format('Y-m-d 00:00:00'),
            '1y' => $now->sub(new DateInterval('P1Y'))->format('Y-m-d 00:00:00'),
            default => '1970-01-01 00:00:00',
        };
    }

    private function dateStart(string $date): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' 00:00:00' : $this->rangeStart('all');
    }

    private function dateEnd(string $date): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' 23:59:59' : null;
    }

    /** @return array<int,array<string,mixed>> */
    private function bodySeries(int $userId, string $metric, string $from, ?string $to): array
    {
        $columns = [
            'peso' => 'weight_kg',
            'bmi' => 'bmi',
            'massa_grassa' => 'body_fat',
            'acqua' => 'body_water',
            'muscoli' => 'muscle',
            'eta_metabolica' => 'metabolic_age',
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
        $column = $columns[$metric] ?? 'weight_kg';
        $sql = "SELECT measured_at AS date, {$column} AS value FROM body_measurements WHERE user_id = ? AND measured_at >= ? AND {$column} IS NOT NULL";
        $values = [$userId, $from];
        if ($to !== null) {
            $sql .= ' AND measured_at <= ?';
            $values[] = $to;
        }
        $sql .= ' ORDER BY measured_at';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll();
    }

    /** @param array<int,float> $values */
    private function bodyKpi(array $series, array $values, ?float $target, string $metric): array
    {
        if (!$values) {
            return [];
        }
        $first = $values[0];
        $last = $values[array_key_last($values)];
        $firstDate = new DateTimeImmutable((string) $series[0]['date']);
        $lastDate = new DateTimeImmutable((string) $series[array_key_last($series)]['date']);
        $days = max(1, (int) $firstDate->diff($lastDate)->format('%a'));
        $weeklyChange = ($last - $first) / max($days / 7, 1);
        $weeklyLoss = $metric === 'peso' ? -$weeklyChange : $weeklyChange;
        return [
            'valore_corrente' => $last,
            'valore_iniziale' => $first,
            'variazione_totale' => $last - $first,
            'variazione_percentuale' => $first != 0.0 ? (($last - $first) / $first) * 100 : null,
            'media_settimanale' => $weeklyLoss,
            'delta_target' => $target !== null ? $last - $target : null,
            'minimo' => min($values),
            'massimo' => max($values),
        ];
    }

    private function stepsMetric(int $userId, string $from, ?string $to): array
    {
        $sql = 'SELECT step_date AS date, steps AS value FROM daily_steps WHERE user_id = ? AND step_date >= DATE(?)';
        $values = [$userId, $from];
        if ($to !== null) {
            $sql .= ' AND step_date <= DATE(?)';
            $values[] = $to;
        }
        $sql .= ' ORDER BY step_date';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        $series = $stmt->fetchAll();
        $values = array_map('intval', array_column($series, 'value'));
        return [
            'series' => $series,
            'kpi' => [
                'media_giornaliera' => $values ? array_sum($values) / count($values) : 0,
                'totale_periodo' => array_sum($values),
                'giorni_sopra_obiettivo' => count(array_filter($values, static fn ($v) => $v >= 10000)),
                'percentuale_giorni_target' => $values ? count(array_filter($values, static fn ($v) => $v >= 10000)) / count($values) * 100 : 0,
            ],
            'glp1_overlay' => [],
        ];
    }

    private function glp1Overlay(int $userId, string $from, ?string $to): array
    {
        $previous = $this->pdo->prepare('SELECT scheduled_at, administered_at, planned_dose_mg, administered_dose_mg, status FROM glp1_injections WHERE user_id = ? AND scheduled_at < ? ORDER BY scheduled_at DESC LIMIT 1');
        $previous->execute([$userId, $from]);
        $events = $previous->fetchAll();

        $sql = 'SELECT scheduled_at, administered_at, planned_dose_mg, administered_dose_mg, status FROM glp1_injections WHERE user_id = ? AND scheduled_at >= ?';
        $values = [$userId, $from];
        if ($to !== null) {
            $sql .= ' AND scheduled_at <= ?';
            $values[] = $to;
        }
        $sql .= ' ORDER BY scheduled_at';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return array_merge($events, $stmt->fetchAll());
    }

    private function goalValue(int $userId, string $metric): ?float
    {
        $stmt = $this->pdo->prepare('SELECT target_value FROM goals WHERE user_id = ? AND metric_key = ? AND is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute([$userId, $metric]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (float) $value;
    }
}
