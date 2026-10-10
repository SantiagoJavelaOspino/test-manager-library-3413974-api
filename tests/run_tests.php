<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Domain\Exception\BusinessException;
use App\Domain\Exception\DuplicateBookInLoanException;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\Exception\InvalidDateRangeException;
use App\Domain\Exception\ItemAlreadyReturnedException;
use App\Domain\Exception\MemberLimitExceededException;
use App\Domain\Exception\ValidationException;
use App\Domain\Model\Book;
use App\Domain\Model\Category;
use App\Domain\Model\Loan;
use App\Domain\Model\LoanItem;
use App\Domain\Model\Member;
use App\Domain\Model\User;
use App\Domain\ValueObject\DateRange;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\Username;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Composition\Container;
use App\Infrastructure\Database\DatabaseConnection;

$passed = 0;
$failed = 0;

function it(string $description, callable $test): void {
    global $passed, $failed;
    try {
        $test();
        echo "  [PASS] {$description}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$description}\n";
        echo "         -> " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
        $failed++;
    }
}

function assertEquals($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        throw new \Exception("Expected " . var_export($expected, true) . " but got " . var_export($actual, true) . ". {$msg}");
    }
}

function assertTrue($val, string $msg = ''): void {
    if (!$val) {
        throw new \Exception("Expected true but got false. {$msg}");
    }
}

echo "=========================================================\n";
echo "   MANAGER LIBRARY — SUITE DE PRUEBAS AUTOMATIZADAS     \n";
echo "=========================================================\n\n";

echo "--- T-03 / T-06: Value Objects y Dominio Core ---\n";

it("Isbn valida exactamente 13 dígitos numéricos", function() {
    $isbn = new Isbn("9780307474728");
    assertEquals("9780307474728", $isbn->value());

    $thrown = false;
    try {
        new Isbn("12345"); // Demasiado corto
    } catch (ValidationException) {
        $thrown = true;
    }
    assertTrue($thrown, "Debe rechazar ISBN con longitud != 13");

    $thrownAlpha = false;
    try {
        new Isbn("978030747472X"); // Carácter no numérico
    } catch (ValidationException) {
        $thrownAlpha = true;
    }
    assertTrue($thrownAlpha, "Debe rechazar ISBN con letras");
});

it("Quantity exige enteros positivos >= 1", function() {
    $q = new Quantity(5);
    assertEquals(5, $q->value());

    $thrown = false;
    try {
        new Quantity(0);
    } catch (ValidationException) {
        $thrown = true;
    }
    assertTrue($thrown, "Debe rechazar cantidad 0");
});

it("DateRange valida formato Y-m-d y fin >= inicio", function() {
    $range = new DateRange("2026-10-01", "2026-10-14");
    assertEquals("2026-10-01", $range->fromString());
    assertEquals("2026-10-14", $range->toString());

    $thrown = false;
    try {
        new DateRange("2026-10-15", "2026-10-01"); // Fin antes de inicio
    } catch (InvalidDateRangeException) {
        $thrown = true;
    }
    assertTrue($thrown, "Debe arrojar InvalidDateRangeException cuando to < from");
});

it("Username normaliza a minúsculas y recorta espacios", function() {
    $u = new Username("  SantiagoAdmin  ");
    assertEquals("santiagoadmin", $u->value());
});

it("Role solo acepta 'admin' y 'librarian'", function() {
    $r1 = new Role("admin");
    assertTrue($r1->isAdmin());

    $r2 = new Role("librarian");
    assertTrue($r2->isLibrarian());

    $thrown = false;
    try {
        new Role("guest");
    } catch (ValidationException) {
        $thrown = true;
    }
    assertTrue($thrown, "Debe rechazar roles desconocidos");
});

echo "\n--- T-04: Dominio Book (Invariantes de Stock y Baja Lógica) ---\n";

it("Book gestiona restock, préstamo y devolución garantizando 0 <= disponibles <= total", function() {
    $book = new Book(
        Uuid::generate(),
        "El Principito",
        "Antoine de Saint-Exupéry",
        new Isbn("9780156012195"),
        Uuid::generate(),
        new Quantity(3),
        3
    );

    assertEquals(3, $book->availableCopies());

    // Prestar 1
    $book->lendCopy();
    assertEquals(2, $book->availableCopies());

    // Prestar 2 más -> disponibles = 0
    $book->lendCopy();
    $book->lendCopy();
    assertEquals(0, $book->availableCopies());

    // Intentar prestar sin ejemplares
    $thrown = false;
    try {
        $book->lendCopy();
    } catch (BusinessException $e) {
        assertEquals("BOOK_NOT_AVAILABLE", $e->getErrorCode());
        $thrown = true;
    }
    assertTrue($thrown, "Prestar sin ejemplares disponibles debe fallar con BOOK_NOT_AVAILABLE");

    // Devolver
    $book->returnCopy();
    assertEquals(1, $book->availableCopies());

    // Ajustar total a 4
    $book->restock(new Quantity(4));
    assertEquals(4, $book->totalCopies());
    assertEquals(2, $book->availableCopies());
});

