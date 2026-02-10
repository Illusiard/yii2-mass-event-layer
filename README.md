# illusiard/yii2-mass-event-layer

Инфраструктурный слой **нулевой конфигурации** для Yii2, который фиксирует **массовые операции с БД** (`updateAll`, `deleteAll`, `batchInsert`) и публикует их как события Yii.

Слой **учитывает транзакции**, включая **вложенные транзакции** (точки сохранения), и не содержит бизнес-логики.

## Functions

- **Нулевая конфигурация**: начинает работать сразу после `composer require`
- Захватывает массовые операции с БД через расширенный `yii\db\Command`:
  - `db.updateAll`, `db.deleteAll`, `db.insert`, `db.batchInsert`
  - Резервное событие: `db.execute` (когда структурированный контекст недоступен)
- **Поддержка вложенных транзакций** через исправленный `yii\db\Transaction`
  - публиковать события только при **внешней фиксации** (при коммите реальной транзакции)
  - корректно сбрасывать события при внутреннем/внешнем откате (отмене точки сохранения или реальной транзакции)
- **Единый контракт**: все события публикуются с единой схемой данных.
- **Внутри нет бизнес-логики**.

## Installation

```bash
composer require illusiard/yii2-mass-event-layer
```

По умолчанию расширение подключается автоматически через `yiisoft/yii2-composer` (он генерирует `vendor/yiisoft/extensions.php`). 

Если это не так:
1. Убедитесь, что компонент приложения «massEvents» существует.
2. Если ваше приложение не загружает `vendor/yiisoft/extensions.php`, включите его в своей конфигурации (стандартные приложения Yii2 уже загружают).

В конфигурации приложения:

```php
'components' => [
    'db' => [
        'class' => illusiard\massEvents\components\db\Connection::class,
        'dsn' => 'mysql:host=localhost;dbname=app',
        'username' => 'user',
        'password' => 'secret',
        'charset' => 'utf8',
    ],
],
```

Все остальные параметры соединения полностью совместимы с yii\db\Connection
и передаются без изменений.

## Usages

Подпишитесь на события, используя события Yii:

```php
use yii\base\Event;
use illusiard\massEvents\components\MassEventLayer;

Event::on(MassEventLayer::class, 'db.updateAll', function ($e) {
    /** @var \illusiard\massEvents\components\MassEventYiiEvent $e */
    $payload = $e->payload;

    // $payload example:
    // [
    //   'name' => 'db.updateAll',
    //   'occurredAt' => 1700000000,
    //   'payload' => [
    //     'db' => 'mysql:host=...;dbname=...',
    //     'table' => 'employee',
    //     'operation' => 'updateAll',
    //     'affectedRows' => 42,
    //     'set' => ['status' => 'archived'],
    //     'condition' => ['department_id' => 10],
    //     'params' => [],
    //   ],
    //   'meta' => ['captured' => true],
    // ]
});
```

Вы также можете подписаться на всеобщее событие (т.е. на все события):

```php
Event::on(MassEventLayer::class, MassEventLayer::EVENT_ANY, function ($e) {
    /** @var \illusiard\massEvents\components\MassEventYiiEvent $e */
    // handle any mass event
});
```

## Events

- `db.updateAll`
- `db.deleteAll`
- `db.insert`
- `db.batchInsert`
- `db.execute` (резервный вариант, когда контекст команды невозможно получить)

## Transactions and nested transactions

Уровень **учитывает транзакции** и корректно поддерживает вложенные транзакции (точки сохранения):

| Сценарий | Результат |
|---|---|
| Внутренняя `commit()` (освободить точку сохранения) | события **буферизуются** и объединяются на родительском уровне |
| Внутренний `rollback()` (откат к точке сохранения) | события текущего уровня **отбрасываются** |
| Внешний `commit()` (настоящий COMMIT) | **все буферизованные** события публикуются |
| Внешний `rollback()` | **все буферизованные** события отбрасываются |

### Техническое примечание

Функционал реализуется через кастомное соединение с БД
(`illusiard\massEvents\components\db\Connection`), которое:

- возвращает расширенный `Command` для перехвата массовых операций;
- использует расширенный `Transaction` для корректной работы с вложенными транзакциями;
- не изменяет публичный контракт `yii\db\Connection`.

`Command` сохраняет контекст операции (table/set/condition и т.д.) перед `execute()` и создает событие *после* `execute()` с `affectedRows`.
Если транзакция активна, события буферизуются и освобождаются при внешней фиксации.

