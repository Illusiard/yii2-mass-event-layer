<?php

namespace illusiard\massEvents\models\interfaces;

interface FilterInterface
{
    /** @param array<string, mixed> $event */
    public function shouldPublish(array $event): bool;
}
