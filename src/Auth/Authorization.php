<?php

declare(strict_types=1);

namespace App\Auth;

final class Authorization
{
    public static function canAccessUser(array $actor, int $targetUserId): bool
    {
        return $actor['role'] === 'super_admin' || (int) $actor['id'] === $targetUserId;
    }

    public static function isSuperAdmin(array $actor): bool
    {
        return $actor['role'] === 'super_admin';
    }
}
