<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use PhpSoftBox\Storage\FileHelper;
use PhpSoftBox\Storage\StorageException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileHelper::class)]
#[CoversMethod(FileHelper::class, 'normalizePath')]
#[CoversMethod(FileHelper::class, 'isAbsolutePath')]
#[CoversMethod(FileHelper::class, 'encodeUrlPath')]
final class FileHelperTest extends TestCase
{
    /**
     * Проверяет, что обратные слэши, `.` и лишние разделители нормализуются.
     *
     * @see FileHelper::normalizePath()
     */
    #[Test]
    public function normalizePathCollapsesSeparators(): void
    {
        $this->assertSame('a/b/c.txt', FileHelper::normalizePath('/a\\\\b/./c.txt'));
    }

    /**
     * Проверяет, что `..` внутри имени файла не считается выходом за корень.
     *
     * @see FileHelper::normalizePath()
     */
    #[Test]
    public function normalizePathAllowsDoubleDotInsideName(): void
    {
        $this->assertSame('docs/report..v2.pdf', FileHelper::normalizePath('docs/report..v2.pdf'));
    }

    /**
     * Проверяет, что сегмент `..` запрещён.
     *
     * @see FileHelper::normalizePath()
     */
    #[Test]
    public function normalizePathRejectsParentSegment(): void
    {
        $this->expectException(StorageException::class);

        FileHelper::normalizePath('tenant1/../tenant2/x');
    }

    /**
     * Проверяет, что NUL-байт в пути запрещён.
     *
     * @see FileHelper::normalizePath()
     */
    #[Test]
    public function normalizePathRejectsNulByte(): void
    {
        $this->expectException(StorageException::class);

        FileHelper::normalizePath("a.txt\0.png");
    }

    /**
     * Проверяет распознавание абсолютных путей.
     *
     * @see FileHelper::isAbsolutePath()
     */
    #[Test]
    public function isAbsolutePathRecognizesAbsolutePaths(): void
    {
        $this->assertTrue(FileHelper::isAbsolutePath('/var/storage'));
        $this->assertTrue(FileHelper::isAbsolutePath('C:\\storage'));
        $this->assertTrue(FileHelper::isAbsolutePath('vfs://root'));
        $this->assertFalse(FileHelper::isAbsolutePath('local/storage'));
        $this->assertFalse(FileHelper::isAbsolutePath('./storage'));
    }

    /**
     * Проверяет, что сегменты пути кодируются для URL, а разделители сохраняются.
     *
     * @see FileHelper::encodeUrlPath()
     */
    #[Test]
    public function encodeUrlPathEncodesSegments(): void
    {
        $this->assertSame('a%20b/%3F%23.txt', FileHelper::encodeUrlPath('a b/?#.txt'));
    }
}
