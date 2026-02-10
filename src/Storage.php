<?php

declare(strict_types=1);

namespace PhpSoftBox\Storage;

use PhpSoftBox\Storage\Contracts\StorageInterface;
use PhpSoftBox\Storage\Drivers\Local\LocalStorage;
use PhpSoftBox\Storage\Drivers\S3\S3Storage;

use function array_values;
use function is_array;
use function is_string;

final class Storage
{
    /** @var array<string, StorageInterface> */
    private array $disks = [];

    public function __construct(
        private readonly array $config = [],
    ) {
    }

    public function disk(?string $name = null): StorageInterface
    {
        $name = $name ?? $this->defaultDisk();

        if (!isset($this->disks[$name])) {
            $diskConfig         = $this->resolveDiskConfig($name);
            $this->disks[$name] = $this->createDisk($diskConfig);
        }

        return $this->disks[$name];
    }

    public function build(array $config): StorageInterface
    {
        return $this->createDisk($config);
    }

    public function url(string $path, ?string $disk = null): string
    {
        return $this->disk($disk)->url($path);
    }

    /**
     * @return list<string>
     */
    public function diskNames(): array
    {
        $disks = $this->config['disks'] ?? [];
        if (!is_array($disks) || $disks === []) {
            return [];
        }

        $names = [];
        foreach ($disks as $name => $_config) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $names[$name] = $name;
        }

        return array_values($names);
    }

    private function defaultDisk(): string
    {
        $default = $this->config['default'] ?? null;

        return is_string($default) && $default !== '' ? $default : 'local';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDiskConfig(string $name): array
    {
        $disks = $this->config['disks'] ?? [];
        if (!is_array($disks)) {
            $disks = [];
        }

        $diskConfig = $disks[$name] ?? null;
        if (!is_array($diskConfig)) {
            if ($name !== $this->defaultDisk()) {
                throw new StorageException('Storage disk is not configured: ' . $name);
            }

            return $this->defaultLocalConfig();
        }

        return $diskConfig;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createDisk(array $config): StorageInterface
    {
        $driver = $config['driver'] ?? 'local';
        if (!is_string($driver) || $driver === '') {
            $driver = 'local';
        }

        return match ($driver) {
            'local' => LocalStorage::fromConfig($config, $this->defaultLocalRoot()),
            's3'    => S3Storage::fromConfig($config),
            default => throw new StorageException('Unknown storage driver: ' . $driver),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLocalConfig(): array
    {
        return [
            'driver'   => 'local',
            'rootPath' => $this->defaultLocalRoot(),
        ];
    }

    private function defaultLocalRoot(): string
    {
        return 'local/storage';
    }
}
