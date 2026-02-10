# API

Менеджер `Storage`:

- `disk(?string $name = null): StorageInterface`
- `build(array $config): StorageInterface`
- `url(string $path, ?string $disk = null): string`

Контракт драйвера описан в `PhpSoftBox\Storage\Contracts\StorageInterface`:

- `get(string $path): string`
- `read(string $path): string`
- `put(string $path, string $contents, array $options = []): void`
- `delete(string $path): void`
- `exists(string $path): bool`
- `missing(string $path): bool`
- `list(string $prefix = ''): array`
- `copy(string $sourcePath, string $targetPath): void`
- `move(string $sourcePath, string $targetPath): void`
- `rename(string $path, string $newName): void`
- `url(string $path): string`
- `download(string $path, ?string $name = null): ResponseInterface`

`get()` — алиас для `read()`.

`FileHelper` содержит статические утилиты для локальных путей и файловой системы:

- `normalizePath(string $path): string`
- `directory(string $path): string`
- `directories(string $path): array`
- `createDirectory(string $path, int $mode = 0775, bool $recursive = true): void`
- `ensureDirectory(string $path, int $mode = 0775): void`
- `ensureDirectoryForFile(string $path, int $mode = 0775): void`
- `deleteDirectory(string $path): void`
- `deleteFile(string $path): void`
- `copyFile(string $source, string $target, int $mode = 0775): void`
- `moveFile(string $source, string $target, int $mode = 0775): void`
- `renameFile(string $path, string $newName, int $mode = 0775): void`

`download()` использует `phpsoftbox/http-message` для формирования ответа.

Для ошибок используется `StorageException`, где доступен контекст (`bucket`, `key`, `prefix` или `path`).
