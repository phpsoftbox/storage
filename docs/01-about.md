# About

`phpsoftbox/storage` предоставляет единый интерфейс для работы с дисками и драйверами хранения.

Основные элементы:
- `Storage` — менеджер дисков
- `StorageInterface` (в `Contracts`) — контракт драйвера
- `LocalStorage`, `S3Storage` — базовые драйверы
- `FileHelper` — статические утилиты для локальных путей и файловой системы

Если диски не заданы, автоматически создаётся `local` с rootPath `local/storage` и базовым URL `/storage`.
