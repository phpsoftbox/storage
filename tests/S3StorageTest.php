<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use PhpSoftBox\Storage\Drivers\S3\S3Storage;
use PhpSoftBox\Storage\StorageException;
use PhpSoftBox\Storage\Tests\Support\FakeS3Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function sprintf;

#[CoversClass(S3Storage::class)]
#[CoversMethod(S3Storage::class, 'put')]
#[CoversMethod(S3Storage::class, 'get')]
#[CoversMethod(S3Storage::class, 'exists')]
#[CoversMethod(S3Storage::class, 'list')]
#[CoversMethod(S3Storage::class, 'copy')]
#[CoversMethod(S3Storage::class, 'rename')]
#[CoversMethod(S3Storage::class, 'url')]
#[CoversMethod(S3Storage::class, 'setPrefix')]
final class S3StorageTest extends TestCase
{
    /**
     * Проверяет запись и чтение с учётом префикса.
     *
     * @see S3Storage::put()
     * @see S3Storage::get()
     */
    #[Test]
    public function putAndGetUsesPrefix(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket', 'app');

        $storage->put('file.txt', 'content');

        $this->assertSame('content', $storage->get('file.txt'));
        $this->assertSame('content', $client->objects['app/file.txt']);
        $this->assertSame('putObject', $client->calls[0]['method']);
        $this->assertSame('app/file.txt', $client->calls[0]['args']['Key']);
    }

    /**
     * Проверяет, что путь нормализуется так же, как в LocalStorage: слэши, `.` и пустые сегменты убираются.
     *
     * @see S3Storage::put()
     */
    #[Test]
    public function putNormalizesPath(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket', 'app');

        $storage->put('/docs//./a.txt', 'content');

        $this->assertSame(['app/docs/a.txt'], array_keys($client->objects));
    }

    /**
     * Проверяет, что `../` не позволяет выйти за префикс диска (изоляция тенантов).
     *
     * @see S3Storage::put()
     */
    #[Test]
    public function putRejectsTraversalOutsidePrefix(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket', 'tenant1');

        try {
            $storage->put('../tenant2/x.txt', 'evil');
            $this->fail('Ожидалось StorageException.');
        } catch (StorageException) {
            // Запрос в S3 не должен уйти.
            $this->assertSame([], $client->calls);
        }
    }

    /**
     * Проверяет, что пустой путь отклоняется.
     *
     * @see S3Storage::get()
     */
    #[Test]
    public function getRejectsEmptyPath(): void
    {
        $storage = new S3Storage(new FakeS3Client(), 'bucket');

        $this->expectException(StorageException::class);

        $storage->get('/');
    }

    /**
     * Проверяет, что `..` внутри имени файла допустимо.
     *
     * @see S3Storage::put()
     */
    #[Test]
    public function putAllowsDoubleDotInsideName(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket');

        $storage->put('reports/report..v2.pdf', 'pdf');

        $this->assertArrayHasKey('reports/report..v2.pdf', $client->objects);
    }

    /**
     * Проверяет, что префикс с `..` отклоняется.
     *
     * @see S3Storage::setPrefix()
     */
    #[Test]
    public function setPrefixRejectsTraversal(): void
    {
        $storage = new S3Storage(new FakeS3Client(), 'bucket');

        $this->expectException(StorageException::class);

        $storage->setPrefix('tenant1/../tenant2');
    }

    /**
     * Проверяет, что опции put() передаются в PutObject, но не переопределяют Bucket/Key/Body.
     *
     * @see S3Storage::put()
     */
    #[Test]
    public function putPassesOptionsWithoutOverridingKey(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket');

        $storage->put('a.txt', 'content', ['ContentType' => 'text/plain', 'Key' => 'other.txt']);

        $this->assertSame('a.txt', $client->calls[0]['args']['Key']);
        $this->assertSame('text/plain', $client->calls[0]['args']['ContentType']);
    }

    /**
     * Проверяет, что exists() возвращает false для отсутствующего ключа.
     *
     * @see S3Storage::exists()
     */
    #[Test]
    public function existsReturnsFalseForMissingKey(): void
    {
        $storage = new S3Storage(new FakeS3Client(), 'bucket');

        $this->assertFalse($storage->exists('missing.txt'));
    }

