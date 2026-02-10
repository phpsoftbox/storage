<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function copy;
use function dirname;
use function explode;
use function file_exists;
use function implode;
use function is_dir;
use function is_file;
use function ltrim;
use function mkdir;
use function rename;
use function rmdir;
use function rtrim;
use function str_contains;
use function str_replace;
use function trim;
use function unlink;

final class FileHelper
{
    private function __construct()
    {
    }

    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '') {
            throw new StorageException('Path must be non-empty.');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' || str_contains($segment, '..')) {
                throw new StorageException('Path traversal is not allowed.');
            }
            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new StorageException('Path must be non-empty.');
        }

        return implode('/', $segments);
    }

    public static function directory(string $path): string
    {
        $path = self::normalizePath($path);
        $dir  = rtrim(dirname($path), '/');

        return $dir === '.' ? '' : $dir;
    }

    /**
     * @return list<string>
     */
    public static function directories(string $path): array
    {
        $dir = self::directory($path);
        if ($dir === '') {
            return [];
        }

        return explode('/', $dir);
    }

    public static function createDirectory(string $path, int $mode = 0775, bool $recursive = true): void
    {
        if ($path === '') {
            throw new RuntimeException('Directory path must be non-empty.');
        }

        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, $mode, $recursive) && !is_dir($path)) {
            throw new StorageException('Failed to create directory.', null, ['path' => $path]);
        }
    }

    public static function ensureDirectory(string $path, int $mode = 0775): void
    {
        self::createDirectory($path, $mode, true);
    }

    public static function ensureDirectoryForFile(string $path, int $mode = 0775): void
    {
        $dir = dirname($path);
        if ($dir === '' || $dir === '.') {
            return;
        }

        self::ensureDirectory($dir, $mode);
    }

    public static function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $info) {
            if (!$info instanceof SplFileInfo) {
                continue;
            }

            if ($info->isDir()) {
                if (!rmdir($info->getPathname())) {
                    throw new StorageException('Failed to delete directory.', null, ['path' => $info->getPathname()]);
                }
                continue;
            }

            if (!unlink($info->getPathname())) {
                throw new StorageException('Failed to delete file.', null, ['path' => $info->getPathname()]);
            }
        }

        if (!rmdir($path)) {
            throw new StorageException('Failed to delete directory.', null, ['path' => $path]);
        }
    }

    public static function deleteFile(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (!unlink($path)) {
            throw new StorageException('Failed to delete file.', null, ['path' => $path]);
        }
    }

    public static function copyFile(string $source, string $target, int $mode = 0775): void
    {
        if (!is_file($source)) {
            throw new StorageException('Source file not found for copy.', null, ['path' => $source]);
        }

        self::ensureDirectoryForFile($target, $mode);

        if (!copy($source, $target)) {
            throw new StorageException('Failed to copy file.', null, [
                'source' => $source,
                'target' => $target,
            ]);
        }
    }

    public static function moveFile(string $source, string $target, int $mode = 0775): void
    {
        if (!is_file($source)) {
            throw new StorageException('Source file not found for move.', null, ['path' => $source]);
        }

        self::ensureDirectoryForFile($target, $mode);

        if (rename($source, $target)) {
            return;
        }

        if (!copy($source, $target)) {
            throw new StorageException('Failed to move file.', null, [
                'source' => $source,
                'target' => $target,
            ]);
        }

        if (!unlink($source)) {
            throw new StorageException('Failed to cleanup source file after move.', null, ['path' => $source]);
        }
    }

    public static function renameFile(string $path, string $newName, int $mode = 0775): void
    {
        $newName = self::normalizePath($newName);
        $dir     = rtrim(dirname($path), '/');
        $target  = $dir === '' || $dir === '.' ? $newName : $dir . '/' . $newName;

        self::moveFile($path, $target, $mode);
    }
}
