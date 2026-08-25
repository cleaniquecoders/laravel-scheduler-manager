<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Tests;

/**
 * Boots the package with the management UI switched off, so the "engine only,
 * no HTTP surface" installation is exercised from a real boot rather than by
 * flipping config after the provider has already run.
 */
class UiDisabledTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scheduler-manager.ui.enabled', false);
    }
}
