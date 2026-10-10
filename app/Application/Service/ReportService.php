<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\ReportReaderInterface;
use App\Domain\Exception\ForbiddenRoleException;
use App\Domain\ValueObject\DateRange;
use App\Domain\ValueObject\Role;

final class ReportService
{
    private ReportReaderInterface $reportReader;

    public function __construct(ReportReaderInterface $reportReader)
    {
        $this->reportReader = $reportReader;
    }

    /**
     * @return array{from: string, to: string, rows: array<int, array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>}
     */
    public function execute(string $fromString, string $toString, Role $role): array
    {
        if (!$role->isAdmin()) {
            throw new ForbiddenRoleException();
        }

        $dateRange = new DateRange($fromString, $toString);

        $rows = $this->reportReader->getMostBorrowed($dateRange->from(), $dateRange->to());

        return [
            'from' => $dateRange->fromString(),
            'to' => $dateRange->toString(),
            'rows' => $rows,
        ];
    }
}
