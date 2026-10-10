<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class BookNotAvailableException extends BusinessException
{
    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message = 'No quedan ejemplares disponibles del libro solicitado.', array $details = [])
    {
        parent::__construct($message, 'BOOK_NOT_AVAILABLE', 409, $details);
    }
}
