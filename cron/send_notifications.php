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
$today = date('Y-m-d');
$events = [];

$queries = [
    ['injection', 'glp1_injections', 'scheduled_at', 'Iniezione GLP-1 oggi', "È prevista un'iniezione da %s mg."],
    ['workout', 'workout_sessions', 'scheduled_at', 'Allenamento oggi', 'Hai programmato %s.'],
];

foreach ($queries as [$type, $table, $column, $title, $bodyTemplate]) {
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE DATE({$column}) = ? AND status = 'scheduled'");
    $stmt->execute([$today]);
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [$type, $table, $row, $title, $bodyTemplate];
    }
}

foreach ($events as [$type, $table, $event, $title, $bodyTemplate]) {
    $exists = $pdo->prepare('SELECT id FROM notification_log WHERE user_id = ? AND notification_type = ? AND related_table = ? AND related_id = ? AND scheduled_for = ?');
    $exists->execute([$event['user_id'], $type, $table, $event['id'], $today]);
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
        ->execute([$event['user_id'], $type, $table, $event['id'], $today, $status, $error]);
}

echo "Notifiche elaborate.\n";
