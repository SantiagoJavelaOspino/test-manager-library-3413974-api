<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\BookRepositoryInterface;
use App\Application\Port\ClockInterface;
use App\Application\Port\LoanRepositoryInterface;
use App\Domain\Exception\ItemAlreadyReturnedException;
use App\Domain\Exception\NotFoundException;
use App\Domain\ValueObject\Uuid;

final class ReturnItemService
{
    private LoanRepositoryInterface $loanRepository;
    private BookRepositoryInterface $bookRepository;
    private ClockInterface $clock;

    public function __construct(
        LoanRepositoryInterface $loanRepository,
        BookRepositoryInterface $bookRepository,
        ClockInterface $clock
    ) {
        $this->loanRepository = $loanRepository;
        $this->bookRepository = $bookRepository;
        $this->clock = $clock;
    }

    public function execute(string $loanIdString, string $itemIdString): array
    {
        $loanUuid = new Uuid($loanIdString);
        $itemUuid = new Uuid($itemIdString);

        $item = $this->loanRepository->findItemById($loanUuid, $itemUuid);
        if ($item === null) {
            throw new NotFoundException('Línea de préstamo no encontrada.');
        }

        if ($item->isReturned()) {
            throw new ItemAlreadyReturnedException();
        }

        $now = $this->clock->now();
        $item->return($now);

        // Increment book available copies
        $book = $this->bookRepository->findById($item->bookId());
        if ($book !== null) {
            $book->returnCopy();
            $this->bookRepository->save($book);
        }

        $this->loanRepository->updateItem($item);

        return $item->toArray();
    }
}
