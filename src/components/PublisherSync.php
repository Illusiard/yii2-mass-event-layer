<?php

namespace illusiard\massEvents\components;

use illusiard\massEvents\models\interfaces\PublisherInterface;

final class PublisherSync implements PublisherInterface
{
    private readonly MassEventLayer $layer;

    public function __construct(MassEventLayer $layer)
    {
        $this->layer = $layer;
    }

    #[\Override]
    public function publish(array $event): void
    {
        $this->layer->publish($event);
    }

    #[\Override]
    public function publishMany(array $events): void
    {
        $this->layer->publishMany($events);
    }
}
