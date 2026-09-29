<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Drivers\S3;

use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
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

    public function headObject(array $args): ?array
    {
        try {
            return $this->normalizeResult($this->client->headObject($args));
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    public function listObjectsV2(array $args): array
    {
        return $this->normalizeResult($this->client->listObjectsV2($args));
    }

    public function copyObject(array $args): void
    {
        $this->client->copyObject($args);
    }

    /**
     * @return array<string, mixed>
     */
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
