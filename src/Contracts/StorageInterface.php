<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Contracts;

use PhpSoftBox\Storage\StorageException;
use Psr\Http\Message\ResponseInterface;

/**
 * Контракт диска.
 *
 * Пути — относительные внутри диска и нормализуются FileHelper::normalizePath() одинаково для всех драйверов:
 * `\` → `/`, ведущие `/`, пустые сегменты и `.` отбрасываются; сегмент `..`, NUL-байт и пустой путь —
 * StorageException. Ошибки операций — StorageException.
 */
interface StorageInterface
{
    /**
     * @throws StorageException
     */
    public function get(string $path): string;

    /**
     * @throws StorageException
     */
    public function read(string $path): string;

    /**
     * Записывает файл целиком. Набор `$options` зависит от драйвера (см. документацию драйвера);
     * неподдерживаемые опции не игнорируются молча.
     *
     * @param array<string, mixed> $options
     *
     * @throws StorageException
     */
    public function put(string $path, string $contents, array $options = []): void;

    /**
     * @throws StorageException
     */
    public function delete(string $path): void;

    /**
     * false — только если файла точно нет; ошибка доступа или сети — StorageException.
     *
     * @throws StorageException
     */
    public function exists(string $path): bool;

    /**
     * @throws StorageException
     */
    public function missing(string $path): bool;

    /**
     * Все файлы внутри каталога `$prefix` (рекурсивно), пути относительно корня диска.
     *
     * @return list<string>
     *
     * @throws StorageException
     */
    public function list(string $prefix = ''): array;

    /**
     * @throws StorageException
     */
    public function copy(string $sourcePath, string $targetPath): void;

    /**
     * @throws StorageException
     */
    public function move(string $sourcePath, string $targetPath): void;

    /**
     * @throws StorageException
     */
    public function rename(string $path, string $newName): void;

    /**
     * Публичный URL; сегменты пути кодируются rawurlencode().
     *
     * @throws StorageException
     */
    public function url(string $path): string;

    /**
     * Ответ `Content-Disposition: attachment` c ASCII `filename` и UTF-8 `filename*`.
     *
     * @throws StorageException
     */
    public function download(string $path, ?string $name = null): ResponseInterface;
}
