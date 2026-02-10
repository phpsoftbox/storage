<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Contracts;

use Psr\Http\Message\ResponseInterface;

interface StorageInterface
{
    public function get(string $path): string;

    public function read(string $path): string;

    public function put(string $path, string $contents, array $options = []): void;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function missing(string $path): bool;

    /**
     * @return list<string>
     */
    public function list(string $prefix = ''): array;

    public function copy(string $sourcePath, string $targetPath): void;

    public function move(string $sourcePath, string $targetPath): void;

    public function rename(string $path, string $newName): void;

    public function url(string $path): string;

    public function download(string $path, ?string $name = null): ResponseInterface;
}
