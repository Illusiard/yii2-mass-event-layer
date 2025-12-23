<?php

namespace illusiard\massEvents\components\db;

final class CommandContext
{
    public const OPERATION_UPDATE_ALL   = 'updateAll';
    public const OPERATION_DELETE_ALL   = 'deleteAll';
    public const OPERATION_INSERT       = 'insert';
    public const OPERATION_BATCH_INSERT = 'batchInsert';

    private string $operation;
    private string $table;

    /** @var array<string, mixed> */
    private array $set = [];

    private mixed $condition;

    /** @var array<string, mixed> */
    private array $conditionParams = [];

    /** @var array<int, string> */
    private array $columns = [];

    private int $rowsCount = 0;

    /**
     * @param string               $table
     * @param array                $columns
     * @param mixed                $condition
     * @param array<string, mixed> $params
     *
     * @return CommandContext
     */
    public static function forUpdateAll(string $table, array $columns, mixed $condition, array $params): self
    {
        $commandContext                  = new self();
        $commandContext->operation       = self::OPERATION_UPDATE_ALL;
        $commandContext->table           = $table;
        $commandContext->set             = $columns;
        $commandContext->condition       = $condition;
        $commandContext->conditionParams = $params;

        return $commandContext;
    }

    /**
     * @param string               $table
     * @param mixed                $condition
     * @param array<string, mixed> $params
     *
     * @return CommandContext
     */
    public static function forDeleteAll(string $table, mixed $condition, array $params): self
    {
        $commandContext                  = new self();
        $commandContext->operation       = self::OPERATION_DELETE_ALL;
        $commandContext->table           = $table;
        $commandContext->condition       = $condition;
        $commandContext->conditionParams = $params;

        return $commandContext;
    }

    /**
     * @param string               $table
     * @param array<string, mixed> $columns
     *
     * @return CommandContext
     */
    public static function forInsert(string $table, array $columns): self
    {
        $commandContext            = new self();
        $commandContext->operation = self::OPERATION_INSERT;
        $commandContext->table     = $table;
        $commandContext->set       = $columns;

        return $commandContext;
    }

    /**
     * @param string                        $table
     * @param array<int, string>            $columns
     * @param array<int, array<int, mixed>> $rows
     *
     * @return CommandContext
     */
    public static function forBatchInsert(string $table, array $columns, array $rows): self
    {
        $commandContext            = new self();
        $commandContext->operation = self::OPERATION_BATCH_INSERT;
        $commandContext->table     = $table;
        $commandContext->columns   = array_values(array_map('strval', $columns));
        $commandContext->rowsCount = count($rows);

        return $commandContext;
    }

    /**
     * @return array<string, mixed>
     */
    public function toEvent(int $affectedRows, ?string $dbId = null): array
    {
        $name = match ($this->operation) {
            self::OPERATION_UPDATE_ALL   => 'db.updateAll',
            self::OPERATION_DELETE_ALL   => 'db.deleteAll',
            self::OPERATION_INSERT       => 'db.insert',
            self::OPERATION_BATCH_INSERT => 'db.batchInsert',
            default                      => 'db.execute',
        };

        $payload = [
            'db'           => $dbId,
            'table'        => $this->table,
            'operation'    => $this->operation,
            'affectedRows' => $affectedRows,
        ];

        switch ($this->operation) {
            case self::OPERATION_UPDATE_ALL:
                $payload['set']       = $this->set;
                $payload['condition'] = $this->condition;
                $payload['params']    = $this->conditionParams;
                break;
            case self::OPERATION_DELETE_ALL:
                $payload['condition'] = $this->condition;
                $payload['params']    = $this->conditionParams;
                break;
            case self::OPERATION_INSERT:
                $payload['values'] = $this->set;
                break;
            case self::OPERATION_BATCH_INSERT:
                $payload['columns']   = $this->columns;
                $payload['rowsCount'] = $this->rowsCount;
                break;
        }

        return [
            'name'       => $name,
            'occurredAt' => time(),
            'payload'    => $payload,
            'meta'       => [
                'captured' => true,
            ],
        ];
    }
}
