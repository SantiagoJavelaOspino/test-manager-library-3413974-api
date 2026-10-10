<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class IsbnTakenException extends BusinessException
{
    public function __construct(string $message = 'El ISBN ya está asignado a otro libro activo.')
    {
        parent::__construct($message, 'ISBN_TAKEN', 409);
    }
}
