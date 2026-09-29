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
use SplFileInfo;

use function array_diff;
use function array_keys;
use function array_values;
use function bin2hex;
use function chmod;
use function dirname;
use function fclose;
use function fflush;
use function file_exists;
use function file_get_contents;
use function fopen;
use function fwrite;
use function implode;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function ltrim;
use function mkdir;
use function preg_match;
use function random_bytes;
use function rename;
use function rtrim;
use function sort;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function umask;
use function unlink;

final class LocalStorage implements StorageInterface
{
    private const string TEMP_PREFIX  = '.psb-tmp-';
    private const string TEMP_PATTERN = '/^\.psb-tmp-[0-9a-f]{16}$/D';

    private string $rootPath;
    private ?string $baseUrl;

    /**
     * @param string $rootPath Абсолютный путь к корню диска (относительный зависел бы от cwd процесса).
     */
    public function __construct(string $rootPath, ?string $baseUrl = null)
    {
        $this->rootPath = self::normalizeRootPath($rootPath);
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
            throw new StorageException('Local storage requires an absolute rootPath.');
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

    /**
     * Атомарно записывает файл: данные пишутся во временный файл рядом с целевым и переносятся rename(),
     * поэтому читатель видит либо старую, либо новую версию целиком.
     *
     * Опции:
     * - `permissions` (int) — права файла; по умолчанию `0666 & ~umask()`, как у file_put_contents().
     * Другие ключи не поддерживаются и приводят к StorageException.
     *
     * @param array<string, mixed> $options
     */
    public function put(string $path, string $contents, array $options = []): void
    {
        $permissions = $this->resolvePermissions($options);
        $fullPath    = $this->resolvePath($path);
        $dir         = dirname($fullPath);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new StorageException('Failed to create directory for local storage.', null, ['path' => $dir]);
        }

        $tmpPath = $dir . '/' . self::TEMP_PREFIX . bin2hex(random_bytes(8));

        try {
            $this->writeTempFile($tmpPath, $contents, $permissions);

            if (!@rename($tmpPath, $fullPath)) {
                throw new StorageException('Failed to write file to local storage.', null, ['path' => $fullPath]);
            }
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
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

            if (preg_match(self::TEMP_PATTERN, $info->getFilename()) === 1) {
                // Незавершённая запись put().
                continue;
            }

            $relative = $this->relativePath($info->getPathname());
            if ($relative !== '') {
                $files[] = $relative;
            }
        }

        sort($files);

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

        return rtrim($baseUrl, '/') . '/' . FileHelper::encodeUrlPath(FileHelper::normalizePath($path));
    }

    public function download(string $path, ?string $name = null): ResponseInterface
    {
        $contents = $this->read($path);

        return DownloadResponseFactory::fromString($contents, $name ?? $path);
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function setRootPath(string $rootPath): void
    {
        $this->rootPath = self::normalizeRootPath($rootPath);
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): void
    {
        $this->baseUrl = $baseUrl !== null && $baseUrl !== '' ? rtrim($baseUrl, '/') : null;
    }

    private static function normalizeRootPath(string $rootPath): string
    {
        $normalized = rtrim($rootPath, '/\\');
        if ($normalized === '' && $rootPath !== '') {
            // Корень файловой системы.
            $normalized = '/';
        }

        if (!FileHelper::isAbsolutePath($normalized)) {
            throw new StorageException('Local storage root path must be absolute.', null, ['path' => $rootPath]);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolvePermissions(array $options): int
    {
        $unsupported = array_diff(array_keys($options), ['permissions']);
        if ($unsupported !== []) {
            throw new StorageException(
                'Unsupported local storage put option(s): ' . implode(', ', $unsupported) . '.',
                null,
                ['options' => array_values($unsupported)],
            );
        }

        $permissions = $options['permissions'] ?? (0666 & ~umask());
        if (!is_int($permissions) || $permissions < 0 || $permissions > 07777) {
            throw new StorageException('Local storage option "permissions" must be an integer file mode.');
        }

        return $permissions;
    }

    private function writeTempFile(string $tmpPath, string $contents, int $permissions): void
    {
        // Режим "x" не перезаписывает существующий файл и не следует по подложенной символической ссылке.
        $handle = @fopen($tmpPath, 'xb');
        if ($handle === false) {
            throw new StorageException('Failed to create temporary file in local storage.', null, ['path' => $tmpPath]);
        }

        try {
            $written = @fwrite($handle, $contents);
            if ($written !== strlen($contents) || !@fflush($handle)) {
                throw new StorageException('Failed to write file to local storage.', null, ['path' => $tmpPath]);
            }
        } finally {
            fclose($handle);
        }

        if (!@chmod($tmpPath, $permissions)) {
            throw new StorageException('Failed to set permissions for file in local storage.', null, ['path' => $tmpPath]);
        }
    }

    private function relativePath(string $absolutePath): string
    {
        $absolutePath = str_replace('\\', '/', $absolutePath);
        $root         = rtrim(str_replace('\\', '/', $this->rootPath), '/') . '/';

        if (str_starts_with($absolutePath, $root)) {
            return ltrim(substr($absolutePath, strlen($root)), '/');
        }

        return '';
    }

    private function resolvePath(string $path): string
    {
        return rtrim($this->rootPath, '/\\') . '/' . FileHelper::normalizePath($path);
    }
}
