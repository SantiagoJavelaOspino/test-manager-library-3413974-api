<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class BookHasOpenLoansException extends BusinessException
{
    public function __construct(string $message = 'El libro tiene préstamos abiertos y no puede darse de baja.')
    {
        parent::__construct($message, 'BOOK_HAS_OPEN_LOANS', 409);
    }
}
