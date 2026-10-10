<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\CategoryRepositoryInterface;
use App\Domain\Model\Category;
use App\Domain\ValueObject\Uuid;
use PDO;

final class PgCategoryRepository implements CategoryRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT id, name FROM library.category ORDER BY name ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];
        foreach ($results as $row) {
            $categories[] = new Category(new Uuid((string)$row['id']), (string)$row['name']);
        }
        return $categories;
    }

    public function findById(Uuid $id): ?Category
    {
        $stmt = $this->pdo->prepare("SELECT id, name FROM library.category WHERE id = :id");
        $stmt->execute(['id' => $id->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return new Category(new Uuid((string)$row['id']), (string)$row['name']);
    }
}
