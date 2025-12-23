<?php

namespace illusiard\massEvents\services;

use illusiard\massEvents\models\interfaces\SerializerInterface;

final class VersionedJsonSerializer implements SerializerInterface
{
    public const SCHEMA  = 'illusiard.mass-events';
    public const VERSION = 1;

    public function serializeEvent(array $event): string
    {
        $envelope = [
            'schema'        => self::SCHEMA,
            'schemaVersion' => self::VERSION,
            'id'            => $this->newId(),
            'sentAt'        => time(),
            'event'         => $event,
        ];

        return json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function unserializeEvent(string $payload): array
    {
        /** @var mixed $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Serialized payload must decode to an array.');
        }

        // v0: raw event array (no envelope)
        if (!isset($data['schemaVersion']) || !isset($data['event'])) {
            /** @var array<string, mixed> $event */
            $event = $data;

            return $this->normalizeEvent($event);
        }

        $schemaVersion = (int)($data['schemaVersion'] ?? 0);

        /** @var mixed $evt */
        $evt = $data['event'] ?? [];
        if (!is_array($evt)) {
            throw new \UnexpectedValueException('Envelope.event must be an array.');
        }

        /** @var array<string, mixed> $event */
        $event = $evt;

        while ($schemaVersion < self::VERSION) {
            $event = $this->upcast($schemaVersion, $event);
            $schemaVersion++;
        }

        return $this->normalizeEvent($event);
    }

    /**
     * @param int                  $fromVersion
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    private function upcast(int $fromVersion, array $event): array
    {
        // v0 -> v1 is a no-op for the event shape.
        // Future breaking changes would be implemented here.
        return $event;
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    private function normalizeEvent(array $event): array
    {
        if (!isset($event['id']) || !is_string($event['id']) || $event['id'] === '') {
            $event['id'] = $this->newId();
        }
        if (!isset($event['occurredAt'])) {
            $event['occurredAt'] = time();
        }
        if (!isset($event['meta']) || !is_array($event['meta'])) {
            $event['meta'] = [];
        }
        if (!isset($event['meta']['transport'])) {
            $event['meta']['transport'] = 'json-envelope';
        }

        return $event;
    }

    private function newId(): string
    {
        $time = (int)(microtime(true) * 1000);

        return dechex($time) . '-' . bin2hex(random_bytes(8));
    }
}
