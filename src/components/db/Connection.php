<?php

namespace illusiard\massEvents\components\db;

use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\Connection as YiiConnection;
use yii\db\Exception;
use yii\db\Transaction as YiiTransaction;

class Connection extends YiiConnection
{
    private ?Transaction $_transaction = null;

    /**
     * Creates a DB command.
     *
     * @param string|null $sql    the SQL statement to be executed
     * @param array       $params the parameters to be bound to the SQL statement
     */
    public function createCommand($sql = null, $params = []): Command
    {
        $command = new Command([
            'db'  => $this,
            'sql' => $sql,
        ]);
        $command->bindValues($params);

        return $command;
    }

    /**
     * Begins a transaction and returns it.
     *
     * Yii2 does not provide a transactionClass property, so the only reliable way to use a custom
     * Transaction implementation is to override beginTransaction().
     *
     * @param null $isolationLevel the isolation level for the transaction
     *
     * @return YiiTransaction
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws Exception
     */
    public function beginTransaction($isolationLevel = null): YiiTransaction
    {
        $this->open();

        $transaction = $this->getTransaction();
        if ($transaction === null) {
            $transaction = $this->_transaction = new Transaction(['db' => $this]);
        }

        $transaction->begin($isolationLevel);

        return $transaction;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->_transaction && $this->_transaction->getIsActive() ? $this->_transaction : null;
    }

    public function close(): void
    {
        parent::close();
        $this->_transaction = null;
    }

    public function __sleep()
    {
        $fields = (array)$this;

        unset(
            // общие
            $fields['pdo'],
            // private поля yii\db\Connection
            $fields["\0yii\db\Connection\0_master"],
            $fields["\0yii\db\Connection\0_slave"],
            $fields["\0yii\db\Connection\0_transaction"],
            $fields["\0yii\db\Connection\0_schema"],
            // private поля текущего Connection
            $fields["\0" . self::class . "\0_transaction"]
        );

        return array_keys($fields);
    }

    public function __clone()
    {
        parent::__clone();

        $this->_transaction = null;
    }
}
