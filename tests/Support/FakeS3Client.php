<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests\Support;

use PhpSoftBox\Storage\Contracts\S3ClientInterface;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function array_slice;
use function count;
use function explode;
use function is_string;
use function ksort;
use function rawurldecode;
use function str_starts_with;
use function strlen;

final class FakeS3Client implements S3ClientInterface
{
    /** @var list<array{method: string, args: mixed}> */
    public array $calls = [];

    /** @var array<string, string> */
    public array $objects = [];

    /** @var array<string, array<string, mixed>> Параметры PutObject (кроме Bucket/Key/Body) по ключу. */
    public array $metadata = [];

    /** Размер страницы listObjectsV2 (в S3 — 1000). */
    public int $pageSize = 1000;

    /** Исключение, которое бросит headObject (эмуляция 403, сетевой ошибки). */
    public ?Throwable $headException = null;

    public function putObject(array $args): void
    {
        $this->calls[] = ['method' => 'putObject', 'args' => $args];

        $key  = $args['Key'] ?? null;
        $body = $args['Body'] ?? '';

        if (!is_string($key)) {
            throw new RuntimeException('Missing Key for putObject.');
        }

        unset($args['Bucket'], $args['Key'], $args['Body']);

        $this->objects[$key]  = (string) $body;
        $this->metadata[$key] = $args;
    }

    public function getObject(array $args): array
    {
        $this->calls[] = ['method' => 'getObject', 'args' => $args];

        $key = $args['Key'] ?? null;
        if (!is_string($key) || !array_key_exists($key, $this->objects)) {
            throw new RuntimeException('Object not found.');
        }

        return ['Body' => $this->objects[$key]];
    }

    public function deleteObject(array $args): void
    {
        $this->calls[] = ['method' => 'deleteObject', 'args' => $args];

        $key = $args['Key'] ?? null;
        if (is_string($key)) {
            unset($this->objects[$key], $this->metadata[$key]);
        }
    }

    public function headObject(array $args): ?array
    {
        $this->calls[] = ['method' => 'headObject', 'args' => $args];

        if ($this->headException !== null) {
            throw $this->headException;
        }

        $key = $args['Key'] ?? null;
        if (!is_string($key) || !array_key_exists($key, $this->objects)) {
            return null;
        }

        return ['ContentLength' => strlen($this->objects[$key])];
    }

    public function listObjectsV2(array $args): array
    {
        $this->calls[] = ['method' => 'listObjectsV2', 'args' => $args];

        $prefix = (string) ($args['Prefix'] ?? '');
        $offset = (int) ($args['ContinuationToken'] ?? 0);

        $objects = $this->objects;
        ksort($objects);

        $matched = [];
        foreach ($objects as $key => $_value) {
            if ($prefix === '' || str_starts_with((string) $key, $prefix)) {
                $matched[] = ['Key' => (string) $key];
            }
        }

        $page      = array_slice($matched, $offset, $this->pageSize);
        $truncated = $offset + $this->pageSize < count($matched);

        $result = ['Contents' => $page, 'IsTruncated' => $truncated];
        if ($truncated) {
            $result['NextContinuationToken'] = (string) ($offset + $this->pageSize);
        }

        return $result;
    }

    public function copyObject(array $args): void
    {
        $this->calls[] = ['method' => 'copyObject', 'args' => $args];

        [, $encodedKey] = explode('/', (string) $args['CopySource'], 2);
        $sourceKey      = rawurldecode($encodedKey);

        if (!array_key_exists($sourceKey, $this->objects)) {
            throw new RuntimeException('Source object not found.');
        }

        $targetKey                  = (string) $args['Key'];
        $this->objects[$targetKey]  = $this->objects[$sourceKey];
        $this->metadata[$targetKey] = $this->metadata[$sourceKey] ?? [];
    }
}
