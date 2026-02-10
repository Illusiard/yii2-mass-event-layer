<?php

namespace illusiard\massEvents\components\db;

use illusiard\massEvents\components\MassEventLayer;
use Yii;
use yii\db\Transaction as YiiTransaction;

final class Transaction extends YiiTransaction
{
    public function commit(): void
    {
        $levelBefore = $this->level;

        try {
            parent::commit();
        } finally {
            $layer = Yii::$app->get('massEvents', false);
            if (!$layer instanceof MassEventLayer) {
                return;
            }

            $eventsToEmit = $layer->getEventBuffer()->onCommit($this, $levelBefore);
            if (!empty($eventsToEmit) && method_exists($layer, 'emitMany')) {
                $layer->emitMany($eventsToEmit);
            }
        }
    }

    public function rollBack(): void
    {
        $levelBefore = $this->level;

        try {
            parent::rollBack();
        } finally {
            $layer = Yii::$app->get('massEvents', false);
            if (!$layer instanceof MassEventLayer) {
                return;
            }

            $layer->getEventBuffer()->onRollback($this, $levelBefore);
        }
    }
}
