# PhpSoftBox Storage

## About
`phpsoftbox/storage` — компонент хранения файлов для PhpSoftBox с поддержкой локального диска и S3-совместимых хранилищ. По умолчанию ориентирован на Yandex Cloud Object Storage, но можно подключить любой совместимый endpoint.

Ключевые свойства:
- менеджер `Storage` для работы с дисками
- драйверы: `LocalStorage`, `S3Storage`
- `FileHelper` со статическими утилитами для локальных путей и файловой системы

## Quick Start
```php
use PhpSoftBox\Storage\Storage;

$storage = new Storage([
    'default' => 'uploads',
    'disks' => [
        'uploads' => [
            'driver' => 'local',
            'rootPath' => __DIR__ . '/storage',
            'baseUrl' => 'https://cdn.local',
        ],
    ],
]);

$contents = $storage->disk('uploads')->get('reports/hello.txt');
```

S3:

```php
use PhpSoftBox\Storage\Storage;

$storage = new Storage([
    'default' => 's3',
    'disks' => [
        's3' => [
            'driver' => 's3',
            'bucket' => 'my-bucket',
            'key' => $_ENV['S3_KEY'],
            'secret' => $_ENV['S3_SECRET'],
            'endpoint' => 'https://storage.yandexcloud.net',
            'region' => 'ru-central1',
            'prefix' => 'app',
        ],
    ],
]);

$storage->disk('s3')->put('reports/hello.txt', 'Hello!');
```

## Оглавление
- [Документация](docs/index.md)
- [About](docs/01-about.md)
- [Quick Start](docs/02-quick-start.md)
- [S3 (Yandex Cloud)](docs/03-s3.md)
- [Local](docs/04-local.md)
- [API](docs/05-api.md)
- [DI](docs/06-di.md)
