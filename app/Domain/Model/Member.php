<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\ValueObject\Uuid;

final class Member
{
    private Uuid $id;
    private string $fullName;
    private string $documentNumber;

    public function __construct(Uuid $id, string $fullName, string $documentNumber)
    {
        $this->id = $id;
        $this->fullName = trim($fullName);
        $this->documentNumber = trim($documentNumber);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function documentNumber(): string
    {
        return $this->documentNumber;
    }

    public function toArray(?int $activeLoans = null): array
    {
        $data = [
            'id' => $this->id->value(),
            'full_name' => $this->fullName,
            'document_number' => $this->documentNumber,
        ];
        if ($activeLoans !== null) {
            $data['active_loans'] = $activeLoans;
        }
        return $data;
    }
}
