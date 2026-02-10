<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use PhpSoftBox\Storage\Drivers\S3\S3Storage;
use PhpSoftBox\Storage\Tests\Support\FakeS3Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(S3Storage::class)]
final class S3StorageTest extends TestCase
{
    /**
     * Проверяет запись и чтение с учётом префикса.
     */
    #[Test]
    public function testPutAndGetUsesPrefix(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket', 'app');

        $storage->put('file.txt', 'content');

        $contents = $storage->get('file.txt');

        $this->assertSame('content', $contents);
        $this->assertSame('content', $client->objects['app/file.txt']);
        $this->assertSame('putObject', $client->calls[0]['method']);
        $this->assertSame('app/file.txt', $client->calls[0]['args']['Key']);
    }

    /**
     * Проверяет, что exists() возвращает false для отсутствующего ключа.
     */
    #[Test]
    public function testExistsReturnsFalseForMissingKey(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket');

        $this->assertFalse($storage->exists('missing.txt'));
    }

    /**
     * Проверяет, что list() возвращает ключи без базового префикса.
     */
    #[Test]
    public function testListStripsBasePrefix(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket', 'base');

        $storage->put('first.txt', 'one');
        $storage->put('nested/second.txt', 'two');

        $list = $storage->list('');

        $this->assertSame(['first.txt', 'nested/second.txt'], $list);
    }

    /**
     * Проверяет, что url() строится на основе endpoint.
     */
    #[Test]
    public function testUrlUsesEndpoint(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket', 'base', 'https://storage.yandexcloud.net', true);

        $url = $storage->url('file.txt');

        $this->assertSame('https://storage.yandexcloud.net/bucket/base/file.txt', $url);
    }
}
