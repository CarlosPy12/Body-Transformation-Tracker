<?php

declare(strict_types=1);

namespace App\Import;

use RuntimeException;

final class StepsCsvParser
{
    public static function isDailyFile(string $fileName): bool
    {
        return preg_match('/^Passi \d{4}\.\d{2}\.\d{2} (Huawei Health|Health Connect)\.csv$/u', $fileName) === 1;
    }

    public static function dateFromFileName(string $fileName): ?string
    {
        if (!self::isDailyFile($fileName)) {
            return null;
        }
        preg_match('/(\d{4})\.(\d{2})\.(\d{2})/', $fileName, $match);
        return "{$match[1]}-{$match[2]}-{$match[3]}";
    }

    /** @return array{date:string,steps:int} */
    public function parse(string $fileName, string $content): array
    {
        if (!self::isDailyFile($fileName)) {
            throw new RuntimeException('Il file non rispetta il pattern giornaliero dei passi.');
        }

        $date = self::dateFromFileName($fileName);
        $lines = preg_split('/\R/u', trim($content));
        if (!$lines || count($lines) < 2) {
            return ['date' => $date, 'steps' => 0];
        }

        $header = array_map(static fn (string $cell): string => strtolower(trim($cell)), str_getcsv((string) $lines[0], ','));
        $stepsIndex = array_search('passi', $header, true);
        if ($stepsIndex === false) {
            $stepsIndex = array_search('steps', $header, true);
        }
        if ($stepsIndex === false) {
            $stepsIndex = count($header) >= 3 ? 2 : 1;
        }

        $total = 0;
        foreach (array_slice($lines, 1) as $line) {
            $cells = str_getcsv(trim($line), ',');
            if (!isset($cells[$stepsIndex]) || !is_numeric(trim($cells[$stepsIndex]))) {
                continue;
            }
            $total += max(0, (int) trim($cells[$stepsIndex]));
        }

        return ['date' => $date, 'steps' => $total];
    }
}
