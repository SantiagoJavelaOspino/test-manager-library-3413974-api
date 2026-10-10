<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\User;
use App\Domain\ValueObject\Username;
use App\Domain\ValueObject\Uuid;

interface UserRepositoryInterface
{
    public function findByUsername(Username $username): ?User;
    public function findById(Uuid $id): ?User;
    public function save(User $user): void;
}
