<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Drivers\S3;

use Aws\S3\S3Client;
use PhpSoftBox\Storage\Contracts\S3ClientInterface;
use PhpSoftBox\Storage\Contracts\StorageInterface;
use PhpSoftBox\Storage\DownloadResponseFactory;
use PhpSoftBox\Storage\StorageException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function basename;
use function class_exists;
use function dirname;
use function is_array;
use function is_object;
use function is_resource;
use function is_string;
use function ltrim;
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
        $this->prefix       = trim($prefix, '/');
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

    public function exists(string $path): bool
    {
        $key = $this->buildKey($path);

        try {
            $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function list(string $prefix = ''): array
    {
        $resolved = $this->buildKey($prefix);

        try {
            $result = $this->client->listObjectsV2(['Bucket' => $this->bucket, 'Prefix' => $resolved]);
        } catch (Throwable $exception) {
            throw new StorageException('Failed to list objects in S3.', $exception, [
                'bucket' => $this->bucket,
                'prefix' => $resolved,
            ]);
        }

        $contents = $result['Contents'] ?? [];
        $keys     = [];

        if (!is_array($contents)) {
            return $keys;
        }

        foreach ($contents as $item) {
            if (!is_array($item) || !array_key_exists('Key', $item)) {
                continue;
            }

            $keys[] = $this->stripPrefix((string) $item['Key']);
        }

        return $keys;
    }

    public function copy(string $sourcePath, string $targetPath): void
    {
        $contents = $this->read($sourcePath);
        $this->put($targetPath, $contents);
    }

    public function move(string $sourcePath, string $targetPath): void
    {
        $this->copy($sourcePath, $targetPath);
        $this->delete($sourcePath);
    }

    public function rename(string $path, string $newName): void
    {
        $newName = ltrim($newName, '/');
        $dir     = rtrim(dirname($path), '/');
        $target  = $dir === '' || $dir === '.' ? $newName : $dir . '/' . $newName;

        $this->move($path, $target);
    }

    public function url(string $path): string
    {
        $key = $this->buildKey($path);

        if ($this->baseUrl !== null) {
            return $this->baseUrl . '/' . ltrim($key, '/');
        }

        if ($this->usePathStyle) {
            return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . ltrim($key, '/');
        }

        $parts  = parse_url($this->endpoint);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? 'https') : 'https';
        $host   = is_array($parts) ? ($parts['host'] ?? '') : '';

        if ($host === '') {
            return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . ltrim($key, '/');
        }

        return $scheme . '://' . $this->bucket . '.' . $host . '/' . ltrim($key, '/');
    }

    public function download(string $path, ?string $name = null): ResponseInterface
    {
        $contents = $this->read($path);
        $filename = $name ?? basename($path);

        return DownloadResponseFactory::fromString($contents, $filename);
    }

    private function buildKey(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->prefix === '') {
            return $path;
        }

        return rtrim($this->prefix, '/') . '/' . $path;
    }

    private function stripPrefix(string $key): string
    {
        if ($this->prefix === '') {
            return $key;
        }

        $prefix = rtrim($this->prefix, '/') . '/';

        if (str_starts_with($key, $prefix)) {
            return ltrim(substr($key, strlen($prefix)), '/');
        }

        return $key;
    }
}
