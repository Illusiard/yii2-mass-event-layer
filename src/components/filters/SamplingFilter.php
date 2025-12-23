<?php

namespace illusiard\massEvents\components\filters;

use illusiard\massEvents\models\interfaces\FilterInterface;

final class SamplingFilter implements FilterInterface
{
    private float $rate;

    public function __construct(float $rate)
    {
        $this->rate = max(0.0, min(1.0, $rate));
    }

    public function shouldPublish(array $event): bool
    {
        if ($this->rate >= 1.0) {
            return true;
        }
        if ($this->rate <= 0.0) {
            return false;
        }

        $id = $event['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $hash   = crc32($id);
            $bucket = ($hash % 10000) / 10000.0;

            return $bucket < $this->rate;
        }

        return (mt_rand() / mt_getrandmax()) < $this->rate;
    }
}
