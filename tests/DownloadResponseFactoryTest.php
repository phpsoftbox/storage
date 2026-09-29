<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests;

use PhpSoftBox\Storage\DownloadResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadResponseFactory::class)]
#[CoversMethod(DownloadResponseFactory::class, 'fromString')]
#[CoversMethod(DownloadResponseFactory::class, 'contentDisposition')]
final class DownloadResponseFactoryTest extends TestCase
{
    /**
     * Проверяет, что UTF-8 имя передаётся через filename*, а в filename — ASCII-fallback.
     *
     * @see DownloadResponseFactory::contentDisposition()
     */
    #[Test]
    public function contentDispositionEncodesUtf8Name(): void
    {
        $this->assertSame(
            'attachment; filename="___ 1.pdf"; filename*=UTF-8\'\'%D0%B0%D0%BA%D1%82%201.pdf',
            DownloadResponseFactory::contentDisposition('docs/акт 1.pdf'),
        );
    }

    /**
     * Проверяет, что кавычки и переводы строк не ломают заголовок.
     *
     * @see DownloadResponseFactory::contentDisposition()
     */
    #[Test]
    public function contentDispositionEscapesQuotesAndControlChars(): void
    {
        $this->assertSame(
            'attachment; filename="a_b_c.txt"; filename*=UTF-8\'\'a%22b%0Ac.txt',
            DownloadResponseFactory::contentDisposition("a\"b\nc.txt"),
        );
    }

    /**
     * Проверяет, что ответ содержит тело, длину и заголовок Content-Disposition.
     *
     * @see DownloadResponseFactory::fromString()
     */
    #[Test]
    public function fromStringBuildsAttachmentResponse(): void
    {
        $response = DownloadResponseFactory::fromString('content', 'dir/file.txt');

        $this->assertSame('7', $response->getHeaderLine('Content-Length'));
        $this->assertSame(
            'attachment; filename="file.txt"; filename*=UTF-8\'\'file.txt',
            $response->getHeaderLine('Content-Disposition'),
        );
        $this->assertSame('content', (string) $response->getBody());
    }
}
