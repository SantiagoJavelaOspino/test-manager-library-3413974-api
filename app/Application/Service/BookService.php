<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\BookRepositoryInterface;
use App\Application\Port\CategoryRepositoryInterface;
use App\Application\Port\ClockInterface;
use App\Application\Port\CoverStorageInterface;
use App\Domain\Exception\BookHasOpenLoansException;
use App\Domain\Exception\ForbiddenRoleException;
use App\Domain\Exception\IsbnTakenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Model\Book;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\Uuid;

final class BookService
{
    private BookRepositoryInterface $bookRepository;
    private CategoryRepositoryInterface $categoryRepository;
    private CoverStorageInterface $coverStorage;
    private ClockInterface $clock;

    public function __construct(
        BookRepositoryInterface $bookRepository,
        CategoryRepositoryInterface $categoryRepository,
        CoverStorageInterface $coverStorage,
        ClockInterface $clock
    ) {
        $this->bookRepository = $bookRepository;
        $this->categoryRepository = $categoryRepository;
        $this->coverStorage = $coverStorage;
        $this->clock = $clock;
    }

    public function create(
        string $title,
        string $author,
        string $isbnString,
        string $categoryIdString,
        int $totalCopiesInt,
        Role $role
    ): array {
        if (!$role->isAdmin()) {
            throw new ForbiddenRoleException();
        }

        $trimmedTitle = trim($title);
        $trimmedAuthor = trim($author);
        if ($trimmedTitle === '') {
            throw new ValidationException('El título no puede estar vacío.', [['field' => 'title', 'issue' => 'requerido']]);
        }
        if ($trimmedAuthor === '') {
            throw new ValidationException('El autor no puede estar vacío.', [['field' => 'author', 'issue' => 'requerido']]);
        }

        $isbn = new Isbn($isbnString);
        $catUuid = new Uuid($categoryIdString);
        $category = $this->categoryRepository->findById($catUuid);
        if ($category === null) {
            throw new ValidationException('La categoría indicada no existe.', [['field' => 'category_id', 'issue' => 'no existe']]);
        }

        $quantity = new Quantity($totalCopiesInt);

        $existing = $this->bookRepository->findByIsbn($isbn);
        if ($existing !== null && !$existing->isDeleted()) {
            throw new IsbnTakenException();
        }

        $book = new Book(
            Uuid::generate(),
            $trimmedTitle,
            $trimmedAuthor,
            $isbn,
            $catUuid,
            $quantity,
            $quantity->value(),
            null,
            null
        );

        $this->bookRepository->save($book);

        return $book->toArray($category->toArray());
    }

    public function update(
        string $idString,
        string $title,
        string $author,
        string $categoryIdString,
        int $totalCopiesInt,
        Role $role
    ): array {
        if (!$role->isAdmin()) {
            throw new ForbiddenRoleException();
        }

        $book = $this->findActiveBook($idString);
        $catUuid = new Uuid($categoryIdString);
        $category = $this->categoryRepository->findById($catUuid);
        if ($category === null) {
            throw new ValidationException('La categoría indicada no existe.', [['field' => 'category_id', 'issue' => 'no existe']]);
        }

        $quantity = new Quantity($totalCopiesInt);
        $book->updateDetails($title, $author, $catUuid, $quantity);
        $this->bookRepository->save($book);

        return $book->toArray($category->toArray());
    }

    public function softDelete(string $idString, Role $role): void
    {
        if (!$role->isAdmin()) {
            throw new ForbiddenRoleException();
        }

        $book = $this->findActiveBook($idString);

        if ($this->bookRepository->hasOpenLoans($book->id())) {
            throw new BookHasOpenLoansException();
        }

        $book->softDelete($this->clock->now());
        $this->bookRepository->save($book);
    }

    public function get(string $idString): array
    {
        $book = $this->findActiveBook($idString);
        $category = $this->categoryRepository->findById($book->categoryId());
        return $book->toArray($category ? $category->toArray() : null);
    }

    public function search(?string $query, ?string $categoryIdString, int $page, int $size): array
    {
        $clampedSize = max(1, min(50, $size));
        $clampedPage = max(1, $page);

        $catUuid = $categoryIdString ? new Uuid($categoryIdString) : null;
        $result = $this->bookRepository->search($query, $catUuid, $clampedPage, $clampedSize);

        $items = [];
        foreach ($result['items'] as $book) {
            $cat = $this->categoryRepository->findById($book->categoryId());
            $items[] = $book->toArray($cat ? $cat->toArray() : null);
        }

        return [
            'items' => $items,
            'page' => $clampedPage,
            'size' => $clampedSize,
            'total' => $result['total'],
        ];
    }

    public function uploadCover(string $idString, string $contents, string $mimeType, Role $role): void
    {
        if (!$role->isAdmin()) {
            throw new ForbiddenRoleException();
        }

        $book = $this->findActiveBook($idString);

        if (strlen($contents) > 2 * 1024 * 1024) {
            throw new ValidationException('La imagen de la portada no puede superar 2 MB.', [
                ['field' => 'file', 'issue' => 'tamaño máximo 2 MB']
            ]);
        }

        $allowedMimes = ['image/jpeg', 'image/png'];
        if (!in_array(strtolower($mimeType), $allowedMimes, true)) {
            throw new ValidationException('Formato de portada no permitido. Solo se admite JPG y PNG.', [
                ['field' => 'file', 'issue' => 'debe ser imagen JPG o PNG']
            ]);
        }

        if ($book->coverKey()) {
            $this->coverStorage->delete($book->coverKey());
        }

        $key = $this->coverStorage->store($contents, $mimeType);
        $book->setCoverKey($key);
        $this->bookRepository->save($book);
    }

    public function deleteCover(string $idString, Role $role): void
    {
        if (!$role->isAdmin()) {
            throw new ForbiddenRoleException();
        }

        $book = $this->findActiveBook($idString);
        if ($book->coverKey()) {
            $key = $book->coverKey();
            $book->setCoverKey(null);
            $this->bookRepository->save($book);
            $this->coverStorage->delete($key);
        }
    }

    public function getCover(string $idString): array
    {
        $book = $this->findActiveBook($idString);
        if (!$book->coverKey()) {
            throw new NotFoundException('El libro no tiene portada registrada.');
        }

        $file = $this->coverStorage->get($book->coverKey());
        if ($file === null) {
            throw new NotFoundException('El archivo de portada no fue encontrado.');
        }

        return $file;
    }

    private function findActiveBook(string $idString): Book
    {
        $uuid = new Uuid($idString);
        $book = $this->bookRepository->findById($uuid);
        if ($book === null || $book->isDeleted()) {
            throw new NotFoundException('Libro no encontrado o dado de baja.');
        }
        return $book;
    }
}
