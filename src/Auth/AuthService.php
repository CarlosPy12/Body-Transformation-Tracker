<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\Env;
use App\Support\Logger;
use PDO;

final class AuthService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function login(string $email, string $password): bool
    {
        if ($this->isRateLimited($email)) {
            Logger::write('security', 'Login limitato', ['email' => $email]);
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedLogin($email);
            Logger::write('security', 'Login fallito', ['email' => $email]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $this->pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$user['id']]);
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        if ((time() - (int) ($_SESSION['last_activity'] ?? 0)) > Env::int('APP_SESSION_TIMEOUT_MINUTES', 120) * 60) {
            $this->logout();
            return null;
        }
        $_SESSION['last_activity'] = time();
        $stmt = $this->pdo->prepare('SELECT id, email, name, role, is_active FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public function csrfToken(): string
    {
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    public function assertCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }

    private function isRateLimited(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)');
        $stmt->execute([$email]);
        return (int) $stmt->fetchColumn() >= 8;
    }

    private function recordFailedLogin(string $email): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO login_attempts(email, ip_address, attempted_at) VALUES (?, ?, UTC_TIMESTAMP())');
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    }
}
