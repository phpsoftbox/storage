<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Contracts;

/**
 * Минимальный S3-клиент, который нужен S3Storage. Аргументы — как у одноимённых операций AWS SDK.
 */
interface S3ClientInterface
{
    /**
     * @param array<string, mixed> $args
     */
    public function putObject(array $args): void;

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function getObject(array $args): array;

    /**
     * @param array<string, mixed> $args
     */
    public function deleteObject(array $args): void;

    /**
     * Метаданные объекта или null, если объекта нет (HTTP 404).
     *
     * Любая другая ошибка (403, сетевая, 5xx) должна пробрасываться исключением.
     *
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>|null
     */
    public function headObject(array $args): ?array;

    /**
     * Одна страница листинга (до `MaxKeys` ключей); продолжение — через `ContinuationToken`.
     *
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function listObjectsV2(array $args): array;

    /**
     * Серверное копирование объекта (`CopySource` = `bucket/url-encoded-key`).
     *
     * @param array<string, mixed> $args
     */
    public function copyObject(array $args): void;
}
