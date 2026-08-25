<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Tests;

/**
 * Boots the package with an empty route prefix, which the shipped config
 * documents as mounting the UI at the site root.
 */
class RootPrefixTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scheduler-manager.route_prefix', '');
    }
}
