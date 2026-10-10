<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\MemberRepositoryInterface;
use App\Domain\Exception\DocumentTakenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Model\Member;
use App\Domain\ValueObject\Uuid;

final class MemberService
{
    private MemberRepositoryInterface $memberRepository;

    public function __construct(MemberRepositoryInterface $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }

    public function create(string $fullName, string $documentNumber): array
    {
        $trimmedName = trim($fullName);
        $trimmedDoc = trim($documentNumber);

        if ($trimmedName === '') {
            throw new ValidationException('El nombre completo es requerido.', [
                ['field' => 'full_name', 'issue' => 'no puede estar vacío']
            ]);
        }
        if ($trimmedDoc === '') {
            throw new ValidationException('El número de documento es requerido.', [
                ['field' => 'document_number', 'issue' => 'no puede estar vacío']
            ]);
        }

        if ($this->memberRepository->findByDocumentNumber($trimmedDoc) !== null) {
            throw new DocumentTakenException();
        }

        $member = new Member(Uuid::generate(), $trimmedName, $trimmedDoc);
        $this->memberRepository->save($member);

        return $member->toArray();
    }

    public function get(string $idString): array
    {
        $uuid = new Uuid($idString);
        $member = $this->memberRepository->findById($uuid);
        if ($member === null) {
            throw new NotFoundException('Socio no encontrado.');
        }

        $activeLoans = $this->memberRepository->countActiveLoans($uuid);
        return $member->toArray($activeLoans);
    }

    public function search(?string $query, int $page, int $size): array
    {
        $clampedPage = max(1, $page);
        $clampedSize = max(1, min(50, $size));

        $result = $this->memberRepository->search($query, $clampedPage, $clampedSize);

        return [
            'items' => array_map(fn(Member $m) => $m->toArray(), $result['items']),
            'page' => $clampedPage,
            'size' => $clampedSize,
            'total' => $result['total'],
        ];
    }
}
