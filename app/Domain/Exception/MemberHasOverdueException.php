<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class MemberHasOverdueException extends BusinessException
{
    public function __construct(string $message = 'El socio tiene libros vencidos pendientes y no puede solicitar más préstamos.')
    {
        parent::__construct($message, 'MEMBER_HAS_OVERDUE', 409);
    }
}
