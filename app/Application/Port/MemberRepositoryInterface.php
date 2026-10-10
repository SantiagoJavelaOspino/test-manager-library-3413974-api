<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\Member;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface MemberRepositoryInterface
{
    public function findById(Uuid $id): ?Member;
    public function findByDocumentNumber(string $documentNumber): ?Member;
    /**
     * @return array{items: array<int, Member>, total: int}
     */
    public function search(?string $query, int $page, int $size): array;
    public function countActiveLoans(Uuid $memberId): int;
    public function hasOverdueLoans(Uuid $memberId, DateTimeImmutable $now): bool;
    public function save(Member $member): void;
}
