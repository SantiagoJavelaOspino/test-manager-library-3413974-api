<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Port\CoverStorageInterface;

final class DiskCoverStorage implements CoverStorageInterface
{
    private string $storageDir;

    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim($storageDir, '/\\');
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function store(string $contents, string $mimeType): string
    {
        $key = bin2hex(random_bytes(16)) . '_' . hash('sha256', $contents);
        $ext = ($mimeType === 'image/png') ? 'png' : 'jpg';
        $filePath = "{$this->storageDir}/{$key}.{$ext}";
        file_put_contents($filePath, $contents);
        return "{$key}.{$ext}";
    }

    public function get(string $key): ?array
    {
        $filePath = "{$this->storageDir}/" . basename($key);
        if (!file_exists($filePath)) {
            return null;
        }
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        return [
            'contents' => $contents,
            'mime_type' => $mime,
        ];
    }

    public function delete(string $key): void
    {
        $filePath = "{$this->storageDir}/" . basename($key);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
