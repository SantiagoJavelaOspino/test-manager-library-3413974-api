<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\BookNotAvailableException;
use App\Domain\Exception\ValidationException;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Book
{
    private Uuid $id;
    private string $title;
    private string $author;
    private Isbn $isbn;
    private Uuid $categoryId;
    private int $totalCopies;
    private int $availableCopies;
    private ?string $coverKey;
    private ?DateTimeImmutable $deletedAt;

    public function __construct(
        Uuid $id,
        string $title,
        string $author,
        Isbn $isbn,
        Uuid $categoryId,
        Quantity $totalCopies,
        ?int $availableCopies = null,
        ?string $coverKey = null,
        ?DateTimeImmutable $deletedAt = null
    ) {
        $this->id = $id;
        $this->title = trim($title);
        $this->author = trim($author);
        $this->isbn = $isbn;
        $this->categoryId = $categoryId;
        $this->totalCopies = $totalCopies->value();
        $this->availableCopies = $availableCopies ?? $this->totalCopies;
        $this->coverKey = $coverKey;
        $this->deletedAt = $deletedAt;

        if ($this->availableCopies < 0 || $this->availableCopies > $this->totalCopies) {
            throw new ValidationException('Los ejemplares disponibles deben estar entre 0 y el total.');
        }
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): Uuid
    {
        return $this->categoryId;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function restock(Quantity $newTotal): void
    {
        $newTotalVal = $newTotal->value();
        $difference = $newTotalVal - $this->totalCopies;
        $newAvailable = $this->availableCopies + $difference;

        if ($newAvailable < 0) {
            throw new ValidationException('No se puede reducir el total por debajo de los ejemplares actualmente prestados.', [
                ['field' => 'total_copies', 'issue' => 'dejaría disponibles negativos']
            ]);
        }

        $this->totalCopies = $newTotalVal;
        $this->availableCopies = $newAvailable;
    }

    public function updateDetails(string $title, string $author, Uuid $categoryId, Quantity $totalCopies): void
    {
        $this->title = trim($title);
        $this->author = trim($author);
        $this->categoryId = $categoryId;
        $this->restock($totalCopies);
    }

    public function lendCopy(): void
    {
        if ($this->availableCopies <= 0) {
            throw new BookNotAvailableException("No quedan ejemplares disponibles de «{$this->title}».", [
                ['field' => 'book_id', 'issue' => 'sin ejemplares']
            ]);
        }
        $this->availableCopies--;
    }

    public function returnCopy(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new ValidationException("Los ejemplares disponibles no pueden superar el total.");
        }
        $this->availableCopies++;
    }

    public function setCoverKey(?string $coverKey): void
    {
        $this->coverKey = $coverKey;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
    }

    public function toArray(?array $categoryArray = null): array
    {
        $data = [
            'id' => $this->id->value(),
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn->value(),
            'total_copies' => $this->totalCopies,
            'available_copies' => $this->availableCopies,
            'cover_key' => $this->coverKey,
        ];
        if ($categoryArray !== null) {
            $data['category'] = $categoryArray;
        } else {
            $data['category_id'] = $this->categoryId->value();
        }
        return $data;
    }
}
