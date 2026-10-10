<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\ValidationException;

final class Username
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            throw new ValidationException('El nombre de usuario no puede estar vacío.', [
                ['field' => 'username', 'issue' => 'no puede estar vacío']
            ]);
        }
        if (strlen($normalized) > 120) {
            throw new ValidationException('El nombre de usuario no puede superar 120 caracteres.', [
                ['field' => 'username', 'issue' => 'longitud máxima 120 caracteres']
            ]);
        }
        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
