<?php

namespace illusiard\massEvents\components\db;

use Yii;
use yii\db\Command as YiiCommand;
use yii\db\Connection;

class Command extends YiiCommand
{
    private ?CommandContext $context = null;

    public function __construct(Connection $db, ?string $sql = null, array $params = [])
    {
        parent::__construct($db, $sql, $params);
    }

    public function update($table, $columns, $condition = '', $params = []): static
    {
        $this->context = CommandContext::forUpdateAll($table, $columns, $condition, $params);

        return parent::update($table, $columns, $condition, $params);
    }

    public function delete($table, $condition = '', $params = []): static
    {
        $this->context = CommandContext::forDeleteAll($table, $condition, $params);

        return parent::delete($table, $condition, $params);
    }

    public function insert($table, $columns): static
    {
        $this->context = CommandContext::forInsert($table, $columns);

        return parent::insert($table, $columns);
    }

    public function batchInsert($table, $columns, $rows): static
    {
        $this->context = CommandContext::forBatchInsert($table, $columns, $rows);

        return parent::batchInsert($table, $columns, $rows);
    }

    public function execute(): int
    {
        $affected = parent::execute();
        $this->emitEventAfterExecute($affected);

        return $affected;
    }

    private function emitEventAfterExecute(int $affectedRows): void
    {
        $layer = Yii::$app->get('massEvents', false);
        if ($layer === null || !method_exists($layer, 'publish') || !method_exists($layer, 'getEventBuffer')) {
            $this->context = null;

            return;
        }

        $dbId = $this->db instanceof Connection ? ($this->db->dsn ?? null) : null;

        $event = $this->context
            ? $this->context->toEvent($affectedRows, $dbId)
            : [
                'name'       => 'db.execute',
                'occurredAt' => time(),
                'payload'    => [
                    'db'           => $dbId,
                    'sql'          => $this->getSql(),
                    'params'       => $this->params,
                    'affectedRows' => $affectedRows,
                ],
                'meta'       => [
                    'captured' => false,
                ],
            ];

        $transaction = $this->db->getTransaction();
        if ($transaction !== null) {
            $layer->getEventBuffer()->add($transaction, (int)$transaction->level, $event);
        } else {
            $layer->publish($event);
        }

        $this->context = null;
    }
}
