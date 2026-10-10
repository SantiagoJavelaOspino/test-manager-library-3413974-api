<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Infrastructure\Database\DatabaseConnection;

$pdo = DatabaseConnection::get();

echo "Ejecutando migraciones para Manager Library en PostgreSQL...\n";

// 1. Crear esquema library
$pdo->exec("CREATE SCHEMA IF NOT EXISTS library;");
$pdo->exec("SET search_path TO library, public;");

// 2. Tabla category
$pdo->exec("
    CREATE TABLE IF NOT EXISTS category (
        id UUID PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        CONSTRAINT uq_category_name UNIQUE (name)
    );
");

// 3. Tabla user
$pdo->exec("
    CREATE TABLE IF NOT EXISTS \"user\" (
        id UUID PRIMARY KEY,
        username VARCHAR(120) NOT NULL,
        password_hash VARCHAR(512) NOT NULL,
        role VARCHAR(40) NOT NULL,
        CONSTRAINT uq_user_username UNIQUE (username),
        CONSTRAINT ck_user_role CHECK (role IN ('admin', 'librarian'))
    );
");

// 4. Tabla book
$pdo->exec("
    CREATE TABLE IF NOT EXISTS book (
        id UUID PRIMARY KEY,
        title VARCHAR(250) NOT NULL,
        author VARCHAR(200) NOT NULL,
        isbn CHAR(13) NOT NULL,
        category_id UUID NOT NULL REFERENCES category(id) ON DELETE RESTRICT,
        total_copies INTEGER NOT NULL,
        available_copies INTEGER NOT NULL,
        cover_key VARCHAR(512) NULL,
        deleted_at TIMESTAMPTZ NULL,
        CONSTRAINT ck_book_available_range CHECK (0 <= available_copies AND available_copies <= total_copies),
        CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1)
    );
");

$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_book_isbn_active ON book (isbn) WHERE deleted_at IS NULL;");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_book_category_title ON book (category_id, title) WHERE deleted_at IS NULL;");

// 5. Tabla member
$pdo->exec("
    CREATE TABLE IF NOT EXISTS member (
        id UUID PRIMARY KEY,
        full_name VARCHAR(200) NOT NULL,
        document_number VARCHAR(30) NOT NULL,
        CONSTRAINT uq_member_document UNIQUE (document_number)
    );
");

// 6. Tabla loan
$pdo->exec("
    CREATE TABLE IF NOT EXISTS loan (
        id UUID PRIMARY KEY,
        member_id UUID NOT NULL REFERENCES member(id) ON DELETE RESTRICT,
        loaned_by_user_id UUID NOT NULL REFERENCES \"user\"(id) ON DELETE RESTRICT,
        loaned_at TIMESTAMPTZ NOT NULL
    );
");

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_loan_loaned_at ON loan (loaned_at);");

// 7. Tabla loan_item
$pdo->exec("
    CREATE TABLE IF NOT EXISTS loan_item (
        id UUID PRIMARY KEY,
        loan_id UUID NOT NULL REFERENCES loan(id) ON DELETE CASCADE,
        book_id UUID NOT NULL REFERENCES book(id) ON DELETE RESTRICT,
        book_title VARCHAR(250) NOT NULL,
        isbn CHAR(13) NOT NULL,
        category_name VARCHAR(120) NOT NULL,
        due_at TIMESTAMPTZ NOT NULL,
        returned_at TIMESTAMPTZ NULL
    );
");

$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_loan_item_loan_book ON loan_item (loan_id, book_id);");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_loan_item_book ON loan_item (book_id);");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_loan_item_overdue ON loan_item (due_at) WHERE returned_at IS NULL;");

// 8. Sembrar las cinco categorías fijas (§9)
$categories = [
    ['11111111-1111-4111-8111-111111111111', 'Novela'],
    ['22222222-2222-4222-8222-222222222222', 'Ciencia'],
    ['33333333-3333-4333-8333-333333333333', 'Tecnología'],
    ['44444444-4444-4444-8444-444444444444', 'Historia'],
    ['55555555-5555-4555-8555-555555555555', 'Infantil'],
];

$stmt = $pdo->prepare("
    INSERT INTO category (id, name)
    VALUES (:id, :name)
    ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name;
");

foreach ($categories as [$id, $name]) {
    $stmt->execute(['id' => $id, 'name' => $name]);
}

// 9. Provisionar admin inicial desde variables de entorno si no existe (§1 y CA-01)
$adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD') ?: 'admin123';

$checkUser = $pdo->prepare("SELECT id FROM \"user\" WHERE username = :username");
$checkUser->execute(['username' => $adminUser]);
if (!$checkUser->fetch()) {
    $adminUuid = '00000000-0000-4000-8000-000000000001';
    $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
    $insertUser = $pdo->prepare("
        INSERT INTO \"user\" (id, username, password_hash, role)
        VALUES (:id, :username, :hash, 'admin')
        ON CONFLICT (username) DO NOTHING;
    ");
    $insertUser->execute([
        'id' => $adminUuid,
        'username' => $adminUser,
        'hash' => $adminHash,
    ]);
    echo "Usuario admin inicial provisionado con username: {$adminUser}\n";
}

echo "Migraciones y semillas completadas exitosamente.\n";
