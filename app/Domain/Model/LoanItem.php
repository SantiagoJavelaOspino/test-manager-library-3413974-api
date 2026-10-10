<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\ItemAlreadyReturnedException;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class LoanItem
{
    private Uuid $id;
    private Uuid $loanId;
    private Uuid $bookId;
    private string $bookTitle;
    private Isbn $isbn;
    private string $categoryName;
    private DateTimeImmutable $dueAt;
    private ?DateTimeImmutable $returnedAt;

    public function __construct(
        Uuid $id,
        Uuid $loanId,
        Uuid $bookId,
        string $bookTitle,
        Isbn $isbn,
        string $categoryName,
        DateTimeImmutable $dueAt,
        ?DateTimeImmutable $returnedAt = null
    ) {
        $this->id = $id;
        $this->loanId = $loanId;
        $this->bookId = $bookId;
        $this->bookTitle = trim($bookTitle);
        $this->isbn = $isbn;
        $this->categoryName = trim($categoryName);
        $this->dueAt = $dueAt;
        $this->returnedAt = $returnedAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function loanId(): Uuid
    {
        return $this->loanId;
    }

    public function bookId(): Uuid
    {
        return $this->bookId;
    }

    public function bookTitle(): string
    {
        return $this->bookTitle;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryName(): string
    {
        return $this->categoryName;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isReturned() && $this->dueAt < $now;
    }

    public function return(DateTimeImmutable $returnedAt): void
    {
        if ($this->returnedAt !== null) {
            throw new ItemAlreadyReturnedException();
        }
        $this->returnedAt = $returnedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'book_id' => $this->bookId->value(),
            'book_title' => $this->bookTitle,
            'isbn' => $this->isbn->value(),
            'category_name' => $this->categoryName,
            'due_at' => $this->dueAt->format('Y-m-d\TH:i:s\Z'),
            'returned_at' => $this->returnedAt ? $this->returnedAt->format('Y-m-d\TH:i:s\Z') : null,
        ];
    }
}
