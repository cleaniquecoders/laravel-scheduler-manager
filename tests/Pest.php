<?php

use CleaniqueCoders\LaravelSchedulerManager\Tests\RootPrefixTestCase;
use CleaniqueCoders\LaravelSchedulerManager\Tests\TestCase;
use CleaniqueCoders\LaravelSchedulerManager\Tests\UiDisabledTestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');

/*
 * Provider wiring that has to be settled before the application boots cannot be
 * exercised by flipping config inside a test, so those scenarios get their own
 * base cases.
 */
uses(UiDisabledTestCase::class)->in(__DIR__.'/Boot/UiDisabledTest.php');
uses(RootPrefixTestCase::class)->in(__DIR__.'/Boot/RootPrefixTest.php');
