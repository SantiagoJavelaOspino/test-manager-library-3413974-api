<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

abstract class BusinessException extends Exception
{
    protected string $errorCode;
    protected int $httpStatus;
    /** @var array<int, array{field: string, issue: string}> */
    protected array $details = [];

    /**
     * @param array<int, array{field: string, issue: string}> $details
     */
    public function __construct(string $message, string $errorCode, int $httpStatus = 400, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<int, array{field: string, issue: string}>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
