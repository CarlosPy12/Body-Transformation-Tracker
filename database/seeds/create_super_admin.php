<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Support\Database;
use App\Support\Env;

$email = Env::get('FIRST_ADMIN_EMAIL');
$password = Env::get('FIRST_ADMIN_PASSWORD');
$name = Env::get('FIRST_ADMIN_NAME', 'Super Admin');

if (!$email || !$password) {
    fwrite(STDERR, "Configura FIRST_ADMIN_EMAIL e FIRST_ADMIN_PASSWORD nel file .env.\n");
    exit(1);
}

$pdo = Database::pdo();
$stmt = $pdo->prepare('INSERT INTO users(email, password_hash, name, role, is_active) VALUES (?, ?, ?, "super_admin", 1) ON DUPLICATE KEY UPDATE role = "super_admin", is_active = 1');
$stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name]);
$userId = (int) $pdo->lastInsertId();
if ($userId > 0) {
    $pdo->prepare('INSERT IGNORE INTO user_settings(user_id) VALUES (?)')->execute([$userId]);
    $pdo->prepare('INSERT IGNORE INTO notification_preferences(user_id) VALUES (?)')->execute([$userId]);
}

echo "Super admin pronto: {$email}\n";
