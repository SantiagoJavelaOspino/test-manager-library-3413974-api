<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\Uuid;

interface TokenIssuerInterface
{
    /**
     * @return array{access_token: string, token_type: string, expires_in: int, role: string}
     */
    public function issue(Uuid $userId, Role $role): array;

    /**
     * @return array{user_id: string, role: string}
     */
    public function verify(string $token): array;
}
