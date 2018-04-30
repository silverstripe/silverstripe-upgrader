<?php
namespace SilverStripe\Upgrader\Tests\Composer;

use SilverStripe\Upgrader\Composer\Packagist;

/**
 * Apply this trait to your test if your want to use the packagist cache.
 */
trait InitPackageCacheTrait
{

    protected function setUp()
    {
        if (!file_exists('/tmp/silverstripe-upgrader-cache')) {
            mkdir('/tmp/silverstripe-upgrader-cache');
        }

        // Create our own fake cache in the tmp folder so we can speed up our package test
        Packagist::addCacheFolder('/tmp/silverstripe-upgrader-cache');

        // Add a fixture folder. This will allow us to have a fix package cache and keep our unit test results
        // consistentish
        Packagist::addCacheFolder(__DIR__ . '/fixture/composer-cache');

        // Add the real composer cache in here, this should speed up test by allowing us to piggy back off composer.
        Packagist::addCacheFolder(realpath('~/.cache/composer/repo/https---packagist.org'));

        parent::setUp();
    }

    protected function tearDown()
    {
        Packagist::disableCacheFolders();

        parent::tearDown();
    }
}
