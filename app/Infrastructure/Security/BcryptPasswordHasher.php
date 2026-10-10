<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Port\PasswordHasherInterface;

final class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
