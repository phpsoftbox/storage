<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage\Tests\Support;

final class FakePresignedRequest
{
    public function __construct(
        private readonly string $url,
    ) {
    }

    public function getUri(): string
    {
        return $this->url;
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
