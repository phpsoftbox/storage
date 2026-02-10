<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use PhpSoftBox\Storage\Storage;
use PHPUnit\Framework\TestCase;

use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class StorageTest extends TestCase
{
    public function testDefaultDiskWorks(): void
    {
        $root = sys_get_temp_dir() . '/psb_storage_' . uniqid('', true);
        if (!mkdir($root, 0775, true) && !is_dir($root)) {
            $this->fail('Не удалось создать временную директорию.');
        }

        $storage = new Storage([
            'default' => 'local',
            'disks'   => [
                'local' => [
                    'driver'   => 'local',
                    'rootPath' => $root,
                ],
            ],
        ]);

        $disk = $storage->disk();
        $disk->put('test.txt', 'hello');

        $contents = $disk->get('test.txt');
        $this->assertSame('hello', $contents);
    }
}
