<?php

namespace illusiard\massEvents\components;

use illusiard\massEvents\components\db\Command;
use illusiard\massEvents\components\db\Transaction;
use yii\base\BootstrapInterface;
use yii\base\Application;
use yii\db\Command as YiiCommand;
use yii\db\Connection as YiiConnection;
use yii\db\Transaction as YiiTransaction;

final class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        if (!$app instanceof Application) {
            return;
        }

        $this->ensureComponent($app);
        $this->patchDbConnection($app);
    }

    private function ensureComponent(Application $app): void
    {
        if ($app->has('massEvents', true)) {
            return;
        }

        $app->set('massEvents', [
            'class' => MassEventLayer::class,
        ]);
    }

    private function patchDbConnection(Application $app): void
    {
        $db = $app->get('db', false);
        if (!$db instanceof YiiConnection) {
            return;
        }

        if ($db->commandClass === YiiCommand::class) {
            $db->commandClass = Command::class;
        }

        if ($db->transactionClass === YiiTransaction::class) {
            $db->transactionClass = Transaction::class;
        }
    }
}
