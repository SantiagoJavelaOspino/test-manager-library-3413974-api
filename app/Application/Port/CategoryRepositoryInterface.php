<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\Category;
use App\Domain\ValueObject\Uuid;

interface CategoryRepositoryInterface
{
    /**
     * @return array<int, Category>
     */
    public function findAll(): array;
    public function findById(Uuid $id): ?Category;
}
