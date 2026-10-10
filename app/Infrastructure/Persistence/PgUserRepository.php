<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\UserRepositoryInterface;
use App\Domain\Model\User;
use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\Username;
use App\Domain\ValueObject\Uuid;
use PDO;

final class PgUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUsername(Username $username): ?User
    {
        $stmt = $this->pdo->prepare("SELECT id, username, password_hash, role FROM library.\"user\" WHERE username = :username");
        $stmt->execute(['username' => $username->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new User(
            new Uuid((string)$row['id']),
            new Username((string)$row['username']),
            (string)$row['password_hash'],
            new Role((string)$row['role'])
        );
    }

    public function findById(Uuid $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT id, username, password_hash, role FROM library.\"user\" WHERE id = :id");
        $stmt->execute(['id' => $id->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new User(
            new Uuid((string)$row['id']),
            new Username((string)$row['username']),
            (string)$row['password_hash'],
            new Role((string)$row['role'])
        );
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO library.\"user\" (id, username, password_hash, role)
            VALUES (:id, :username, :password_hash, :role)
            ON CONFLICT (id) DO UPDATE SET
                username = EXCLUDED.username,
                password_hash = EXCLUDED.password_hash,
                role = EXCLUDED.role;
        ");
        $stmt->execute([
            'id' => $user->id()->value(),
            'username' => $user->username()->value(),
            'password_hash' => $user->passwordHash(),
            'role' => $user->role()->value(),
        ]);
    }
}
