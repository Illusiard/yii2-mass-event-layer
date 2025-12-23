<?php

namespace illusiard\massEvents\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\queue\Queue;
use illusiard\massEvents\models\interfaces\QueueAdapterInterface;
use illusiard\massEvents\components\queue\PublishEventJob;

final class Yii2QueueAdapter implements QueueAdapterInterface
{
    private string $queueComponentId;

    public function __construct(string $queueComponentId = 'queue')
    {
        $this->queueComponentId = $queueComponentId;
    }

    public function push(string $serializedEvent): string
    {
        $queue = Yii::$app->get($this->queueComponentId, false);
        if (!$queue instanceof Queue) {
            throw new InvalidConfigException(
                "Queue component '{$this->queueComponentId}' must be instance of yii\\queue\\Queue."
            );
        }

        $jobId = $queue->push(new PublishEventJob(['serializedEvent' => $serializedEvent]));

        return (string)$jobId;
    }
}
