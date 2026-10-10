<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\LoanRepositoryInterface;
use App\Domain\Model\Loan;
use App\Domain\Model\LoanItem;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PgLoanRepository implements LoanRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(Uuid $id): ?Loan
    {
        $stmt = $this->pdo->prepare("SELECT id, member_id, loaned_by_user_id, loaned_at FROM library.loan WHERE id = :id");
        $stmt->execute(['id' => $id->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $itemsStmt = $this->pdo->prepare("
            SELECT id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at
            FROM library.loan_item
            WHERE loan_id = :loan_id
            ORDER BY id ASC;
        ");
        $itemsStmt->execute(['loan_id' => $id->value()]);
        $itemsRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($itemsRows as $iRow) {
            $items[] = $this->hydrateItem($iRow);
        }

        return new Loan(
            new Uuid((string)$row['id']),
            new Uuid((string)$row['member_id']),
            new Uuid((string)$row['loaned_by_user_id']),
            new DateTimeImmutable((string)$row['loaned_at']),
            $items
        );
    }

    public function findItemById(Uuid $loanId, Uuid $itemId): ?LoanItem
    {
        $stmt = $this->pdo->prepare("
            SELECT id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at
            FROM library.loan_item
            WHERE loan_id = :loan_id AND id = :item_id;
        ");
        $stmt->execute([
            'loan_id' => $loanId->value(),
            'item_id' => $itemId->value(),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydrateItem($row);
    }

    public function search(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?string $status,
        ?Uuid $memberId,
        int $page,
        int $size,
        DateTimeImmutable $now
    ): array {
        $clauses = [];
        $params = [];

        if ($from !== null) {
            $clauses[] = "l.loaned_at >= :from";
            $params['from'] = $from->format('Y-m-d 00:00:00P');
        }

        if ($to !== null) {
            $clauses[] = "l.loaned_at <= :to";
            $params['to'] = $to->format('Y-m-d 23:59:59P');
        }

        if ($memberId !== null) {
            $clauses[] = "l.member_id = :member_id";
            $params['member_id'] = $memberId->value();
        }

        $whereSql = count($clauses) > 0 ? "WHERE " . implode(" AND ", $clauses) : "";

        // Get loans
        $stmt = $this->pdo->prepare("
            SELECT l.id, l.member_id, l.loaned_by_user_id, l.loaned_at
            FROM library.loan l
            {$whereSql}
            ORDER BY l.loaned_at DESC;
        ");
        $stmt->execute($params);
        $allLoans = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $itemsStmt = $this->pdo->prepare("
                SELECT id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at
                FROM library.loan_item
                WHERE loan_id = :loan_id
                ORDER BY id ASC;
            ");
            $itemsStmt->execute(['loan_id' => $row['id']]);
            $itemRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            $items = array_map(fn($r) => $this->hydrateItem($r), $itemRows);

            $loan = new Loan(
                new Uuid((string)$row['id']),
                new Uuid((string)$row['member_id']),
                new Uuid((string)$row['loaned_by_user_id']),
                new DateTimeImmutable((string)$row['loaned_at']),
                $items
            );

            // Filter status in memory if status is specified
            if ($status !== null && trim($status) !== '') {
                if ($loan->status($now) !== strtolower(trim($status))) {
                    continue;
                }
            }

            $allLoans[] = $loan;
        }

        $total = count($allLoans);
        $offset = ($page - 1) * $size;
        $pageLoans = array_slice($allLoans, $offset, $size);

        return [
            'items' => $pageLoans,
            'total' => $total,
        ];
    }

    public function findOverdue(DateTimeImmutable $now): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                li.loan_id,
                li.id AS item_id,
                li.book_title,
                m.full_name AS member_name,
                li.due_at
            FROM library.loan_item li
            JOIN library.loan l ON l.id = li.loan_id
            JOIN library.member m ON m.id = l.member_id
            WHERE li.returned_at IS NULL
              AND li.due_at < :now
            ORDER BY li.due_at ASC;
        ");
        $stmt->execute(['now' => $now->format('Y-m-d H:i:sP')]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $dueAt = new DateTimeImmutable((string)$row['due_at']);
            $diffSeconds = $now->getTimestamp() - $dueAt->getTimestamp();
            $daysLate = (int)ceil($diffSeconds / 86400);

            $results[] = [
                'loan_id' => (string)$row['loan_id'],
                'item_id' => (string)$row['item_id'],
                'book_title' => (string)$row['book_title'],
                'member_name' => (string)$row['member_name'],
                'due_at' => $dueAt->format('Y-m-d\TH:i:s\Z'),
                'days_late' => max(1, $daysLate),
            ];
        }

        // Sort by days_late descending
        usort($results, fn($a, $b) => $b['days_late'] <=> $a['days_late']);

        return $results;
    }

    public function save(Loan $loan): void
    {
        $this->pdo->beginTransaction();
        try {
            $loanStmt = $this->pdo->prepare("
                INSERT INTO library.loan (id, member_id, loaned_by_user_id, loaned_at)
                VALUES (:id, :member_id, :loaned_by_user_id, :loaned_at)
                ON CONFLICT (id) DO NOTHING;
            ");
            $loanStmt->execute([
                'id' => $loan->id()->value(),
                'member_id' => $loan->memberId()->value(),
                'loaned_by_user_id' => $loan->loanedByUserId()->value(),
                'loaned_at' => $loan->loanedAt()->format('Y-m-d H:i:sP'),
            ]);

            $itemStmt = $this->pdo->prepare("
                INSERT INTO library.loan_item (
                    id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at
                )
                VALUES (
                    :id, :loan_id, :book_id, :book_title, :isbn, :category_name, :due_at, :returned_at
                )
                ON CONFLICT (id) DO UPDATE SET
                    returned_at = EXCLUDED.returned_at;
            ");

            foreach ($loan->items() as $item) {
                $itemStmt->execute([
                    'id' => $item->id()->value(),
                    'loan_id' => $item->loanId()->value(),
                    'book_id' => $item->bookId()->value(),
                    'book_title' => $item->bookTitle(),
                    'isbn' => $item->isbn()->value(),
                    'category_name' => $item->categoryName(),
                    'due_at' => $item->dueAt()->format('Y-m-d H:i:sP'),
                    'returned_at' => $item->returnedAt() ? $item->returnedAt()->format('Y-m-d H:i:sP') : null,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateItem(LoanItem $item): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE library.loan_item
            SET returned_at = :returned_at
            WHERE id = :id;
        ");
        $stmt->execute([
            'id' => $item->id()->value(),
            'returned_at' => $item->returnedAt() ? $item->returnedAt()->format('Y-m-d H:i:sP') : null,
        ]);
    }

    private function hydrateItem(array $row): LoanItem
    {
        $returnedAt = $row['returned_at'] ? new DateTimeImmutable((string)$row['returned_at']) : null;

        return new LoanItem(
            new Uuid((string)$row['id']),
            new Uuid((string)$row['loan_id']),
            new Uuid((string)$row['book_id']),
            (string)$row['book_title'],
            new Isbn((string)$row['isbn']),
            (string)$row['category_name'],
            new DateTimeImmutable((string)$row['due_at']),
            $returnedAt
        );
    }
}
