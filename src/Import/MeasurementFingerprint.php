<?php

declare(strict_types=1);

namespace App\Import;

final class MeasurementFingerprint
{
    private const FIELDS = [
        'measured_at', 'weight_kg', 'bmi', 'body_fat', 'body_water', 'muscle', 'bone',
        'left_arm_body_fat', 'left_arm_muscle', 'right_arm_body_fat', 'right_arm_muscle',
        'left_leg_body_fat', 'left_leg_muscle', 'right_leg_body_fat', 'right_leg_muscle',
        'trunk_body_fat', 'trunk_muscle', 'metabolic_age', 'heart_rate_bpm', 'visceral_fat',
    ];

    /** @param array<string,mixed> $row */
    public static function hash(int $userId, array $row): string
    {
        $parts = [(string) $userId];
        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;
            if ($field === 'measured_at') {
                $parts[] = (string) $value;
                continue;
            }
            $parts[] = $value === null ? 'NULL' : number_format((float) $value, 4, '.', '');
        }
        return hash('sha256', implode('|', $parts));
    }
}
