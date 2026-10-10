<?php

declare(strict_types=1);

namespace App\Application\Port;

interface CoverStorageInterface
{
    public function store(string $contents, string $mimeType): string;
    /**
     * @return array{contents: string, mime_type: string}|null
     */
    public function get(string $key): ?array;
    public function delete(string $key): void;
}
