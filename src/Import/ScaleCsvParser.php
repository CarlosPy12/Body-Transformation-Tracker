<?php

declare(strict_types=1);

namespace App\Import;

use DateTimeImmutable;
use RuntimeException;

final class ScaleCsvParser
{
    private const HEADER_FIRST_CELL = 'Data';

    /** @return array<int,array<string,mixed>> */
    public function parse(string $content): array
    {
        $lines = preg_split('/\R/u', trim($content));
        if (!$lines) {
            return [];
        }

        $headerIndex = null;
        foreach ($lines as $index => $line) {
            if (str_starts_with(trim($line), self::HEADER_FIRST_CELL . ';Ora;kg;IMC;')) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('Header del CSV bilancia non trovato.');
        }

        $rows = [];
        foreach (array_slice($lines, $headerIndex + 1) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cells = str_getcsv($line, ';');
            if (count($cells) < 2) {
                continue;
            }
            $rows[] = $this->mapRow($cells);
        }

        return $rows;
    }

    /** @param array<int,string|null> $cells */
    private function mapRow(array $cells): array
    {
        $date = DateTimeImmutable::createFromFormat('!d/m/Y H:i', trim((string) ($cells[0] ?? '')) . ' ' . trim((string) ($cells[1] ?? '')));
        if (!$date) {
            throw new RuntimeException('Data o ora non valida nel CSV bilancia.');
        }

        return [
            'measured_at' => $date->format('Y-m-d H:i:s'),
            'weight_kg' => $this->decimal($cells[2] ?? null),
            'bmi' => $this->decimal($cells[3] ?? null),
            'body_fat' => $this->decimal($cells[4] ?? null),
            'body_water' => $this->decimal($cells[5] ?? null),
            'muscle' => $this->decimal($cells[6] ?? null),
            'bone' => $this->decimal($cells[7] ?? null),
            'left_arm_body_fat' => $this->decimal($cells[8] ?? null),
            'left_arm_muscle' => $this->decimal($cells[9] ?? null),
            'right_arm_body_fat' => $this->decimal($cells[10] ?? null),
            'right_arm_muscle' => $this->decimal($cells[11] ?? null),
            'left_leg_body_fat' => $this->decimal($cells[12] ?? null),
            'left_leg_muscle' => $this->decimal($cells[13] ?? null),
            'right_leg_body_fat' => $this->decimal($cells[14] ?? null),
            'right_leg_muscle' => $this->decimal($cells[15] ?? null),
            'trunk_body_fat' => $this->decimal($cells[16] ?? null),
            'trunk_muscle' => $this->decimal($cells[17] ?? null),
            'metabolic_age' => $this->decimal($cells[18] ?? null),
            'heart_rate_bpm' => $this->decimal($cells[19] ?? null),
            'visceral_fat' => $this->decimal($cells[20] ?? null),
        ];
    }

    private function decimal(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return (float) str_replace(',', '.', $value);
    }
}
