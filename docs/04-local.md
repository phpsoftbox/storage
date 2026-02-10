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

Локальные операции с файловой системой можно выполнять через `FileHelper`:

```php
use PhpSoftBox\Storage\FileHelper;

FileHelper::copyFile(
    __DIR__ . '/storage/avatars/user-1.png',
    __DIR__ . '/storage/backup/user-1.png',
);
```
