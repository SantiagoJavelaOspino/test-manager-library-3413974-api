<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class ItemAlreadyReturnedException extends BusinessException
{
    public function __construct(string $message = 'El ejemplar ya fue devuelto anteriormente.')
    {
        parent::__construct($message, 'ITEM_ALREADY_RETURNED', 409);
    }
}
