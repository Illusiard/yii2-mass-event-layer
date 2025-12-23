<?php

namespace illusiard\massEvents\components;

use illusiard\massEvents\models\interfaces\PublisherInterface;
use illusiard\massEvents\models\interfaces\QueueAdapterInterface;
use illusiard\massEvents\models\interfaces\SerializerInterface;

final class PublisherAsync implements PublisherInterface
{
    private QueueAdapterInterface $queue;
    private SerializerInterface   $serializer;

    public function __construct(QueueAdapterInterface $queue, SerializerInterface $serializer)
    {
        $this->queue      = $queue;
        $this->serializer = $serializer;
    }

    public function publish(array $event): void
    {
        $this->queue->push($this->serializer->serializeEvent($event));
    }

    public function publishMany(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }
}
