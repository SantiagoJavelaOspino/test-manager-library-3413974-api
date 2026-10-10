<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\ClockInterface;
use App\Application\Port\LoanRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Model\Loan;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class LoanQueryService
{
    private LoanRepositoryInterface $loanRepository;
    private ClockInterface $clock;

    public function __construct(
        LoanRepositoryInterface $loanRepository,
        ClockInterface $clock
    ) {
        $this->loanRepository = $loanRepository;
        $this->clock = $clock;
    }

    public function getLoan(string $idString): array
    {
        $uuid = new Uuid($idString);
        $loan = $this->loanRepository->findById($uuid);
        if ($loan === null) {
            throw new NotFoundException('Préstamo no encontrado.');
        }

        return $loan->toArray($this->clock->now());
    }

    public function listLoans(
        ?string $fromString,
        ?string $toString,
        ?string $status,
        ?string $memberIdString,
        int $page,
        int $size
    ): array {
        $now = $this->clock->now();
        $clampedPage = max(1, $page);
        $clampedSize = max(1, min(50, $size));

        $from = $fromString ? DateTimeImmutable::createFromFormat('!Y-m-d', trim($fromString)) : null;
        $to = $toString ? DateTimeImmutable::createFromFormat('!Y-m-d', trim($toString)) : null;
        $memberUuid = $memberIdString ? new Uuid($memberIdString) : null;

        $result = $this->loanRepository->search(
            $from ?: null,
            $to ?: null,
            $status,
            $memberUuid,
            $clampedPage,
            $clampedSize,
            $now
        );

        return [
            'items' => array_map(fn(Loan $l) => $l->toArray($now), $result['items']),
            'page' => $clampedPage,
            'size' => $clampedSize,
            'total' => $result['total'],
        ];
    }

    /**
     * @return array{items: array<int, array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: string, days_late: int}>}
     */
    public function listOverdue(): array
    {
        $now = $this->clock->now();
        $items = $this->loanRepository->findOverdue($now);
        return [
            'items' => $items,
        ];
    }
}
