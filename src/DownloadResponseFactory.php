<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage;

use PhpSoftBox\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;

use function basename;
use function class_exists;
use function strlen;

final class DownloadResponseFactory
{
    public static function fromString(string $contents, string $filename, string $contentType = 'application/octet-stream'): ResponseInterface
    {
        if (!class_exists(Response::class)) {
            throw new StorageException('phpsoftbox/http-message is required for download responses.');
        }

        $name    = basename($filename);
        $headers = [
            'Content-Type'        => $contentType,
            'Content-Length'      => (string) strlen($contents),
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ];

        /** @var ResponseInterface $response */
        $response = new Response(200, $headers, $contents);

        return $response;
    }
}
