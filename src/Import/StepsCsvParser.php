<?php

declare(strict_types=1);

namespace App\Import;

use RuntimeException;

final class StepsCsvParser
{
    public static function isDailyFile(string $fileName): bool
    {
        return preg_match('/^Passi \d{4}\.\d{2}\.\d{2} Huawei Health\.csv$/u', $fileName) === 1;
    }

    /** @return array{date:string,steps:int} */
    public function parse(string $fileName, string $content): array
    {
        if (!self::isDailyFile($fileName)) {
            throw new RuntimeException('Il file non rispetta il pattern giornaliero dei passi.');
        }

        preg_match('/(\d{4})\.(\d{2})\.(\d{2})/', $fileName, $match);
        $date = "{$match[1]}-{$match[2]}-{$match[3]}";
        $lines = preg_split('/\R/u', trim($content));
        if (!$lines || count($lines) < 2) {
            return ['date' => $date, 'steps' => 0];
        }

        $total = 0;
        foreach (array_slice($lines, 1) as $line) {
            $cells = str_getcsv(trim($line), ',');
            if (count($cells) < 3 || !is_numeric(trim($cells[2]))) {
                continue;
            }
            $total += max(0, (int) trim($cells[2]));
        }

        return ['date' => $date, 'steps' => $total];
    }
}
