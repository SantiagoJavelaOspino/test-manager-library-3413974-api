<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\ReportReaderInterface;
use DateTimeImmutable;
use PDO;

final class PgReportReader implements ReportReaderInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getMostBorrowed(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                li.book_id,
                li.book_title,
                li.category_name,
                COUNT(li.id) AS times_borrowed
            FROM library.loan_item li
            JOIN library.loan l ON l.id = li.loan_id
            WHERE l.loaned_at >= :from
              AND l.loaned_at <= :to
            GROUP BY li.book_id, li.book_title, li.category_name
            ORDER BY times_borrowed DESC, li.book_title ASC;
        ");

        $stmt->execute([
            'from' => $from->format('Y-m-d 00:00:00P'),
            'to' => $to->format('Y-m-d 23:59:59P'),
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'book_id' => (string)$row['book_id'],
                'book_title' => (string)$row['book_title'],
                'category_name' => (string)$row['category_name'],
                'times_borrowed' => (int)$row['times_borrowed'],
            ];
        }

        return $results;
    }
}
