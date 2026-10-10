<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class UsernameTakenException extends BusinessException
{
    public function __construct(string $message = 'El nombre de usuario ya se encuentra registrado.')
    {
        parent::__construct($message, 'USERNAME_TAKEN', 409);
    }
}
