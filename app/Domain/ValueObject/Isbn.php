<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\ValidationException;

final class Isbn
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if (!preg_match('/^\d{13}$/', $trimmed)) {
            throw new ValidationException('El ISBN debe contener exactamente 13 dígitos numéricos.', [
                ['field' => 'isbn', 'issue' => 'debe contener exactamente 13 dígitos numéricos']
            ]);
        }
        $this->value = $trimmed;
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
