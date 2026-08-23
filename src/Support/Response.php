<?php

declare(strict_types=1);

namespace App\Support;

final class Response
{
    public static function json(bool $success, mixed $data = null, ?array $error = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'data' => $data,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function ok(mixed $data = []): void
    {
        self::json(true, $data);
    }

    public static function fail(string $code, string $message, int $status = 400): void
    {
        self::json(false, null, ['code' => $code, 'message' => $message], $status);
    }
}
