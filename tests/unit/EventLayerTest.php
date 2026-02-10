<?php

namespace illusiard\massEvents\tests\unit;

use Yii;
use yii\base\Event;
use illusiard\massEvents\components\MassEventLayer;
use illusiard\massEvents\components\filters\IgnoreTablesFilter;
use illusiard\massEvents\components\filters\IgnoreSqlPatternsFilter;
use illusiard\massEvents\tests\BaseTestCase;
use yii\db\Exception;

final class EventLayerTest extends BaseTestCase
{
    /**
     * @return void
     * @throws Exception
     */
    public function testUpdateAllOutsideTransactionEmitsEventImmediately(): void
    {
        $events = [];
        Event::on(MassEventLayer::class, 'db.updateAll', static function ($e) use (&$events) {
            $events[] = $e->payload;
        });

        Yii::$app->db->createCommand()->update('employee', ['status' => 'archived'], ['department_id' => 1])->execute();

        $this->assertCount(1, $events);
        $this->assertSame('db.updateAll', $events[0]['name'] ?? null);

        $payload = $events[0]['payload'] ?? [];
        $this->assertSame('employee', $payload['table'] ?? null);
        $this->assertSame('updateAll', $payload['operation'] ?? null);
        $this->assertSame(['status' => 'archived'], $payload['set'] ?? null);
        $this->assertSame(['department_id' => 1], $payload['condition'] ?? null);
        $this->assertSame(2, $payload['affectedRows'] ?? null);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testBufferedInsideTransactionAndEmittedOnCommit(): void
    {
        $events = [];
        Event::on(MassEventLayer::class, 'db.updateAll', static function ($e) use (&$events) {
            $events[] = $e->payload;
        });

        $tx = Yii::$app->db->beginTransaction();
        Yii::$app->db->createCommand()->update('employee', ['status' => 't1'], ['department_id' => 1])->execute();

        $this->assertCount(0, $events);

        $tx->commit();

        $this->assertCount(1, $events);
        $payload = $events[0]['payload'] ?? [];
        $this->assertSame('employee', $payload['table'] ?? null);
        $this->assertSame(['status' => 't1'], $payload['set'] ?? null);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testNestedTransactionInnerRollbackDropsInnerLevelEvents(): void
    {
        $events = [];
        Event::on(MassEventLayer::class, 'db.updateAll', static function ($e) use (&$events) {
            $events[] = $e->payload;
        });

        $outer = Yii::$app->db->beginTransaction();

        Yii::$app->db->createCommand()->update('employee', ['status' => 'outer'], ['department_id' => 1])->execute();

        $inner = Yii::$app->db->beginTransaction();
        Yii::$app->db->createCommand()->update('employee', ['status' => 'inner'], ['department_id' => 1])->execute();
        $inner->rollBack();

        $this->assertCount(0, $events);

        $outer->commit();

        $this->assertCount(1, $events);
        $payload = $events[0]['payload'] ?? [];
        $this->assertSame(['status' => 'outer'], $payload['set'] ?? null);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testIgnoreTablesFilterSupportsWildcardAndExcept(): void
    {
        /** @var MassEventLayer $layer */
        $layer = Yii::$app->massEvents;
        $layer->setFilters([
            new IgnoreTablesFilter(['except' => ['log_*']]),
        ]);

        $events = [];
        Event::on(MassEventLayer::class, MassEventLayer::EVENT_ANY, static function ($e) use (&$events) {
            $events[] = $e->payload;
        });

        Yii::$app->db->createCommand()->update('log_events', ['message' => 'y'], ['id' => 1])->execute();
        Yii::$app->db->createCommand()->update('employee', ['status' => 'ok'], ['department_id' => 1])->execute();

        $this->assertNotEmpty($events);

        foreach ($events as $evt) {
            $table = $evt['payload']['table'] ?? null;
            $this->assertNotSame('log_events', $table);
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testIgnoreTablesOnlyPublishesSpecifiedTables(): void
    {
        /** @var MassEventLayer $layer */
        $layer = Yii::$app->massEvents;
        $layer->setFilters([
            new IgnoreTablesFilter(['only' => ['department']]),
        ]);

        $events = [];
        Event::on(MassEventLayer::class, MassEventLayer::EVENT_ANY, static function ($e) use (&$events) {
            $events[] = $e->payload;
        });

        Yii::$app->db->createCommand()->update('employee', ['status' => 'nope'], ['department_id' => 1])->execute();
        Yii::$app->db->createCommand()->update('department', ['name' => 'B'], ['id' => 1])->execute();

        $this->assertCount(1, $events);
        $this->assertSame('department', $events[0]['payload']['table'] ?? null);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testIgnoreSqlPatternsOnlyCanBeUsedForDbExecuteFallback(): void
    {
        /** @var MassEventLayer $layer */
        $layer = Yii::$app->massEvents;

        $layer->setFilters([
            new IgnoreSqlPatternsFilter(['only' => ['/^\s*UPDATE\b/i']]),
        ]);

        $events = [];
        Event::on(MassEventLayer::class, 'db.execute', static function ($e) use (&$events) {
            $events[] = $e->payload;
        });

        Yii::$app->db->createCommand(
            'UPDATE employee SET status = :s WHERE department_id = :d',
            [':s' => 'raw', ':d' => 1]
        )->execute();

        $this->assertCount(1, $events);
        $this->assertSame('db.execute', $events[0]['name'] ?? null);
        $this->assertMatchesRegularExpression('/^\s*UPDATE\b/i', $events[0]['payload']['sql'] ?? '');
    }
}
