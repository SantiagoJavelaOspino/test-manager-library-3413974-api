<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\Loan;
use App\Domain\Model\LoanItem;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface LoanRepositoryInterface
{
    public function findById(Uuid $id): ?Loan;
    public function findItemById(Uuid $loanId, Uuid $itemId): ?LoanItem;
    /**
     * @return array{items: array<int, Loan>, total: int}
     */
    public function search(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?string $status,
        ?Uuid $memberId,
        int $page,
        int $size,
        DateTimeImmutable $now
    ): array;
    /**
     * @return array<int, array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: string, days_late: int}>
     */
    public function findOverdue(DateTimeImmutable $now): array;
    public function save(Loan $loan): void;
    public function updateItem(LoanItem $item): void;
}
