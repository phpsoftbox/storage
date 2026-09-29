<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Drivers\S3;

use Aws\S3\S3Client;
use PhpSoftBox\Storage\Contracts\S3ClientInterface;
use PhpSoftBox\Storage\Contracts\StorageInterface;
use PhpSoftBox\Storage\DownloadResponseFactory;
use PhpSoftBox\Storage\FileHelper;
use PhpSoftBox\Storage\StorageException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function class_exists;
use function is_array;
use function is_object;
use function is_resource;
use function is_string;
use function method_exists;
use function parse_url;
use function rtrim;
use function str_starts_with;
use function stream_get_contents;
use function strlen;
use function substr;
use function trim;

final class S3Storage implements StorageInterface
{
    private string $bucket;
    private string $prefix;
    private string $endpoint;
    private bool $usePathStyle;
    private ?string $baseUrl;

    public function __construct(
        private readonly S3ClientInterface $client,
        string $bucket,
        string $prefix = '',
        string $endpoint = 'https://storage.yandexcloud.net',
        bool $usePathStyle = true,
        ?string $baseUrl = null,
    ) {
        $this->bucket       = $bucket;
        $this->prefix       = self::normalizePrefix($prefix);
        $this->endpoint     = rtrim($endpoint, '/');
        $this->usePathStyle = $usePathStyle;
        $this->baseUrl      = $baseUrl !== null && $baseUrl !== '' ? rtrim($baseUrl, '/') : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        if (!class_exists(S3Client::class)) {
            throw new RuntimeException('aws/aws-sdk-php is required for S3Storage::fromConfig.');
        }

        $bucket = $config['bucket'] ?? null;
        $key    = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;

        if (!is_string($bucket) || $bucket === '') {
            throw new StorageException('Missing required "bucket" for S3 storage.');
        }

        if (!is_string($key) || $key === '') {
            throw new StorageException('Missing required "key" for S3 storage.');
        }

        if (!is_string($secret) || $secret === '') {
            throw new StorageException('Missing required "secret" for S3 storage.');
        }

        $endpoint     = $config['endpoint'] ?? 'https://storage.yandexcloud.net';
        $region       = $config['region'] ?? 'ru-central1';
        $prefix       = $config['prefix'] ?? '';
        $usePathStyle = $config['use_path_style_endpoint'] ?? true;
        $baseUrl      = $config['baseUrl'] ?? $config['base_url'] ?? null;

        $client = new S3Client([
            'version'                 => 'latest',
            'region'                  => $region,
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => (bool) $usePathStyle,
            'credentials'             => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);

        return new self(
            new AwsS3ClientAdapter($client),
            $bucket,
            is_string($prefix) ? $prefix : '',
            is_string($endpoint) ? $endpoint : 'https://storage.yandexcloud.net',
            (bool) $usePathStyle,
            is_string($baseUrl) ? $baseUrl : null,
        );
    }

    public function get(string $path): string
    {
        return $this->read($path);
    }

    public function read(string $path): string
    {
        $key = $this->buildKey($path);

        try {
            $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $key]);
            $body   = $result['Body'] ?? null;
        } catch (Throwable $exception) {
            throw new StorageException('Failed to download object from S3.', $exception, [
                'bucket' => $this->bucket,
                'key'    => $key,
            ]);
        }

        if ($body instanceof StreamInterface) {
            return (string) $body;
        }

