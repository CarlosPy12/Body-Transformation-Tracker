<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Support\Env;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file']['tmp_name'])) {
    header('Location: /');
    exit;
}

$maxBytes = Env::int('MAX_UPLOAD_MB', 8) * 1024 * 1024;
$name = $_FILES['file']['name'] ?? 'import.csv';
$tmp = $_FILES['file']['tmp_name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

if ($_FILES['file']['size'] > $maxBytes || $ext !== 'csv') {
    $_SESSION['shared_import_error'] = 'Il file condiviso non è un CSV valido o supera la dimensione massima.';
    header('Location: /?share=csv');
    exit;
}

$safeName = 'shared_' . bin2hex(random_bytes(8)) . '.csv';
$target = dirname(__DIR__) . '/storage/uploads/' . $safeName;
move_uploaded_file($tmp, $target);
$_SESSION['shared_import'] = ['path' => $target, 'name' => $name];

header('Location: /?share=csv');
