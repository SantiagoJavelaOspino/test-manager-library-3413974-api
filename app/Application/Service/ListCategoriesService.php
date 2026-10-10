<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\CategoryRepositoryInterface;
use App\Domain\Model\Category;

final class ListCategoriesService
{
    private CategoryRepositoryInterface $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function execute(): array
    {
        $categories = $this->categoryRepository->findAll();
        usort($categories, fn(Category $a, Category $b) => strcmp($a->name(), $b->name()));
        return array_map(fn(Category $cat) => $cat->toArray(), $categories);
    }
}
