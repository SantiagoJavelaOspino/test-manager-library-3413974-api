<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\Book;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Uuid;

interface BookRepositoryInterface
{
    public function findById(Uuid $id): ?Book;
    public function findByIsbn(Isbn $isbn): ?Book;
    /**
     * @return array{items: array<int, Book>, total: int}
     */
    public function search(?string $query, ?Uuid $categoryId, int $page, int $size): array;
    public function hasOpenLoans(Uuid $bookId): bool;
    public function save(Book $book): void;
}
