<?php

namespace illusiard\massEvents\components;

use yii\base\Event;

/**
 * Lightweight Yii Event wrapper for mass-events payload.
 */
final class MassEventYiiEvent extends Event
{
    /** @var array<string, mixed> */
    public array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(array $payload, array $config = [])
    {
        $this->payload = $payload;
        parent::__construct($config);
    }
}
