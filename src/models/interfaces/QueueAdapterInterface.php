<?php

namespace illusiard\massEvents\models\interfaces;

interface QueueAdapterInterface
{
    public function push(string $serializedEvent): string;
}
