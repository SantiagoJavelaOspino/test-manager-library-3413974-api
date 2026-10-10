<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

final class DatabaseConnection
{
    private static ?PDO $pdo = null;

    public static function get(
        ?string $host = null,
        ?int $port = null,
        ?string $database = null,
        ?string $user = null,
        ?string $password = null
    ): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = $host ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = $port ?? (int)(getenv('DB_PORT') ?: 5432);
        $database = $database ?? getenv('DB_DATABASE') ?: 'manager_library';
        $user = $user ?? getenv('DB_USERNAME') ?: 'postgres';
        $password = $password ?? getenv('DB_PASSWORD') ?: '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        self::$pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::$pdo->exec("SET search_path TO library, public;");

        return self::$pdo;
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
