<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\ValidationException;

final class Role
{
    public const ADMIN = 'admin';
    public const LIBRARIAN = 'librarian';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if ($normalized !== self::ADMIN && $normalized !== self::LIBRARIAN) {
            throw new ValidationException('El rol debe ser "admin" o "librarian".', [
                ['field' => 'role', 'issue' => 'debe ser admin o librarian']
            ]);
        }
        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function isLibrarian(): bool
    {
        return $this->value === self::LIBRARIAN;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
