<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidDateRangeException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class DateRange
{
    private DateTimeImmutable $from;
    private DateTimeImmutable $to;

    public function __construct(string $fromString, string $toString)
    {
        $fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', trim($fromString));
        $toDate = DateTimeImmutable::createFromFormat('!Y-m-d', trim($toString));

        if (!$fromDate || !$toDate) {
            throw new ValidationException('Las fechas deben tener el formato YYYY-MM-DD.', [
                ['field' => 'date_range', 'issue' => 'formato inválido, se requiere YYYY-MM-DD']
            ]);
        }

        if ($toDate < $fromDate) {
            throw new InvalidDateRangeException();
        }

        $this->from = $fromDate;
        $this->to = $toDate;
    }

    public function from(): DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): DateTimeImmutable
    {
        return $this->to;
    }

    public function fromString(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function toString(): string
    {
        return $this->to->format('Y-m-d');
    }
}