echo "\n--- T-05: Dominio Loan (Estado Calculado y Devolución) ---\n";

it("Loan calcula dinámicamente open, overdue y closed en función de la fecha", function() {
    $now = new DateTimeImmutable("2026-10-10 10:00:00Z");
    $loanId = Uuid::generate();

    $item1 = new LoanItem(
        Uuid::generate(),
        $loanId,
        Uuid::generate(),
        "Libro A",
        new Isbn("9780000000001"),
        "Novela",
        new DateTimeImmutable("2026-10-24 10:00:00Z"), // Vence en 14 días
        null
    );

    $loan = new Loan(
        $loanId,
        Uuid::generate(),
        Uuid::generate(),
        $now,
        [$item1]
    );

    // Estado hoy: open
    assertEquals("open", $loan->status($now));

    // Estado después de vencer: overdue
    $future = new DateTimeImmutable("2026-10-25 10:00:00Z");
    assertEquals("overdue", $loan->status($future));

    // Devolver la línea
    $item1->return($now);
    assertTrue($item1->isReturned());

    // Estado tras devolución: closed
    assertEquals("closed", $loan->status($future));

    // Intentar devolver de nuevo debe lanzar ITEM_ALREADY_RETURNED
    $thrown = false;
    try {
        $item1->return($now);
    } catch (ItemAlreadyReturnedException) {
        $thrown = true;
    }
    assertTrue($thrown, "Devolver una línea ya devuelta debe arrojar ITEM_ALREADY_RETURNED");
});

echo "\n--- T-07 a T-11: Servicios de Aplicación y Persistencia PostgreSQL ---\n";

$container = Container::instance();

it("HU-01: Login exitoso y rechazo de credenciales inválidas", function() use ($container) {
    // Login con admin correcto
    $auth = $container->loginService->execute("admin", "admin123");
    assertEquals("Bearer", $auth['token_type']);
    assertEquals("admin", $auth['role']);
    assertTrue(strlen($auth['access_token']) > 20);

    // Login con clave errónea (401 genérico)
    $thrown = false;
    try {
        $container->loginService->execute("admin", "clave_incorrecta");
    } catch (InvalidCredentialsException $e) {
        assertEquals("INVALID_CREDENTIALS", $e->getErrorCode());
        assertEquals(401, $e->getHttpStatus());
        $thrown = true;
    }
    assertTrue($thrown, "Debe rechazar clave errónea con 401 INVALID_CREDENTIALS");
});

it("HU-02: Creación de bibliotecario y rechazo de rol admin por API", function() use ($container) {
    $adminRole = new Role(Role::ADMIN);
    $librarianRole = new Role(Role::LIBRARIAN);

    // Intentar crear un admin por la API (debe fallar 422)
    $thrown = false;
    try {
        $container->createLibrarianService->execute("otro_admin", "secreto123", "admin", $adminRole);
    } catch (ValidationException $e) {
        assertEquals(422, $e->getHttpStatus());
        $thrown = true;
    }
    assertTrue($thrown, "Crear admin por API debe fallar");

    // Crear bibliotecario
    $uniqueUser = "biblio_" . substr(bin2hex(random_bytes(4)), 0, 6);
    $created = $container->createLibrarianService->execute("  {$uniqueUser}  ", "password123", "librarian", $adminRole);
    assertEquals(strtolower($uniqueUser), $created['username']);
    assertEquals("librarian", $created['role']);
});

it("HU-03 & HU-04: Catálogo de libros, categorías y búsqueda", function() use ($container) {
    $adminRole = new Role(Role::ADMIN);
    $categories = $container->listCategoriesService->execute();
    assertTrue(count($categories) >= 5, "Deben existir las 5 categorías sembradas");

    $catId = $categories[0]['id'];
    $uniqueIsbn = "978" . str_pad((string)mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);

    // Crear libro
    $bookData = $container->bookService->create(
        "Cien Años de Soledad",
        "Gabriel García Márquez",
        $uniqueIsbn,
        $catId,
        5,
        $adminRole
    );
    assertEquals("Cien Años de Soledad", $bookData['title']);
    assertEquals(5, $bookData['total_copies']);
    assertEquals(5, $bookData['available_copies']);

    // Búsqueda
    $search = $container->bookService->search("Soledad", null, 1, 10);
    assertTrue($search['total'] >= 1);
});

