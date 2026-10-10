<?php

declare(strict_types=1);

namespace App\Application\Port;

use DateTimeImmutable;

interface ReportReaderInterface
{
    /**
     * @return array<int, array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>
     */
    public function getMostBorrowed(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
