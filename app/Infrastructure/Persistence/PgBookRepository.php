<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\BookRepositoryInterface;
use App\Domain\Model\Book;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PgBookRepository implements BookRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(Uuid $id): ?Book
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, author, isbn, category_id, total_copies, available_copies, cover_key, deleted_at
            FROM library.book
            WHERE id = :id;
        ");
        $stmt->execute(['id' => $id->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByIsbn(Isbn $isbn): ?Book
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, author, isbn, category_id, total_copies, available_copies, cover_key, deleted_at
            FROM library.book
            WHERE isbn = :isbn AND deleted_at IS NULL;
        ");
        $stmt->execute(['isbn' => $isbn->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function search(?string $query, ?Uuid $categoryId, int $page, int $size): array
    {
        $offset = ($page - 1) * $size;
        $clauses = ["deleted_at IS NULL"];
        $params = [];

        if ($query !== null && trim($query) !== '') {
            $clauses[] = "(title ILIKE :q OR author ILIKE :q)";
            $params['q'] = '%' . trim($query) . '%';
        }

        if ($categoryId !== null) {
            $clauses[] = "category_id = :cat_id";
            $params['cat_id'] = $categoryId->value();
        }

        $whereSql = "WHERE " . implode(" AND ", $clauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM library.book {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $this->pdo->prepare("
            SELECT id, title, author, isbn, category_id, total_copies, available_copies, cover_key, deleted_at
            FROM library.book
            {$whereSql}
            ORDER BY title ASC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue('limit', $size, PDO::PARAM_INT);
        $dataStmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $items = [];
        while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function hasOpenLoans(Uuid $bookId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM library.loan_item
            WHERE book_id = :book_id AND returned_at IS NULL;
        ");
        $stmt->execute(['book_id' => $bookId->value()]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function save(Book $book): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO library.book (
                id, title, author, isbn, category_id, total_copies, available_copies, cover_key, deleted_at
            )
            VALUES (
                :id, :title, :author, :isbn, :category_id, :total_copies, :available_copies, :cover_key, :deleted_at
            )
            ON CONFLICT (id) DO UPDATE SET
                title = EXCLUDED.title,
                author = EXCLUDED.author,
                isbn = EXCLUDED.isbn,
                category_id = EXCLUDED.category_id,
                total_copies = EXCLUDED.total_copies,
                available_copies = EXCLUDED.available_copies,
                cover_key = EXCLUDED.cover_key,
                deleted_at = EXCLUDED.deleted_at;
        ");

        $deletedAt = $book->deletedAt() ? $book->deletedAt()->format('Y-m-d H:i:sP') : null;

        $stmt->execute([
            'id' => $book->id()->value(),
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn()->value(),
            'category_id' => $book->categoryId()->value(),
            'total_copies' => $book->totalCopies(),
            'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(),
            'deleted_at' => $deletedAt,
        ]);
    }

    private function hydrate(array $row): Book
    {
        $deletedAt = $row['deleted_at'] ? new DateTimeImmutable((string)$row['deleted_at']) : null;

        return new Book(
            new Uuid((string)$row['id']),
            (string)$row['title'],
            (string)$row['author'],
            new Isbn((string)$row['isbn']),
            new Uuid((string)$row['category_id']),
            new Quantity((int)$row['total_copies']),
            (int)$row['available_copies'],
            $row['cover_key'] !== null ? (string)$row['cover_key'] : null,
            $deletedAt
        );
    }
}
