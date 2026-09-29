# Local

`LocalStorage` работает с файловой системой и сохраняет данные на диск. Для генерации URL можно указать `baseUrl`. Если не указать, используется `/storage` (подход с symlink в public).

```php
use PhpSoftBox\Storage\Drivers\Local\LocalStorage;

$disk = new LocalStorage(
    rootPath: __DIR__ . '/storage',
    baseUrl: 'https://cdn.local',
);

$disk->put('avatars/user-1.png', $binary);
$url = $disk->url('avatars/user-1.png');
```

`rootPath` должен быть абсолютным (`/var/app/storage`, `C:\app\storage`, `vfs://root`), иначе `StorageException`.

`put()` атомарный: содержимое пишется во временный файл `.psb-tmp-<random>` в том же каталоге и переносится
`rename()`, поэтому параллельный читатель видит либо старую, либо новую версию целиком. Незавершённые временные файлы
не попадают в `list()`.

Опции `put()`:

- `permissions` (int) — права файла, по умолчанию `0666 & ~umask()` (как у `file_put_contents()`):

```php
$disk->put('keys/private.pem', $pem, ['permissions' => 0600]);
```

Любые другие ключи (например, S3-шные `ContentType`) приводят к `StorageException` — они не игнорируются молча.

`list()` возвращает отсортированный список; `url()` кодирует сегменты пути (`a b.png` → `a%20b.png`).

Локальные операции с файловой системой можно выполнять через `FileHelper`:

```php
use PhpSoftBox\Storage\FileHelper;

FileHelper::copyFile(
    __DIR__ . '/storage/avatars/user-1.png',
    __DIR__ . '/storage/backup/user-1.png',
);
```
