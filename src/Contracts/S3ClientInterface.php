<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Contracts;

use DateInterval;
use DateTimeInterface;

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
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function headObject(array $args): array;

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function listObjectsV2(array $args): array;

    /**
     * @param array<string, mixed> $args
     */
    public function getCommand(string $name, array $args): object;

    public function createPresignedRequest(object $command, DateInterval|DateTimeInterface|int $expires): object;
}
