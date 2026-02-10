<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage;

use RuntimeException;
use Throwable;

final class StorageException extends RuntimeException
{
    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
