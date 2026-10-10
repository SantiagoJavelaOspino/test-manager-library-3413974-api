<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\Username;
use App\Domain\ValueObject\Uuid;

final class User
{
    private Uuid $id;
    private Username $username;
    private string $passwordHash;
    private Role $role;

    public function __construct(Uuid $id, Username $username, string $passwordHash, Role $role)
    {
        $this->id = $id;
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'username' => $this->username->value(),
            'role' => $this->role->value(),
        ];
    }
}