        if (is_string($body)) {
            return $body;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);
            if ($contents !== false) {
                return $contents;
            }
        }

        if (is_object($body) && method_exists($body, '__toString')) {
            return (string) $body;
        }

        throw new StorageException('Unexpected S3 body type.', null, [
            'bucket' => $this->bucket,
            'key'    => $key,
        ]);
    }

    /**
     * Опции передаются в PutObject как есть (`ContentType`, `CacheControl`, `Metadata`, `ACL` и т.д.);
     * `Bucket`, `Key` и `Body` опциями переопределить нельзя.
     *
     * @param array<string, mixed> $options
     */
    public function put(string $path, string $contents, array $options = []): void
    {
        $key = $this->buildKey($path);

        try {
            $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $contents] + $options);
        } catch (Throwable $exception) {
            throw new StorageException('Failed to upload object to S3.', $exception, [
                'bucket' => $this->bucket,
                'key'    => $key,
            ]);
        }
    }

    public function delete(string $path): void
    {
        $key = $this->buildKey($path);

        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
        } catch (Throwable $exception) {
            throw new StorageException('Failed to delete object from S3.', $exception, [
                'bucket' => $this->bucket,
                'key'    => $key,
            ]);
        }
    }

    /**
     * false — только если S3 ответил 404; 403, сетевые и прочие ошибки — StorageException.
     */
    public function exists(string $path): bool
    {
        $key = $this->buildKey($path);

        try {
            return $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]) !== null;
        } catch (Throwable $exception) {
            throw new StorageException('Failed to check object existence in S3.', $exception, [
                'bucket' => $this->bucket,
                'key'    => $key,
            ]);
        }
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    /**
     * Все ключи внутри «каталога» `$prefix` (с учётом префикса диска), постранично через ContinuationToken.
     */
    public function list(string $prefix = ''): array
    {
        $resolved = $this->listPrefix($prefix);
        $keys     = [];
        $token    = null;

        do {
            $args = ['Bucket' => $this->bucket, 'Prefix' => $resolved];
            if ($token !== null) {
                $args['ContinuationToken'] = $token;
            }

            try {
                $result = $this->client->listObjectsV2($args);
            } catch (Throwable $exception) {
                throw new StorageException('Failed to list objects in S3.', $exception, [
                    'bucket' => $this->bucket,
                    'prefix' => $resolved,
                ]);
            }

            $contents = $result['Contents'] ?? [];
            foreach (is_array($contents) ? $contents : [] as $item) {
                if (!is_array($item) || !array_key_exists('Key', $item)) {
                    continue;
                }

                $keys[] = $this->stripPrefix((string) $item['Key']);
            }

            $token = null;
            if (($result['IsTruncated'] ?? false) === true) {
                $next = $result['NextContinuationToken'] ?? null;
                if (!is_string($next) || $next === '') {
                    throw new StorageException('S3 returned a truncated listing without continuation token.', null, [
                        'bucket' => $this->bucket,
                        'prefix' => $resolved,
                    ]);
                }

                $token = $next;
            }
        } while ($token !== null);

        return $keys;
    }

    /**
     * Серверное копирование (CopyObject): содержимое, Content-Type и пользовательские metadata сохраняются.
     */
    public function copy(string $sourcePath, string $targetPath): void
    {
        $sourceKey = $this->buildKey($sourcePath);
        $targetKey = $this->buildKey($targetPath);

        try {
            $this->client->copyObject([
                'Bucket'     => $this->bucket,
                'Key'        => $targetKey,
                'CopySource' => $this->bucket . '/' . FileHelper::encodeUrlPath($sourceKey),
            ]);
        } catch (Throwable $exception) {
            throw new StorageException('Failed to copy object in S3.', $exception, [
                'bucket' => $this->bucket,
                'source' => $sourceKey,
                'key'    => $targetKey,
            ]);
        }
    }

    public function move(string $sourcePath, string $targetPath): void
    {
        $this->copy($sourcePath, $targetPath);
        $this->delete($sourcePath);
    }

    public function rename(string $path, string $newName): void
    {
        $dir    = FileHelper::directory($path);
        $target = FileHelper::normalizePath($newName);
        $target = $dir === '' ? $target : $dir . '/' . $target;

        $this->move($path, $target);
    }

    public function url(string $path): string
    {
        $key = FileHelper::encodeUrlPath($this->buildKey($path));

        if ($this->baseUrl !== null) {
            return $this->baseUrl . '/' . $key;
        }

        if ($this->usePathStyle) {
            return $this->endpoint . '/' . $this->bucket . '/' . $key;
        }

        $parts  = parse_url($this->endpoint);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? 'https') : 'https';
        $host   = is_array($parts) ? ($parts['host'] ?? '') : '';

        if ($host === '') {
            return $this->endpoint . '/' . $this->bucket . '/' . $key;
        }

        return $scheme . '://' . $this->bucket . '.' . $host . '/' . $key;
    }

    public function download(string $path, ?string $name = null): ResponseInterface
    {
        return DownloadResponseFactory::fromString($this->read($path), $name ?? $path);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = self::normalizePrefix($prefix);
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): void
    {
        $this->baseUrl = $baseUrl !== null && $baseUrl !== '' ? rtrim($baseUrl, '/') : null;
    }

    /**
     * Ключ объекта: путь нормализуется по тем же правилам, что и в LocalStorage (FileHelper::normalizePath),
     * поэтому `../` не позволяет выйти за префикс диска (например, префикс тенанта).
     */
    private function buildKey(string $path): string
    {
        $path = FileHelper::normalizePath($path);

        if ($this->prefix === '') {
            return $path;
        }

        return $this->prefix . '/' . $path;
    }

    private function listPrefix(string $prefix): string
    {
        if (trim($prefix, " \t\n\r\x0B/\\") === '') {
            return $this->prefix === '' ? '' : $this->prefix . '/';
        }

        return $this->buildKey($prefix) . '/';
    }

    private static function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix, " \t\n\r\x0B/\\");

        return $prefix === '' ? '' : FileHelper::normalizePath($prefix);
    }

    private function stripPrefix(string $key): string
    {
        if ($this->prefix === '') {
            return $key;
        }

        $prefix = $this->prefix . '/';

        if (str_starts_with($key, $prefix)) {
            return substr($key, strlen($prefix));
        }

        return $key;
    }
}
