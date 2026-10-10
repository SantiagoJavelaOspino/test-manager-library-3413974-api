<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\ValidationException;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Loan
{
    private Uuid $id;
    private Uuid $memberId;
    private Uuid $loanedByUserId;
    private DateTimeImmutable $loanedAt;
    /** @var array<int, LoanItem> */
    private array $items;

    /**
     * @param array<int, LoanItem> $items
     */
    public function __construct(
        Uuid $id,
        Uuid $memberId,
        Uuid $loanedByUserId,
        DateTimeImmutable $loanedAt,
        array $items
    ) {
        if (count($items) < 1 || count($items) > 3) {
            throw new ValidationException('Un préstamo debe contener entre 1 y 3 libros.', [
                ['field' => 'items', 'issue' => 'debe contener entre 1 y 3 libros']
            ]);
        }

        $this->id = $id;
        $this->memberId = $memberId;
        $this->loanedByUserId = $loanedByUserId;
        $this->loanedAt = $loanedAt;
        $this->items = $items;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function memberId(): Uuid
    {
        return $this->memberId;
    }

    public function loanedByUserId(): Uuid
    {
        return $this->loanedByUserId;
    }

    public function loanedAt(): DateTimeImmutable
    {
        return $this->loanedAt;
    }

    /**
     * @return array<int, LoanItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function status(DateTimeImmutable $now): string
    {
        $allReturned = true;
        $hasOverdue = false;

        foreach ($this->items as $item) {
            if (!$item->isReturned()) {
                $allReturned = false;
                if ($item->dueAt() < $now) {
                    $hasOverdue = true;
                }
            }
        }

        if ($allReturned) {
            return 'closed';
        }
        if ($hasOverdue) {
            return 'overdue';
        }
        return 'open';
    }

    public function toArray(DateTimeImmutable $now): array
    {
        return [
            'id' => $this->id->value(),
            'member_id' => $this->memberId->value(),
            'loaned_at' => $this->loanedAt->format('Y-m-d\TH:i:s\Z'),
            'status' => $this->status($now),
            'items' => array_map(fn(LoanItem $item) => $item->toArray(), $this->items),
        ];
    }
}
