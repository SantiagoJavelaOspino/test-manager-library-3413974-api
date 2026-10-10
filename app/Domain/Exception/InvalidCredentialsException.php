<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidCredentialsException extends BusinessException
{
    public function __construct(string $message = 'Credenciales inválidas.')
    {
        parent::__construct($message, 'INVALID_CREDENTIALS', 401);
    }
}
