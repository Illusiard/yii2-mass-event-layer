<?php

namespace illusiard\massEvents\services;

use SplObjectStorage;
use yii\db\Transaction;

final class EventBuffer
{
    /**
     * @var SplObjectStorage<Transaction, array<int, array<int, mixed>>>
     */
    private SplObjectStorage $storage;

    public function __construct()
    {
        $this->storage = new SplObjectStorage();
    }

    /**
     * @param mixed $event
     */
    public function add(Transaction $tx, int $level, $event): void
    {
        if ($level < 1) {
            $level = 1;
        }

        if (!$this->storage->contains($tx)) {
            $this->storage[$tx] = [];
        }

        $levels = $this->storage[$tx];

        if (!isset($levels[$level])) {
            $levels[$level] = [];
        }

        $levels[$level][] = $event;
        $this->storage[$tx] = $levels;
    }

    public function onCommit(Transaction $tx, int $levelBefore): ?array
    {
        if (!$this->storage->contains($tx)) {
            return null;
        }

        $levels = $this->storage[$tx];

        if ($levelBefore > 1) {
            $current = $levels[$levelBefore] ?? [];
            if (!empty($current)) {
                $parentLevel = $levelBefore - 1;
                if (!isset($levels[$parentLevel])) {
                    $levels[$parentLevel] = [];
                }
                $levels[$parentLevel] = array_merge($levels[$parentLevel], $current);
            }
            unset($levels[$levelBefore]);
            $this->storage[$tx] = $levels;
            return null;
        }

        // outer commit: publish everything from level 1
        $toPublish = $levels[1] ?? [];

        // clear all levels for this transaction
        $this->storage->detach($tx);

        return $toPublish;
    }

    public function onRollback(Transaction $tx, int $levelBefore): void
    {
        if (!$this->storage->contains($tx)) {
            return;
        }

        if ($levelBefore > 1) {
            $levels = $this->storage[$tx];
            unset($levels[$levelBefore]);
            $this->storage[$tx] = $levels;
            return;
        }

        // outer rollback: drop all
        $this->storage->detach($tx);
    }
}
