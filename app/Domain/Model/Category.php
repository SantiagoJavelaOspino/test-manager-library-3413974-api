<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\ValueObject\Uuid;

final class Category
{
    private Uuid $id;
    private string $name;

    public function __construct(Uuid $id, string $name)
    {
        $this->id = $id;
        $this->name = trim($name);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'name' => $this->name,
        ];
    }
}
