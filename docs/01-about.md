# About

`phpsoftbox/storage` предоставляет единый интерфейс для работы с дисками и драйверами хранения.

Основные элементы:
- `Storage` — менеджер дисков
- `StorageInterface` (в `Contracts`) — контракт драйвера
- `LocalStorage`, `S3Storage` — базовые драйверы
- `FileHelper` — статические утилиты для локальных путей и файловой системы

Если диск по умолчанию не описан в `disks`, создаётся local-диск с корнем из верхнеуровневого `rootPath` и базовым
URL `/storage`. Корень обязан быть абсолютным: относительный путь зависел бы от рабочего каталога процесса (CLI и FPM
писали бы в разные места). Без `rootPath` обращение к неописанному диску по умолчанию — `StorageException`.

```php
$storage = new Storage(['rootPath' => $projectRoot . '/local/storage']);
$storage->disk(); // LocalStorage в $projectRoot/local/storage
```
