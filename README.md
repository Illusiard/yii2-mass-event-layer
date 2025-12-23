# illusiard/yii2-mass-event-layer

Уровень инфраструктуры **нулевой** конфигурации для Yii2, который генерирует **массовые события** для операций с БД (включая `updateAll`, `deleteAll`, `batchInsert`) и **учитывает транзакции**, включая **вложенные транзакции** (точки сохранения).

Этот пакет не содержит **бизнес-логики**. Он фиксирует только то, что произошло в БД (и публикует их как события Yii), чтобы другие модули могли на них подписаться.

## Функции

- **Нулевая конфигурация**: начинает работать сразу после `composer require`
- Захватывает массовые операции с БД через расширенный `yii\db\Command`:
  - `db.updateAll`, `db.deleteAll`, `db.insert`, `db.batchInsert`
  - Резервное событие: `db.execute` (когда структурированный контекст недоступен)
- **Поддержка вложенных транзакций** через исправленный `yii\db\Transaction`
  - публиковать события только при **внешней фиксации** (при коммите реальной транзакции)
  - корректно сбрасывать события при внутреннем/внешнем откате (отмене точки сохранения или реальной транзакции)
- **Единый контракт**: все события публикуются с единой схемой данных.
- **Внутри нет бизнес-логики**.

## Установка

```bash
composer require illusiard/yii2-mass-event-layer
```

Yii2 автоматически загрузит расширение через `yiisoft/yii2-composer` (он генерирует `vendor/yiisoft/extensions.php`). 

Если это не так:
1. Убедитесь, что компонент приложения «massEvents» существует.
2. Исправьте `db->commandClass` на `illusiard\massEvents\components\db\Command`.
3. Исправьте `db->transactionClass` на `illusiard\massEvents\components\db\Transaction`.

> Если ваше приложение не загружает `vendor/yiisoft/extensions.php`, включите его в своей конфигурации (стандартные приложения Yii2 уже загружают).
> Если для подключения в вашем приложении используется не `db`, произведите соответствующую замену в настройках подключения.

## Использование

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

## События

- `db.updateAll`
- `db.deleteAll`
- `db.insert`
- `db.batchInsert`
- `db.execute` (резервный вариант, когда контекст команды невозможно получить)

## Транзакции и вложенные транзакции

Уровень **учитывает транзакции** и корректно поддерживает вложенные транзакции (точки сохранения):

| Сценарий | Результат |
|---|---|
| Внутренняя `commit()` (освободить точку сохранения) | события **буферизуются** и объединяются на родительском уровне |
| Внутренний `rollback()` (откат к точке сохранения) | события текущего уровня **отбрасываются** |
| Внешний `commit()` (настоящий COMMIT) | **все буферизованные** события публикуются |
| Внешний `rollback()` | **все буферизованные** события отбрасываются |

### Техническое примечание

Функционал реализуется путем автоматической замены компонентов соединения с БД:

- `Connection::commandClass` -> custom `Command` wrapper
- `Connection::transactionClass` -> custom `Transaction` wrapper

`Command` сохраняет контекст операции (table/set/condition и т.д.) перед `execute()` и создает событие *после* `execute()` с `affectedRows`.
Если транзакция активна, события буферизуются и освобождаются при внешней фиксации.

### Известное ограничение (так задумано)

Транзакции, выполняемые **вне** `yii\db\Connection` / `yii\db\Transaction` (например, кастомные адаптеры PDO `BEGIN/COMMIT`), не отслеживаются.
Это намеренное ограничение: обычно Yii2-проекты используют API транзакций фреймворка.
Для таких случаев предлагаю вам реализовать своё подключение.

## Асинхронный паблишер через yii2-queue

Если в вашем приложении есть компонент очереди, который является экземпляром `yii\queue\Queue`,
слой автоматически обнаруживает его и переключается в **асинхронный режим**:

- событие сериализуется в JSON
- пушатся через `PublishEventJob`
- джоба восстанавливает событие и публикует его через `MassEventLayer`

## Фильтры (добавляемые)

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
- **allowOnly**: публиковать только указанные/совпадающие таблицы

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

- по умолчанию **exclude**: игнорировать указанные/совпадающие таблицы
- **allowOnly**: публиковать только указанные/совпадающие таблицы

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