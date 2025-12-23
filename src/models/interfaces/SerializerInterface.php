<?php

namespace illusiard\massEvents\models\interfaces;

interface SerializerInterface
{
    /** @param array<string, mixed> $event */
    public function serializeEvent(array $event): string;

    /** @return array<string, mixed> */
    public function unserializeEvent(string $payload): array;
}
