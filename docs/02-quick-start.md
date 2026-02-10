# Quick Start

```php
use PhpSoftBox\Storage\Storage;

$storage = new Storage([
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'rootPath' => __DIR__ . '/storage',
            'baseUrl' => 'https://cdn.local',
        ],
    ],
]);

$contents = $storage->disk()->get('avatars/user-1.png');
$storage->disk()->put('avatars/user-2.png', 'binary');
```

S3 диск:

```php
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
        ],
    ],
]);

$storage->disk('s3')->put('reports/hello.txt', 'Hello!');
```

Диск на лету:

```php
$documentDisk = $storage->build([
    'driver' => 'local',
    'rootPath' => '/documents',
]);

$documentDisk->put('file.txt', 'content');
```
