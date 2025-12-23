<?php

namespace illusiard\massEvents\models\dto;

final class EventEnvelope
{
    /** @param array<string, mixed> $event @return array<string, mixed> */
    public static function wrap(array $event): array
    {
        if (!isset($event['id']) || !is_string($event['id']) || $event['id'] === '') {
            $event['id'] = self::ulidLike();
        }
        if (!isset($event['occurredAt'])) {
            $event['occurredAt'] = time();
        }

        return $event;
    }

    private static function ulidLike(): string
    {
        $time = (int)(microtime(true) * 1000);
        $rand = bin2hex(random_bytes(8));

        return dechex($time) . '-' . $rand;
    }
}