    /**
     * Проверяет, что ошибка доступа или сети не выдаётся за «файла нет».
     *
     * @see S3Storage::exists()
     */
    #[Test]
    public function existsThrowsOnClientError(): void
    {
        $client = new FakeS3Client();

        $client->headException = new RuntimeException('403 Forbidden');

        $storage = new S3Storage($client, 'bucket');

        $this->expectException(StorageException::class);

        $storage->exists('file.txt');
    }

    /**
     * Проверяет, что list() возвращает ключи без базового префикса.
     *
     * @see S3Storage::list()
     */
    #[Test]
    public function listStripsBasePrefix(): void
    {
        $storage = new S3Storage(new FakeS3Client(), 'bucket', 'base');

        $storage->put('first.txt', 'one');
        $storage->put('nested/second.txt', 'two');

        $this->assertSame(['first.txt', 'nested/second.txt'], $storage->list());
    }

    /**
     * Проверяет, что list() проходит все страницы листинга, а не только первую.
     *
     * @see S3Storage::list()
     */
    #[Test]
    public function listFollowsContinuationTokens(): void
    {
        $client = new FakeS3Client();

        $client->pageSize = 2;
        $storage          = new S3Storage($client, 'bucket');

        for ($i = 1; $i <= 5; $i++) {
            $storage->put(sprintf('files/%d.txt', $i), 'x');
        }

        $this->assertSame(
            ['files/1.txt', 'files/2.txt', 'files/3.txt', 'files/4.txt', 'files/5.txt'],
            $storage->list('files'),
        );
    }

    /**
     * Проверяет, что префикс list() трактуется как каталог: соседний `reports-old` не попадает в выборку.
     *
     * @see S3Storage::list()
     */
    #[Test]
    public function listTreatsPrefixAsDirectory(): void
    {
        $storage = new S3Storage(new FakeS3Client(), 'bucket');

        $storage->put('reports/a.txt', 'a');
        $storage->put('reports-old/b.txt', 'b');

        $this->assertSame(['reports/a.txt'], $storage->list('reports'));
    }

    /**
     * Проверяет, что copy() выполняется на стороне S3 и сохраняет metadata исходного объекта.
     *
     * @see S3Storage::copy()
     */
    #[Test]
    public function copyUsesServerSideCopy(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket', 'app');

        $storage->put('dir/отчёт 1.pdf', 'pdf', ['ContentType' => 'application/pdf']);
        $storage->copy('dir/отчёт 1.pdf', 'copy.pdf');

        $this->assertSame(
            'bucket/app/dir/%D0%BE%D1%82%D1%87%D1%91%D1%82%201.pdf',
            $client->calls[1]['args']['CopySource'],
        );
        $this->assertSame(['ContentType' => 'application/pdf'], $client->metadata['app/copy.pdf']);
    }

    /**
     * Проверяет, что rename() переименовывает объект в том же «каталоге».
     *
     * @see S3Storage::rename()
     */
    #[Test]
    public function renameKeepsDirectory(): void
    {
        $client = new FakeS3Client();

        $storage = new S3Storage($client, 'bucket');

        $storage->put('dir/old.txt', 'x');
        $storage->rename('dir/old.txt', 'new.txt');

        $this->assertSame(['dir/new.txt'], array_keys($client->objects));
    }

    /**
     * Проверяет, что url() строится на основе endpoint.
     *
     * @see S3Storage::url()
     */
    #[Test]
    public function urlUsesEndpoint(): void
    {
        $storage = new S3Storage(new FakeS3Client(), 'bucket', 'base', 'https://storage.yandexcloud.net', true);

        $this->assertSame('https://storage.yandexcloud.net/bucket/base/file.txt', $storage->url('file.txt'));
    }

    /**
     * Проверяет, что url() кодирует сегменты пути.
     *
     * @see S3Storage::url()
     */
    #[Test]
    public function urlEncodesSegments(): void
    {
        $storage = new S3Storage(new FakeS3Client(), 'bucket', '', 'https://s3.local', true, 'https://cdn.local');

        $this->assertSame('https://cdn.local/a%20b/c%23d%3F.txt', $storage->url('a b/c#d?.txt'));
    }
}
