<?php

declare(strict_types=1);

namespace App\Sync;

use App\Import\StepsCsvParser;
use App\Support\Logger;
use DateTimeImmutable;
use PDO;

final class StepsSyncService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function upsertDailySteps(int $userId, string $fileId, string $fileName, ?string $modifiedAt, string $content): array
    {
        $parser = new StepsCsvParser();
        $parsed = $parser->parse($fileName, $content);
        $checksum = hash('sha256', $content);

        $known = $this->pdo->prepare('SELECT checksum FROM sync_files WHERE provider = "health_sync_drive" AND data_type = "steps" AND user_id = ? AND external_file_id = ?');
        $known->execute([$userId, $fileId]);
        if ($known->fetchColumn() === $checksum) {
            return ['status' => 'skipped', 'date' => $parsed['date'], 'steps' => $parsed['steps']];
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO daily_steps(user_id, step_date, steps, source, source_file_id, source_file_name, source_modified_at, synced_at) VALUES (?, ?, ?, "health_sync_drive", ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE steps = VALUES(steps), source_file_id = VALUES(source_file_id), source_file_name = VALUES(source_file_name), source_modified_at = VALUES(source_modified_at), synced_at = UTC_TIMESTAMP()')
                ->execute([$userId, $parsed['date'], $parsed['steps'], $fileId, $fileName, $modifiedAt]);
            $this->pdo->prepare('INSERT INTO sync_files(provider, data_type, user_id, external_file_id, file_name, external_modified_at, checksum, status, processed_at) VALUES ("health_sync_drive", "steps", ?, ?, ?, ?, ?, "processed", UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), external_modified_at = VALUES(external_modified_at), checksum = VALUES(checksum), status = "processed", error_message = NULL, processed_at = UTC_TIMESTAMP()')
                ->execute([$userId, $fileId, $fileName, $modifiedAt, $checksum]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Logger::write('sync', 'Errore sync passi', ['file' => $fileName, 'error' => $e->getMessage()]);
            throw $e;
        }

        return ['status' => 'processed', 'date' => $parsed['date'], 'steps' => $parsed['steps']];
    }

    public static function mysqlDate(?string $rfc3339): ?string
    {
        return $rfc3339 ? (new DateTimeImmutable($rfc3339))->format('Y-m-d H:i:s') : null;
    }
}
