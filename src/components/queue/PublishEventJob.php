<?php

namespace illusiard\massEvents\components\queue;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

final class PublishEventJob extends BaseObject implements JobInterface
{
    public string $serializedEvent = '';

    public function execute($queue): void
    {
        $layer = Yii::$app->get('massEvents', false);
        if ($layer === null || !method_exists($layer, 'getSerializer') || !method_exists($layer, 'publish')) {
            return;
        }

        $serializer = $layer->getSerializer();
        $event      = $serializer->unserializeEvent($this->serializedEvent);
        $layer->publish($event);
    }
}
