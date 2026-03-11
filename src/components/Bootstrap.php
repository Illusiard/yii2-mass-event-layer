<?php

namespace illusiard\massEvents\components;

use yii\base\BootstrapInterface;
use yii\base\Application;
use yii\base\InvalidConfigException;

final class Bootstrap implements BootstrapInterface
{
    /**
     * @param $app
     *
     * @return void
     * @throws InvalidConfigException
     */
    public function bootstrap($app): void
    {
        if (!$app instanceof Application) {
            return;
        }

        $this->ensureComponent($app);
    }

    /**
     * @param Application $app
     *
     * @return void
     * @throws InvalidConfigException
     */
    private function ensureComponent(Application $app): void
    {
        if ($app->has('massEvents')) {
            $component = $app->get('massEvents');
            if ($component instanceof MassEventLayer) {
                return;
            }
        }

        $app->set('massEvents', [
            'class' => MassEventLayer::class,
        ]);

        $app->get('massEvents');
    }
}
