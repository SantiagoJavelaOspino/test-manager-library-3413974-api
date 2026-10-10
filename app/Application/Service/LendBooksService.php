<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\BookRepositoryInterface;
use App\Application\Port\CategoryRepositoryInterface;
use App\Application\Port\ClockInterface;
use App\Application\Port\LoanRepositoryInterface;
use App\Application\Port\MemberRepositoryInterface;
use App\Domain\Exception\DuplicateBookInLoanException;
use App\Domain\Exception\MemberHasOverdueException;
use App\Domain\Exception\MemberLimitExceededException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Model\Loan;
use App\Domain\Model\LoanItem;
use App\Domain\ValueObject\Uuid;
use DateInterval;

final class LendBooksService
{
    private LoanRepositoryInterface $loanRepository;
    private BookRepositoryInterface $bookRepository;
    private MemberRepositoryInterface $memberRepository;
    private CategoryRepositoryInterface $categoryRepository;
    private ClockInterface $clock;

    public function __construct(
        LoanRepositoryInterface $loanRepository,
        BookRepositoryInterface $bookRepository,
        MemberRepositoryInterface $memberRepository,
        CategoryRepositoryInterface $categoryRepository,
        ClockInterface $clock
    ) {
        $this->loanRepository = $loanRepository;
        $this->bookRepository = $bookRepository;
        $this->memberRepository = $memberRepository;
        $this->categoryRepository = $categoryRepository;
        $this->clock = $clock;
    }

    /**
     * @param array<int, array{book_id: string}> $itemsInput
     */
    public function execute(string $memberIdString, string $loanedByUserIdString, array $itemsInput): array
    {
        $itemCount = count($itemsInput);
        if ($itemCount < 1 || $itemCount > 3) {
            throw new ValidationException('El préstamo debe contener entre 1 y 3 libros.', [
                ['field' => 'items', 'issue' => 'debe contener entre 1 y 3 libros']
            ]);
        }

        // Check duplicate books within the request
        $seenBookIds = [];
        foreach ($itemsInput as $item) {
            if (!isset($item['book_id']) || trim($item['book_id']) === '') {
                throw new ValidationException('Cada elemento de préstamo debe indicar un book_id.', [
                    ['field' => 'items.book_id', 'issue' => 'requerido']
                ]);
            }
            $bId = strtolower(trim($item['book_id']));
            if (isset($seenBookIds[$bId])) {
                throw new DuplicateBookInLoanException();
            }
            $seenBookIds[$bId] = true;
        }

        $memberUuid = new Uuid($memberIdString);
        $member = $this->memberRepository->findById($memberUuid);
        if ($member === null) {
            throw new NotFoundException('El socio indicado no existe.');
        }

        $now = $this->clock->now();

        // Check if member has overdue loans
        if ($this->memberRepository->hasOverdueLoans($memberUuid, $now)) {
            throw new MemberHasOverdueException();
        }

        // Check max 3 active loans limit
        $activeLoansCount = $this->memberRepository->countActiveLoans($memberUuid);
        if ($activeLoansCount + $itemCount > 3) {
            throw new MemberLimitExceededException($activeLoansCount);
        }

        // Check and prepare books
        $loanUuid = Uuid::generate();
        $dueAt = $now->add(new DateInterval('P14D'));
        $loanItems = [];
        $booksToUpdate = [];

        foreach ($itemsInput as $item) {
            $bookUuid = new Uuid($item['book_id']);
            $book = $this->bookRepository->findById($bookUuid);
            if ($book === null || $book->isDeleted()) {
                throw new NotFoundException("Libro con ID {$item['book_id']} no encontrado o dado de baja.");
            }

            // Decrement copy (throws BookNotAvailableException if available <= 0)
            $book->lendCopy();

            $category = $this->categoryRepository->findById($book->categoryId());
            $categoryName = $category ? $category->name() : 'Desconocida';

            $loanItem = new LoanItem(
                Uuid::generate(),
                $loanUuid,
                $book->id(),
                $book->title(),
                $book->isbn(),
                $categoryName,
                $dueAt,
                null
            );

            $loanItems[] = $loanItem;
            $booksToUpdate[] = $book;
        }

        // Persist all books and loan
        foreach ($booksToUpdate as $b) {
            $this->bookRepository->save($b);
        }

        $loan = new Loan(
            $loanUuid,
            $memberUuid,
            new Uuid($loanedByUserIdString),
            $now,
            $loanItems
        );

        $this->loanRepository->save($loan);

        return $loan->toArray($now);
    }
}
