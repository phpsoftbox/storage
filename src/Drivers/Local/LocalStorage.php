<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Drivers\Local;

use FilesystemIterator;
use PhpSoftBox\Storage\Contracts\StorageInterface;
use PhpSoftBox\Storage\DownloadResponseFactory;
use PhpSoftBox\Storage\FileHelper;
use PhpSoftBox\Storage\StorageException;
use Psr\Http\Message\ResponseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function basename;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_string;
use function ltrim;
use function mkdir;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function unlink;

final class LocalStorage implements StorageInterface
{
    private string $rootPath;
    private ?string $baseUrl;

    public function __construct(string $rootPath, ?string $baseUrl = null)
    {
        $rootPath = rtrim($rootPath, '/\\');
        if ($rootPath === '') {
            throw new RuntimeException('Local storage root path must be non-empty.');
        }

        $this->rootPath = $rootPath;
        $this->baseUrl  = $baseUrl !== null && $baseUrl !== '' ? rtrim($baseUrl, '/') : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config, ?string $defaultRoot = null): self
    {
        $root    = $config['rootPath'] ?? $config['root'] ?? $defaultRoot ?? '';
        $baseUrl = $config['baseUrl'] ?? $config['base_url'] ?? null;

        if (!is_string($root) || $root === '') {
            throw new StorageException('Local storage requires rootPath.');
        }

        return new self($root, is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : null);
    }

    public function get(string $path): string
    {
        return $this->read($path);
    }

    public function read(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw new StorageException('File not found in local storage.', null, ['path' => $fullPath]);
        }

        $contents = file_get_contents($fullPath);
        if ($contents === false) {
            throw new StorageException('Failed to read file from local storage.', null, ['path' => $fullPath]);
        }

        return $contents;
    }

    public function put(string $path, string $contents, array $options = []): void
    {
        $fullPath = $this->resolvePath($path);
        $dir      = dirname($fullPath);

        if ($dir !== '' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new StorageException('Failed to create directory for local storage.', null, ['path' => $dir]);
        }

        $result = file_put_contents($fullPath, $contents);
        if ($result === false) {
            throw new StorageException('Failed to write file to local storage.', null, ['path' => $fullPath]);
        }
    }

    public function delete(string $path): void
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            return;
        }

        if (!unlink($fullPath)) {
            throw new StorageException('Failed to delete file from local storage.', null, ['path' => $fullPath]);
        }
    }

    public function exists(string $path): bool
    {
        return is_file($this->resolvePath($path));
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function list(string $prefix = ''): array
    {
        $prefixPath = $prefix === '' ? $this->rootPath : $this->resolvePath($prefix);
        if (!is_dir($prefixPath)) {
            return [];
        }

        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($prefixPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $info) {
            if (!$info instanceof SplFileInfo || !$info->isFile()) {
                continue;
            }

            $relative = $this->relativePath($info->getPathname());
            if ($relative !== '') {
                $files[] = $relative;
            }
        }

        return $files;
    }

    public function copy(string $sourcePath, string $targetPath): void
    {
        $source = $this->resolvePath($sourcePath);
        $target = $this->resolvePath($targetPath);

        FileHelper::copyFile($source, $target);
    }

    public function move(string $sourcePath, string $targetPath): void
    {
        $source = $this->resolvePath($sourcePath);
        $target = $this->resolvePath($targetPath);

        FileHelper::moveFile($source, $target);
    }

    public function rename(string $path, string $newName): void
    {
        $fullPath = $this->resolvePath($path);
        FileHelper::renameFile($fullPath, $newName);
    }

    public function url(string $path): string
    {
        $baseUrl = $this->baseUrl ?? '/storage';
        $path    = ltrim(FileHelper::normalizePath($path), '/');

        return rtrim($baseUrl, '/') . '/' . $path;
    }

    public function download(string $path, ?string $name = null): ResponseInterface
    {
        $contents = $this->read($path);
        $filename = $name ?? basename($path);

        return DownloadResponseFactory::fromString($contents, $filename);
    }

    private function relativePath(string $absolutePath): string
    {
        $absolutePath = str_replace('\\', '/', $absolutePath);
        $root         = str_replace('\\', '/', $this->rootPath);

        if (str_starts_with($absolutePath, $root . '/')) {
            return ltrim(substr($absolutePath, strlen($root) + 1), '/');
        }

        return '';
    }

    private function resolvePath(string $path): string
    {
        return $this->rootPath . '/' . FileHelper::normalizePath($path);
    }
}
