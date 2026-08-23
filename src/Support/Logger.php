<?php

declare(strict_types=1);

namespace App\Support;

final class Logger
{
    public static function write(string $channel, string $message, array $context = []): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $safeContext = array_diff_key($context, array_flip(['password', 'token', 'secret', 'private_key']));
        $line = json_encode([
            'at' => gmdate('c'),
            'channel' => $channel,
            'message' => $message,
            'context' => $safeContext,
        ], JSON_UNESCAPED_UNICODE);
        file_put_contents($dir . '/' . $channel . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
