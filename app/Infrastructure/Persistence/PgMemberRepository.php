<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\MemberRepositoryInterface;
use App\Domain\Model\Member;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PgMemberRepository implements MemberRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(Uuid $id): ?Member
    {
        $stmt = $this->pdo->prepare("SELECT id, full_name, document_number FROM library.member WHERE id = :id");
        $stmt->execute(['id' => $id->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new Member(new Uuid((string)$row['id']), (string)$row['full_name'], (string)$row['document_number']);
    }

    public function findByDocumentNumber(string $documentNumber): ?Member
    {
        $stmt = $this->pdo->prepare("SELECT id, full_name, document_number FROM library.member WHERE document_number = :doc");
        $stmt->execute(['doc' => trim($documentNumber)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new Member(new Uuid((string)$row['id']), (string)$row['full_name'], (string)$row['document_number']);
    }

    public function search(?string $query, int $page, int $size): array
    {
        $offset = ($page - 1) * $size;
        $sqlWhere = "";
        $params = [];

        if ($query !== null && trim($query) !== '') {
            $sqlWhere = "WHERE full_name ILIKE :q OR document_number ILIKE :q";
            $params['q'] = '%' . trim($query) . '%';
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM library.member {$sqlWhere}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $this->pdo->prepare("
            SELECT id, full_name, document_number
            FROM library.member
            {$sqlWhere}
            ORDER BY full_name ASC
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
            $items[] = new Member(new Uuid((string)$row['id']), (string)$row['full_name'], (string)$row['document_number']);
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function countActiveLoans(Uuid $memberId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(li.id)
            FROM library.loan_item li
            JOIN library.loan l ON l.id = li.loan_id
            WHERE l.member_id = :member_id AND li.returned_at IS NULL;
        ");
        $stmt->execute(['member_id' => $memberId->value()]);
        return (int)$stmt->fetchColumn();
    }

    public function hasOverdueLoans(Uuid $memberId, DateTimeImmutable $now): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(li.id)
            FROM library.loan_item li
            JOIN library.loan l ON l.id = li.loan_id
            WHERE l.member_id = :member_id
              AND li.returned_at IS NULL
              AND li.due_at < :now;
        ");
        $stmt->execute([
            'member_id' => $memberId->value(),
            'now' => $now->format('Y-m-d H:i:sP'),
        ]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function save(Member $member): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO library.member (id, full_name, document_number)
            VALUES (:id, :full_name, :document_number)
            ON CONFLICT (id) DO UPDATE SET
                full_name = EXCLUDED.full_name,
                document_number = EXCLUDED.document_number;
        ");
        $stmt->execute([
            'id' => $member->id()->value(),
            'full_name' => $member->fullName(),
            'document_number' => $member->documentNumber(),
        ]);
    }
}
