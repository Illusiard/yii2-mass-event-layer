<?php

namespace illusiard\massEvents\components;

use illusiard\massEvents\models\interfaces\PublisherInterface;

final class PublisherSync implements PublisherInterface
{
    private MassEventLayer $layer;

    public function __construct(MassEventLayer $layer)
    {
        $this->layer = $layer;
    }

    public function publish(array $event): void
    {
        $this->layer->publish($event);
    }

    public function publishMany(array $events): void
    {
        $this->layer->publishMany($events);
    }
}
