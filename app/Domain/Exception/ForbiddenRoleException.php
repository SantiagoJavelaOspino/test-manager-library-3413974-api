<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class ForbiddenRoleException extends BusinessException
{
    public function __construct(string $message = 'Rol insuficiente para realizar esta acción.')
    {
        parent::__construct($message, 'FORBIDDEN_ROLE', 403);
    }
}
