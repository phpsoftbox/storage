<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use PhpSoftBox\Storage\Drivers\Local\LocalStorage;
use PhpSoftBox\Storage\FileHelper;
use PhpSoftBox\Storage\Storage;
use PhpSoftBox\Storage\StorageException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(Storage::class)]
#[CoversMethod(Storage::class, 'disk')]
final class StorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/psb_storage_' . uniqid('', true);
        FileHelper::ensureDirectory($this->root);
    }

    protected function tearDown(): void
    {
        FileHelper::deleteDirectory($this->root);
    }

    /**
     * Проверяет работу диска по умолчанию через Storage.
     *
     * @see Storage::disk()
     */
    #[Test]
    public function defaultDiskWorks(): void
    {
        $storage = new Storage([
            'default' => 'local',
            'disks'   => [
                'local' => [
                    'driver'   => 'local',
                    'rootPath' => $this->root,
                ],
            ],
        ]);

        $disk = $storage->disk();
        $disk->put('test.txt', 'hello');

        $this->assertSame('hello', $disk->get('test.txt'));
    }

    /**
     * Проверяет, что неописанный диск по умолчанию создаётся в абсолютном rootPath из конфигурации.
     *
     * @see Storage::disk()
     */
    #[Test]
    public function implicitDefaultDiskUsesConfiguredRootPath(): void
    {
        $storage = new Storage(['rootPath' => $this->root]);

        $disk = $storage->disk();

        $this->assertInstanceOf(LocalStorage::class, $disk);
        $this->assertSame($this->root, $disk->rootPath());
    }

    /**
     * Проверяет, что без rootPath неописанный диск по умолчанию не создаётся в каталоге, зависящем от cwd.
     *
     * @see Storage::disk()
     */
    #[Test]
    public function implicitDefaultDiskWithoutRootPathThrows(): void
    {
        $storage = new Storage();

        $this->expectException(StorageException::class);

        $storage->disk();
    }

    /**
     * Проверяет, что local-диск без собственного rootPath использует общий rootPath.
     *
     * @see Storage::disk()
     */
    #[Test]
    public function localDiskFallsBackToConfiguredRootPath(): void
    {
        $storage = new Storage([
            'rootPath' => $this->root,
            'disks'    => ['public' => ['driver' => 'local', 'baseUrl' => '/public']],
        ]);

        $disk = $storage->disk('public');

        $this->assertInstanceOf(LocalStorage::class, $disk);
        $this->assertSame($this->root, $disk->rootPath());
    }
}
