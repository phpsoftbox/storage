<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests\Support;

use DateInterval;
use DateTimeInterface;
use PhpSoftBox\Storage\Contracts\S3ClientInterface;
use RuntimeException;

use function array_key_exists;
use function array_values;
use function is_string;
use function str_starts_with;
use function strlen;

final class FakeS3Client implements S3ClientInterface
{
    /** @var list<array{method: string, args: mixed}> */
    public array $calls = [];

    /** @var array<string, string> */
    public array $objects = [];

    public string $presignedUrl = 'https://storage.example/signed';

    public function putObject(array $args): void
    {
        $this->calls[] = ['method' => 'putObject', 'args' => $args];

        $key  = $args['Key'] ?? null;
        $body = $args['Body'] ?? '';

        if (!is_string($key)) {
            throw new RuntimeException('Missing Key for putObject.');
        }

        $this->objects[$key] = (string) $body;
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
            unset($this->objects[$key]);
        }
    }

    public function headObject(array $args): array
    {
        $this->calls[] = ['method' => 'headObject', 'args' => $args];

        $key = $args['Key'] ?? null;
        if (!is_string($key) || !array_key_exists($key, $this->objects)) {
            throw new RuntimeException('Object not found.');
        }

        return ['ContentLength' => strlen($this->objects[$key])];
    }

    public function listObjectsV2(array $args): array
    {
        $this->calls[] = ['method' => 'listObjectsV2', 'args' => $args];

        $prefix   = $args['Prefix'] ?? '';
        $contents = [];

        foreach ($this->objects as $key => $value) {
            if ($prefix === '' || str_starts_with($key, (string) $prefix)) {
                $contents[] = ['Key' => $key];
            }
        }

        return ['Contents' => array_values($contents)];
    }

    public function getCommand(string $name, array $args): object
    {
        $this->calls[] = ['method' => 'getCommand', 'args' => ['name' => $name, 'args' => $args]];

        return (object) ['name' => $name, 'args' => $args];
    }

    public function createPresignedRequest(object $command, DateInterval|DateTimeInterface|int $expires): object
    {
        $this->calls[] = ['method' => 'createPresignedRequest', 'args' => ['command' => $command, 'expires' => $expires]];

        return new FakePresignedRequest($this->presignedUrl);
    }
}
