<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class MemberLimitExceededException extends BusinessException
{
    public function __construct(int $currentCount, string $message = 'El socio supera el límite de 3 libros sin devolver.')
    {
        parent::__construct(
            $message,
            'MEMBER_LIMIT_EXCEEDED',
            422,
            [['field' => 'member_id', 'issue' => "El socio actualmente tiene {$currentCount} libros sin devolver."]]
        );
    }
}