it("HU-05 & HU-06: Préstamo con límite de 3 libros y congelación de datos", function() use ($container) {
    $adminRole = new Role(Role::ADMIN);
    $categories = $container->listCategoriesService->execute();
    $catId = $categories[0]['id'];

    // Crear socio
    $uniqueDoc = "DOC" . mt_rand(100000, 999999);
    $member = $container->memberService->create("Carlos Mendoza", $uniqueDoc);

    // Crear 2 libros
    $isbn1 = "978" . str_pad((string)mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    $isbn2 = "978" . str_pad((string)mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    $b1 = $container->bookService->create("Libro Test 1", "Autor 1", $isbn1, $catId, 2, $adminRole);
    $b2 = $container->bookService->create("Libro Test 2", "Autor 2", $isbn2, $catId, 2, $adminRole);

    $adminUser = $container->userRepository->findByUsername(new Username("admin"));

    // Prestar 2 libros
    $loan = $container->lendBooksService->execute(
        $member['id'],
        $adminUser->id()->value(),
        [['book_id' => $b1['id']], ['book_id' => $b2['id']]]
    );
    assertEquals("open", $loan['status']);
    assertEquals(2, count($loan['items']));
    assertEquals("Libro Test 1", $loan['items'][0]['book_title']);

    // Verificar descuento en stock del libro 1
    $bookReloaded = $container->bookService->get($b1['id']);
    assertEquals(1, $bookReloaded['available_copies']);

    // Intentar pedir 2 libros más para el mismo socio (2 + 2 = 4 > 3 -> debe arrojar 422 MEMBER_LIMIT_EXCEEDED)
    $isbn3 = "978" . str_pad((string)mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    $b3 = $container->bookService->create("Libro Test 3", "Autor 3", $isbn3, $catId, 2, $adminRole);

    $thrownLimit = false;
    try {
        $container->lendBooksService->execute(
            $member['id'],
            $adminUser->id()->value(),
            [['book_id' => $b3['id']], ['book_id' => $b1['id']]]
        );
    } catch (MemberLimitExceededException $e) {
        assertEquals("MEMBER_LIMIT_EXCEEDED", $e->getErrorCode());
        $thrownLimit = true;
    }
    assertTrue($thrownLimit, "Superar 3 libros activos debe lanzar MEMBER_LIMIT_EXCEEDED");
});

it("HU-07 & HU-10: Devolución de ítem y reporte inmutable de más prestados (ADR-004)", function() use ($container) {
    $adminRole = new Role(Role::ADMIN);
    $categories = $container->listCategoriesService->execute();
    $catId = $categories[0]['id'];

    $uniqueDoc = "DOC" . mt_rand(100000, 999999);
    $member = $container->memberService->create("Lucía Gómez", $uniqueDoc);

    $isbn = "978" . str_pad((string)mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    $b = $container->bookService->create("Título Original 1984", "George Orwell", $isbn, $catId, 5, $adminRole);
    $adminUser = $container->userRepository->findByUsername(new Username("admin"));

    $loan = $container->lendBooksService->execute(
        $member['id'],
        $adminUser->id()->value(),
        [['book_id' => $b['id']]]
    );

    $itemId = $loan['items'][0]['id'];

    // Renombrar el libro en catálogo después de prestarlo
    $container->bookService->update($b['id'], "Título Modificado Nuevo", "George Orwell", $catId, 5, $adminRole);

    // Consultar el reporte: debe conservar el título original congelado (ADR-004)
    $today = date('Y-m-d');
    $report = $container->reportService->execute($today, $today, $adminRole);

    $found = false;
    foreach ($report['rows'] as $row) {
        if ($row['book_id'] === $b['id']) {
            assertEquals("Título Original 1984", $row['book_title'], "El reporte debe mantener el título congelado!");
            $found = true;
            break;
        }
    }
    assertTrue($found, "El libro prestado debe aparecer en el reporte");

    // Devolver el libro
    $returned = $container->returnItemService->execute($loan['id'], $itemId);
    assertTrue($returned['returned_at'] !== null);

    // Stock restablecido
    $bRestored = $container->bookService->get($b['id']);
    assertEquals(5, $bRestored['available_copies']);
});

echo "\n--- T-19: Verificación de Arquitectura (Artículos I, II y III) ---\n";

it("Artículo I: Archivos de App\\Domain no contienen dependencias externas ni de otras capas", function() {
    $domainDir = __DIR__ . '/../app/Domain';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domainDir));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            assertTrue(!str_contains($content, 'Illuminate\\'), "Dominio no debe importar Illuminate");
            assertTrue(!str_contains($content, 'App\\Infrastructure'), "Dominio no debe importar Infrastructure");
            assertTrue(!str_contains($content, 'App\\Application'), "Dominio no debe importar Application");
            assertTrue(!str_contains($content, 'App\\Http'), "Dominio no debe importar Http");
            assertTrue(!str_contains($content, 'PDO'), "Dominio no debe importar PDO");
        }
    }
});

it("Artículo II: Archivos de App\\Application no importan Infrastructure ni Frameworks", function() {
    $appDir = __DIR__ . '/../app/Application';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            assertTrue(!str_contains($content, 'App\\Infrastructure'), "Application no debe importar Infrastructure en {$file->getFilename()}");
            assertTrue(!str_contains($content, 'Illuminate\\'), "Application no debe importar Illuminate en {$file->getFilename()}");
            assertTrue(!str_contains($content, 'PDO'), "Application no debe importar PDO en {$file->getFilename()}");
        }
    }
});

echo "\n=========================================================\n";
echo " RESULTADO: {$passed} PASADAS, {$failed} FALLIDAS\n";
echo "=========================================================\n";

if ($failed > 0) {
    exit(1);
}
