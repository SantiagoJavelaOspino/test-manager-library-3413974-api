<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidDateRangeException extends BusinessException
{
    public function __construct(string $message = 'El rango de fechas es inválido: la fecha final es anterior a la fecha inicial.')
    {
        parent::__construct($message, 'INVALID_DATE_RANGE', 422);
    }
}
