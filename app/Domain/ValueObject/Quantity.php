<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\ValidationException;

final class Quantity
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 1) {
            throw new ValidationException('La cantidad debe ser mayor o igual a 1.', [
                ['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1']
            ]);
        }
        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }
}
