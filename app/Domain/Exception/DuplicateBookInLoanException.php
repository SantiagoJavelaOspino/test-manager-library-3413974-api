<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class DuplicateBookInLoanException extends BusinessException
{
    public function __construct(string $message = 'No se puede incluir el mismo libro más de una vez en el préstamo.')
    {
        parent::__construct($message, 'DUPLICATE_BOOK_IN_LOAN', 422);
    }
}
