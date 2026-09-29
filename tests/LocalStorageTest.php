<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use FilesystemIterator;
use PhpSoftBox\Storage\Drivers\Local\LocalStorage;
use PhpSoftBox\Storage\StorageException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_diff;
use function array_values;
use function clearstatcache;
use function file_put_contents;
use function fileperms;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function umask;
use function uniqid;
use function unlink;

#[CoversClass(LocalStorage::class)]
#[CoversMethod(LocalStorage::class, '__construct')]
#[CoversMethod(LocalStorage::class, 'put')]
#[CoversMethod(LocalStorage::class, 'get')]
#[CoversMethod(LocalStorage::class, 'list')]
#[CoversMethod(LocalStorage::class, 'url')]
final class LocalStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/psb_storage_' . uniqid('', true);
        if (!mkdir($this->root, 0775, true) && !is_dir($this->root)) {
            $this->fail('Не удалось создать временную директорию.');
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $info) {
            if ($info instanceof SplFileInfo && $info->isFile()) {
                @unlink($info->getPathname());
            } elseif ($info instanceof SplFileInfo && $info->isDir()) {
                @rmdir($info->getPathname());
            }
        }

        @rmdir($this->root);
    }

    /**
     * Проверяет базовые операции записи, чтения и листинга для local.
     *
     * @see LocalStorage::put()
     * @see LocalStorage::get()
     * @see LocalStorage::list()
     */
    #[Test]
    public function putGetAndList(): void
    {
        $storage = new LocalStorage($this->root, 'https://cdn.local');

        $storage->put('folder/file.txt', 'content');

        $this->assertSame('content', $storage->get('folder/file.txt'));
        $this->assertTrue($storage->exists('folder/file.txt'));
        $this->assertSame(['folder/file.txt'], $storage->list('folder'));
        $this->assertSame('https://cdn.local/folder/file.txt', $storage->url('folder/file.txt'));
    }

    /**
     * Проверяет, что url() для local использует дефолтный путь без baseUrl.
     *
     * @see LocalStorage::url()
     */
    #[Test]
    public function urlUsesDefaultBaseUrl(): void
    {
        $storage = new LocalStorage($this->root);

        $this->assertSame('/storage/file.txt', $storage->url('file.txt'));
    }

    /**
     * Проверяет, что url() кодирует сегменты пути.
     *
     * @see LocalStorage::url()
     */
    #[Test]
    public function urlEncodesSegments(): void
    {
        $storage = new LocalStorage($this->root);

        $this->assertSame('/storage/a%20b/%D1%84%23.txt', $storage->url('a b/ф#.txt'));
    }

    /**
     * Проверяет, что put() перезаписывает файл целиком и не оставляет временных файлов.
     *
     * @see LocalStorage::put()
     */
    #[Test]
    public function putReplacesFileWithoutTemporaryLeftovers(): void
    {
        $storage = new LocalStorage($this->root);

        $storage->put('dir/file.txt', 'first version');
        $storage->put('dir/file.txt', 'second');

        $this->assertSame('second', $storage->get('dir/file.txt'));
        $this->assertSame(['file.txt'], array_values(array_diff(scandir($this->root . '/dir'), ['.', '..'])));
    }

    /**
     * Проверяет, что list() не показывает незавершённые временные файлы put().
     *
     * @see LocalStorage::list()
     */
    #[Test]
    public function listSkipsTemporaryFiles(): void
    {
        $storage = new LocalStorage($this->root);

        $storage->put('file.txt', 'content');
        file_put_contents($this->root . '/.psb-tmp-0123456789abcdef', 'partial');

        $this->assertSame(['file.txt'], $storage->list());
    }

    /**
     * Проверяет, что опция permissions задаёт права файла.
     *
     * @see LocalStorage::put()
     */
    #[Test]
    public function putAppliesPermissionsOption(): void
    {
        $storage = new LocalStorage($this->root);

        $storage->put('secret.txt', 'content', ['permissions' => 0600]);
        clearstatcache();

        $this->assertSame(0600, fileperms($this->root . '/secret.txt') & 0777);
    }

    /**
     * Проверяет, что права по умолчанию такие же, как у file_put_contents (0666 с учётом umask), а не 0600.
     *
     * @see LocalStorage::put()
     */
    #[Test]
    public function putUsesUmaskPermissionsByDefault(): void
    {
        $storage = new LocalStorage($this->root);

        $storage->put('public.txt', 'content');
        clearstatcache();

        $this->assertSame(0666 & ~umask(), fileperms($this->root . '/public.txt') & 0777);
    }

    /**
     * Проверяет, что неподдерживаемые опции не игнорируются молча.
     *
     * @see LocalStorage::put()
     */
    #[Test]
    public function putRejectsUnsupportedOption(): void
    {
        $storage = new LocalStorage($this->root);

        $this->expectException(StorageException::class);

        $storage->put('file.txt', 'content', ['ContentType' => 'text/plain']);
    }

    /**
     * Проверяет, что относительный корень диска отклоняется (он зависел бы от cwd CLI/FPM).
     *
     * @see LocalStorage::__construct()
     */
    #[Test]
    public function constructorRejectsRelativeRoot(): void
    {
        $this->expectException(StorageException::class);

        new LocalStorage('local/storage');
    }

    /**
     * Проверяет, что путь с `..` не выходит за корень диска.
     *
     * @see LocalStorage::put()
     */
    #[Test]
    public function putRejectsTraversal(): void
    {
        $storage = new LocalStorage($this->root);

        $this->expectException(StorageException::class);

        $storage->put('../outside.txt', 'content');
    }
}
