<?php

namespace illusiard\massEvents\components;

use illusiard\massEvents\models\dto\EventEnvelope;
use illusiard\massEvents\models\interfaces\FilterInterface;
use illusiard\massEvents\models\interfaces\PublisherInterface;
use illusiard\massEvents\models\interfaces\SerializerInterface;
use illusiard\massEvents\services\EventBuffer;
use illusiard\massEvents\services\VersionedJsonSerializer;
use yii\base\Component;
use yii\queue\Queue;
use Yii;

class MassEventLayer extends Component
{
    public const EVENT_ANY = 'massEvents.any';

    private ?EventBuffer $buffer = null;

    /** @var array<int, FilterInterface> */
    private array $filters = [];

    private ?SerializerInterface $serializer = null;
    private ?PublisherInterface  $publisher  = null;

    public function init(): void
    {
        parent::init();
        $this->buffer     = new EventBuffer();
        $this->serializer = new VersionedJsonSerializer();
        $this->publisher  = $this->buildDefaultPublisher();
    }

    public function getEventBuffer(): EventBuffer
    {
        return $this->buffer ??= new EventBuffer();
    }

    public function getSerializer(): SerializerInterface
    {
        return $this->serializer ??= new VersionedJsonSerializer();
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function getPublisher(): PublisherInterface
    {
        return $this->publisher ??= $this->buildDefaultPublisher();
    }

    public function setPublisher(PublisherInterface $publisher): void
    {
        $this->publisher = $publisher;
    }

    /** @param array<int, FilterInterface> $filters */
    public function setFilters(array $filters): void
    {
        $this->filters = [];
        foreach ($filters as $filter) {
            if ($filter instanceof FilterInterface) {
                $this->filters[] = $filter;
            }
        }
    }

    public function addFilter(FilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    /** @param array<string, mixed> $event */
    public function publish(array $event): void
    {
        $event = EventEnvelope::wrap($event);
        if (!$this->passesFilters($event)) {
            return;
        }

        $name = (string)($event['name'] ?? '');
        if ($name === '') {
            $name          = 'massEvents.unknown';
            $event['name'] = $name;
        }

        // 1) Global catch-all
        $this->trigger(self::EVENT_ANY, new MassEventYiiEvent($event));

        // 2) Named event
        $this->trigger($name, new MassEventYiiEvent($event));
    }

    /** @param array<int, array<string, mixed>> $events */
    public function publishMany(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }

    /** @param array<string, mixed> $event */
    public function emit(array $event): void
    {
        $event = EventEnvelope::wrap($event);
        if (!$this->passesFilters($event)) {
            return;
        }
        $this->getPublisher()->publish($event);
    }

    /** @param array<int, array<string, mixed>> $events */
    public function emitMany(array $events): void
    {
        $out = [];
        foreach ($events as $event) {
            $event = EventEnvelope::wrap($event);
            if ($this->passesFilters($event)) {
                $out[] = $event;
            }
        }
        if (!empty($out)) {
            $this->getPublisher()->publishMany($out);
        }
    }

    private function passesFilters(array $event): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->shouldPublish($event)) {
                return false;
            }
        }

        return true;
    }

    private function buildDefaultPublisher(): PublisherInterface
    {
        $queue = Yii::$app->get('queue', false);
        if ($queue instanceof Queue) {
            return new PublisherAsync(new Yii2QueueAdapter('queue'), $this->getSerializer());
        }

        return new PublisherSync($this);
    }
}
