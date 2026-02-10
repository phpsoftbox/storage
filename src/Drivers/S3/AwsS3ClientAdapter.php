<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Drivers\S3;

use Aws\Result;
use Aws\S3\S3Client;
use DateInterval;
use DateTimeInterface;
use PhpSoftBox\Storage\Contracts\S3ClientInterface;

use function is_array;
use function is_object;
use function method_exists;

final class AwsS3ClientAdapter implements S3ClientInterface
{
    public function __construct(
        private readonly S3Client $client,
    ) {
    }

    public function putObject(array $args): void
    {
        $this->client->putObject($args);
    }

    public function getObject(array $args): array
    {
        return $this->normalizeResult($this->client->getObject($args));
    }

    public function deleteObject(array $args): void
    {
        $this->client->deleteObject($args);
    }

    public function headObject(array $args): array
    {
        return $this->normalizeResult($this->client->headObject($args));
    }

    public function listObjectsV2(array $args): array
    {
        return $this->normalizeResult($this->client->listObjectsV2($args));
    }

    public function getCommand(string $name, array $args): object
    {
        return $this->client->getCommand($name, $args);
    }

    public function createPresignedRequest(object $command, DateInterval|DateTimeInterface|int $expires): object
    {
        return $this->client->createPresignedRequest($command, $expires);
    }

    private function normalizeResult(mixed $result): array
    {
        if ($result instanceof Result) {
            return $result->toArray();
        }

        if (is_array($result)) {
            return $result;
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            /** @var array<string, mixed> $data */
            $data = $result->toArray();

            return $data;
        }

        return (array) $result;
    }
}
