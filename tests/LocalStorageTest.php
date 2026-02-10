<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use FilesystemIterator;
use PhpSoftBox\Storage\Drivers\Local\LocalStorage;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

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

    public function testPutGetAndList(): void
    {
        $storage = new LocalStorage($this->root, 'https://cdn.local');

        $storage->put('folder/file.txt', 'content');

        $contents = $storage->get('folder/file.txt');

        $this->assertSame('content', $contents);
        $this->assertTrue($storage->exists('folder/file.txt'));

        $list = $storage->list('folder');
        $this->assertSame(['folder/file.txt'], $list);

        $url = $storage->url('folder/file.txt');
        $this->assertSame('https://cdn.local/folder/file.txt', $url);
    }

    public function testUrlRequiresBaseUrl(): void
    {
        $storage = new LocalStorage($this->root);

        $this->assertSame('/storage/file.txt', $storage->url('file.txt'));
    }
}
