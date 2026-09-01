<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Support\Database;
use App\Support\Env;
use App\Support\Logger;

$pdo = Database::pdo();
if (!class_exists(Minishlink\WebPush\WebPush::class)) {
    Logger::write('notifications', 'Dipendenza Web Push non installata');
    echo "Dipendenza Web Push non installata.\n";
    exit(0);
}

$auth = [
    'VAPID' => [
        'subject' => Env::get('VAPID_SUBJECT', 'mailto:admin@example.com'),
        'publicKey' => Env::get('VAPID_PUBLIC_KEY', ''),
        'privateKey' => Env::get('VAPID_PRIVATE_KEY', ''),
    ],
];

$webPush = new Minishlink\WebPush\WebPush($auth);
$now = new DateTimeImmutable('now');
$events = [];

$queries = [
    ['injection', 'glp1_injections', 'scheduled_at', 'Iniezione GLP-1', "È prevista un'iniezione da %s mg."],
    ['workout', 'workout_sessions', 'scheduled_at', 'Allenamento', 'Hai programmato %s.'],
];

foreach ($queries as [$type, $table, $column, $title, $bodyTemplate]) {
    $beforeSelect = table_has_column($pdo, $table, 'reminder_minutes_before') ? 'reminder_minutes_before' : ($type === 'injection' ? '1440 AS reminder_minutes_before' : '60 AS reminder_minutes_before');
    $repeatSelect = table_has_column($pdo, $table, 'reminder_repeat_minutes') ? 'reminder_repeat_minutes' : '0 AS reminder_repeat_minutes';
    $stmt = $pdo->prepare("SELECT *, {$beforeSelect}, {$repeatSelect} FROM {$table} WHERE {$column} >= ? AND {$column} <= DATE_ADD(?, INTERVAL 2 DAY) AND status = 'scheduled'");
    $stmt->execute([$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
    foreach ($stmt->fetchAll() as $row) {
        $eventAt = new DateTimeImmutable((string) $row[$column]);
        $minutesUntil = (int) floor(($eventAt->getTimestamp() - $now->getTimestamp()) / 60);
        $reminderBefore = max(0, (int) ($row['reminder_minutes_before'] ?? ($type === 'injection' ? 1440 : 60)));
        if ($minutesUntil < 0 || $minutesUntil > $reminderBefore) {
            continue;
        }
        $repeat = max(0, (int) ($row['reminder_repeat_minutes'] ?? 0));
        $bucket = $repeat > 0 ? (string) floor($minutesUntil / $repeat) : 'once';
        $events[] = [$type . '_' . $bucket, $type, $table, $column, $row, $title, $bodyTemplate];
    }
}

foreach ($events as [$notificationType, $type, $table, $column, $event, $title, $bodyTemplate]) {
    $scheduledFor = date('Y-m-d', strtotime((string) $event[$column]));
    $exists = $pdo->prepare('SELECT id FROM notification_log WHERE user_id = ? AND notification_type = ? AND related_table = ? AND related_id = ? AND scheduled_for = ?');
    $exists->execute([$event['user_id'], $notificationType, $table, $event['id'], $scheduledFor]);
    if ($exists->fetch()) {
        continue;
    }
    $subs = $pdo->prepare('SELECT * FROM push_subscriptions WHERE user_id = ?');
    $subs->execute([$event['user_id']]);
    $bodyValue = $type === 'injection' ? number_format((float) $event['planned_dose_mg'], 1, ',', '') : $event['workout_type'];
    $payload = json_encode(['title' => $title, 'body' => sprintf($bodyTemplate, $bodyValue), 'url' => '/']);
    $status = 'sent';
    $error = null;
    foreach ($subs->fetchAll() as $sub) {
        try {
            $subscription = Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => ['p256dh' => $sub['public_key'], 'auth' => $sub['auth_token']],
            ]);
            $webPush->queueNotification($subscription, $payload);
        } catch (Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
        }
    }
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            $status = 'failed';
            $error = $report->getReason();
        }
    }
    $pdo->prepare('INSERT IGNORE INTO notification_log(user_id, notification_type, related_table, related_id, scheduled_for, sent_at, status, error_message) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?)')
        ->execute([$event['user_id'], $notificationType, $table, $event['id'], $scheduledFor, $status, $error]);
}

echo "Notifiche elaborate.\n";

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return $cache[$key] = ((int) $stmt->fetchColumn() > 0);
}
