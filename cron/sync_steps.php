<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Import\StepsCsvParser;
use App\Support\Database;
use App\Support\Env;
use App\Support\Logger;
use App\Sync\StepsSyncService;

$pdo = Database::pdo();
$folderId = Env::get('GOOGLE_DRIVE_STEPS_FOLDER_ID');
$credentials = Env::get('GOOGLE_SERVICE_ACCOUNT_JSON_PATH');
$syncStartDate = Env::get('STEPS_SYNC_START_DATE', date('Y-m-d'));

if (!$folderId || !$credentials || !class_exists(Google\Client::class)) {
    Logger::write('sync', 'Google Drive non configurato o dipendenze Composer mancanti');
    echo "Google Drive non configurato o dipendenze mancanti.\n";
    exit(0);
}

$users = $pdo->query('SELECT id FROM users WHERE is_active = 1')->fetchAll();
$client = new Google\Client();
$client->setAuthConfig($credentials);
$client->addScope(Google\Service\Drive::DRIVE_READONLY);
$drive = new Google\Service\Drive($client);
$sync = new StepsSyncService($pdo);

foreach ($users as $user) {
    try {
        $files = $drive->files->listFiles([
            'q' => sprintf("'%s' in parents and trashed = false", addslashes($folderId)),
            'fields' => 'files(id,name,modifiedTime,md5Checksum)',
        ]);
        foreach ($files->getFiles() as $file) {
            $fileDate = StepsCsvParser::dateFromFileName($file->getName());
            if ($fileDate === null || $fileDate < $syncStartDate) {
                continue;
            }
            try {
                $content = $drive->files->get($file->getId(), ['alt' => 'media'])->getBody()->getContents();
                $sync->upsertDailySteps((int) $user['id'], $file->getId(), $file->getName(), StepsSyncService::mysqlDate($file->getModifiedTime()), $content);
            } catch (Throwable $e) {
                Logger::write('sync', 'Errore file passi, batch continua', ['file' => $file->getName(), 'error' => $e->getMessage()]);
            }
        }
    } catch (Throwable $e) {
        Logger::write('sync', 'Errore batch utente', ['user_id' => $user['id'], 'error' => $e->getMessage()]);
    }
}

echo "Sync passi completato.\n";