### Известное ограничение (так задумано)

Транзакции, выполняемые **вне** `yii\db\Connection` / `yii\db\Transaction` (например, кастомные адаптеры PDO `BEGIN/COMMIT`), не отслеживаются.
Это намеренное ограничение: обычно Yii2-проекты используют API транзакций фреймворка.
Для таких случаев предлагаю вам реализовать своё подключение.

## Асинхронный паблишер через yii2-queue

Если в вашем приложении есть компонент очереди, который является экземпляром `yii\queue\Queue`, слой автоматически переключается в **асинхронный режим**:

- событие сериализуется в JSON
- пушатся через `PublishEventJob`
- джоба восстанавливает событие и публикует его через `MassEventLayer`

## Filters (добавляемые)

Встроенные инфраструктурные фильтры:

- `IgnoreTablesFilter` — игнорировать `payload.table`
- `IgnoreSqlPatternsFilter` — игнорировать SQL по регулярному выражению (полезно для `db.execute`)
- `SamplingFilter` — детерминированная выборка по `event.id`

Пример:

```php
use illusiard\massEvents\components\filters\IgnoreTablesFilter;
use illusiard\massEvents\components\filters\IgnoreSqlPatternsFilter;
use illusiard\massEvents\components\filters\SamplingFilter;

Yii::$app->massEvents->setFilters([
    new IgnoreTablesFilter(['migration', 'cache']),
    new IgnoreSqlPatternsFilter(['/^\s*SELECT\b/i']),
    new SamplingFilter(0.25),
]);
```

### IgnoreTablesFilter

`IgnoreTablesFilter` поддерживает:

- exact: `employee`
- wildcard: `log_*`, `*_tmp`, `user_??`
- regex (preg): `/^audit_.+$/i`

Режимы:

- по умолчанию **exclude**: игнорировать указанные/совпадающие таблицы
- **only**: публиковать только указанные/совпадающие таблицы

Примеры:

```php
use illusiard\massEvents\components\filters\IgnoreTablesFilter;

Yii::$app->massEvents->addFilter(new IgnoreTablesFilter(['except' => ['migration', 'cache', 'log_*']]));
Yii::$app->massEvents->addFilter(new IgnoreTablesFilter(['only' => ['employee', 'department']]));
```


### IgnoreSqlPatternsFilter

`IgnoreSqlPatternsFilter` поддерживает:

- exact: `employee`
- wildcard: `log_*`, `*_tmp`, `user_??`
- regex (preg): `/^\s*SELECT\b/i`

Режимы:

- по умолчанию **exclude**: игнорировать указанные/совпадающие SQL-запросы
- **only**: публиковать только указанные/совпадающие SQL-запросы

Примеры:

```php
use illusiard\massEvents\components\filters\IgnoreSqlPatternsFilter;

Yii::$app->massEvents->addFilter(new IgnoreSqlPatternsFilter(['except' => ['/^\s*SELECT\b/i']]));
Yii::$app->massEvents->addFilter(new IgnoreSqlPatternsFilter(['only' => ['/^\s*UPDATE\b/i']]));
```

## Сериализация

Сериализатор по умолчанию: `illusiard\massEvents\services\VersionedJsonSerializer`.

```php
Yii::$app->massEvents->setSerializer(new MySerializer());
```

### Обратная совместимость

Если полезные данные джобы не являются контейнером (нет `schemaVersion`/`event`), они обрабатываются как **v0** (необработанный массив событий) и нормализуются.
Старые версии схемы могут быть преобразованы в текущую версию внутри VersionedJsonSerializer.

## Версионный контейнер (полезная нагрузка очереди с обратной совместимостью)

При публикации в очереди события сериализуются с использованием **версированного контейнера**.

- Текущая схема: `illusiard.mass-events`
- Текущая версия: `schemaVersion`: `1`

Пример контейнера (v1):

```json
{
  "schema": "illusiard.mass-events",
  "schemaVersion": 1,
  "id": "envelope-id",
  "sentAt": 1700000001,
  "event": {
    "id": "event-id",
    "name": "db.updateAll",
    "occurredAt": 1700000000,
    "payload": { "table": "employee", "affectedRows": 42 },
    "meta": { "captured": true }
  }
}
```

## License

BSD-3-Clause
MIT-compatible permissive license