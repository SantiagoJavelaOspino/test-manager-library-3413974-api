<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class DocumentTakenException extends BusinessException
{
    public function __construct(string $message = 'El número de documento ya está registrado para otro socio.')
    {
        parent::__construct($message, 'DOCUMENT_TAKEN', 409);
    }
}
