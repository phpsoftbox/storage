<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage;

final readonly class StoragePath
{
    public function __construct(
        private Storage $storage,
        private string $path,
        private ?string $disk = null,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function disk(): ?string
    {
        return $this->disk;
    }

    public function url(): ?string
    {
        if ($this->path === '') {
            return null;
        }

        return $this->storage->url($this->path, $this->disk);
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
