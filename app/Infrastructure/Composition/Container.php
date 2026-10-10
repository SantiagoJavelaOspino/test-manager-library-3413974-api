<?php

declare(strict_types=1);

namespace App\Infrastructure\Composition;

use App\Application\Service\BookService;
use App\Application\Service\CreateLibrarianService;
use App\Application\Service\LendBooksService;
use App\Application\Service\ListCategoriesService;
use App\Application\Service\LoanQueryService;
use App\Application\Service\LoginService;
use App\Application\Service\MemberService;
use App\Application\Service\ReportService;
use App\Application\Service\ReturnItemService;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Persistence\PgBookRepository;
use App\Infrastructure\Persistence\PgCategoryRepository;
use App\Infrastructure\Persistence\PgLoanRepository;
use App\Infrastructure\Persistence\PgMemberRepository;
use App\Infrastructure\Persistence\PgReportReader;
use App\Infrastructure\Persistence\PgUserRepository;
use App\Infrastructure\Security\BcryptPasswordHasher;
use App\Infrastructure\Security\NativeJwtTokenIssuer;
use App\Infrastructure\Storage\DiskCoverStorage;
use PDO;

final class Container
{
    private static ?self $instance = null;

    public SystemClock $clock;
    public BcryptPasswordHasher $passwordHasher;
    public NativeJwtTokenIssuer $tokenIssuer;
    public DiskCoverStorage $coverStorage;

    public PgCategoryRepository $categoryRepository;
    public PgUserRepository $userRepository;
    public PgMemberRepository $memberRepository;
    public PgBookRepository $bookRepository;
    public PgLoanRepository $loanRepository;
    public PgReportReader $reportReader;

    public LoginService $loginService;
    public CreateLibrarianService $createLibrarianService;
    public ListCategoriesService $listCategoriesService;
    public BookService $bookService;
    public MemberService $memberService;
    public LendBooksService $lendBooksService;
    public ReturnItemService $returnItemService;
    public LoanQueryService $loanQueryService;
    public ReportService $reportService;

    public function __construct(?PDO $customPdo = null)
    {
        $pdo = $customPdo ?? DatabaseConnection::get();

        $this->clock = new SystemClock();
        $this->passwordHasher = new BcryptPasswordHasher();
        $jwtSecret = getenv('JWT_SECRET') ?: 'manager_library_super_secret_jwt_key_adso_3413974';
        $this->tokenIssuer = new NativeJwtTokenIssuer($jwtSecret, 28800);

        $storageDir = __DIR__ . '/../../../storage/covers';
        $this->coverStorage = new DiskCoverStorage($storageDir);

        $this->categoryRepository = new PgCategoryRepository($pdo);
        $this->userRepository = new PgUserRepository($pdo);
        $this->memberRepository = new PgMemberRepository($pdo);
        $this->bookRepository = new PgBookRepository($pdo);
        $this->loanRepository = new PgLoanRepository($pdo);
        $this->reportReader = new PgReportReader($pdo);

        $this->loginService = new LoginService(
            $this->userRepository,
            $this->passwordHasher,
            $this->tokenIssuer
        );

        $this->createLibrarianService = new CreateLibrarianService(
            $this->userRepository,
            $this->passwordHasher
        );

        $this->listCategoriesService = new ListCategoriesService($this->categoryRepository);

        $this->bookService = new BookService(
            $this->bookRepository,
            $this->categoryRepository,
            $this->coverStorage,
            $this->clock
        );

        $this->memberService = new MemberService($this->memberRepository);

        $this->lendBooksService = new LendBooksService(
            $this->loanRepository,
            $this->bookRepository,
            $this->memberRepository,
            $this->categoryRepository,
            $this->clock
        );

        $this->returnItemService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );

        $this->loanQueryService = new LoanQueryService(
            $this->loanRepository,
            $this->clock
        );

        $this->reportService = new ReportService($this->reportReader);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }
}
