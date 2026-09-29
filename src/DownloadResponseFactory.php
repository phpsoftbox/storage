<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage;

use PhpSoftBox\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;

use function class_exists;
use function preg_replace;
use function rawurlencode;
use function strlen;
use function strrpos;
use function substr;
use function trim;

final class DownloadResponseFactory
{
    public static function fromString(string $contents, string $filename, string $contentType = 'application/octet-stream'): ResponseInterface
    {
        if (!class_exists(Response::class)) {
            throw new StorageException('phpsoftbox/http-message is required for download responses.');
        }

        $headers = [
            'Content-Type'        => $contentType,
            'Content-Length'      => (string) strlen($contents),
            'Content-Disposition' => self::contentDisposition($filename),
        ];

        /** @var ResponseInterface $response */
        $response = new Response(200, $headers, $contents);

        return $response;
    }

    /**
     * Формирует `Content-Disposition: attachment` по RFC 6266: ASCII-fallback в `filename`
     * (кавычки, обратные слэши, управляющие и не-ASCII символы заменяются на `_`)
     * и точное UTF-8 имя в `filename*`.
     */
    public static function contentDisposition(string $filename): string
    {
        $name = self::baseName($filename);
        if ($name === '') {
            $name = 'download';
        }

        // Посимвольно для корректного UTF-8, побайтно — если имя не является валидным UTF-8.
        $fallback = preg_replace('/[^\x20-\x7E]|["\\\\]/u', '_', $name)
            ?? (string) preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $name);

        return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }

    private static function baseName(string $filename): string
    {
        // basename() зависит от локали и портит многобайтные имена — отрезаем путь вручную.
        $filename = trim($filename);
        foreach (['/', '\\'] as $separator) {
            $position = strrpos($filename, $separator);
            if ($position !== false) {
                $filename = substr($filename, $position + 1);
            }
        }

        return $filename;
    }
}
