<?php

namespace illusiard\massEvents\tests;

use illusiard\massEvents\components\Bootstrap;
use illusiard\massEvents\components\db\Connection;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection as YiiConnection;
use Illusiard\Yii2Testkit\Testing\YiiTestCase;
use yii\db\Exception;

/**
 * Base test case for this extension, built on top of testkit.
 *
 * Responsibilities:
 * - Create minimal SQLite schema required for tests.
 * - Bootstrap the extension after schema creation (to avoid noise events).
 */
abstract class BaseTestCase extends YiiTestCase
{
    protected function appConfig(): array
    {
        $config = parent::appConfig();

        $config['components']['db'] = [
            'class' => Connection::class,
            'dsn'   => 'sqlite::memory:',
        ];

        return $config;
    }

    /**
     * @return void
     * @throws InvalidConfigException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $db = Yii::$app->db;
        $db->open();

        $this->createSchema($db);

        $bootstrap = new Bootstrap();
        $bootstrap->bootstrap(Yii::$app);
    }

    protected function createSchema(YiiConnection $db): void
    {
        $db->createCommand('CREATE TABLE employee (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT, department_id INTEGER)')->execute();
        $db->createCommand('CREATE TABLE department (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)')->execute();
        $db->createCommand('CREATE TABLE log_events (id INTEGER PRIMARY KEY AUTOINCREMENT, message TEXT)')->execute();

        $db->createCommand()->insert('employee', ['status' => 'active', 'department_id' => 1])->execute();
        $db->createCommand()->insert('employee', ['status' => 'active', 'department_id' => 1])->execute();
        $db->createCommand()->insert('department', ['name' => 'A'])->execute();
        $db->createCommand()->insert('log_events', ['message' => 'x'])->execute();
    }
}
