<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

if (!class_exists(Minishlink\WebPush\VAPID::class)) {
    fwrite(STDERR, "Dipendenza Web Push mancante. Esegui prima composer install.\n");
    exit(1);
}

$subject = $argv[1] ?? 'mailto:admin@example.com';
try {
    $keys = Minishlink\WebPush\VAPID::createVapidKeys();
} catch (Throwable $e) {
    fwrite(STDERR, "Non riesco a generare le chiavi VAPID con questo PHP/OpenSSL.\n");
    fwrite(STDERR, "Errore: {$e->getMessage()}\n");
    fwrite(STDERR, "Verifica che l'estensione OpenSSL sia attiva e supporti curve EC P-256.\n");
    exit(1);
}

echo "VAPID_SUBJECT={$subject}\n";
echo 'VAPID_PUBLIC_KEY=' . $keys['publicKey'] . "\n";
echo 'VAPID_PRIVATE_KEY=' . $keys['privateKey'] . "\n";
