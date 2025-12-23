<?php

namespace illusiard\massEvents\models\interfaces;

interface PublisherInterface
{
    /** @param array<string, mixed> $event */
    public function publish(array $event): void;

    /** @param array<int, array<string, mixed>> $events */
    public function publishMany(array $events): void;
}
