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
        Packagist::addCacheFolder('/tmp/silverstripe-upgrader-cache');
        Packagist::addCacheFolder(realpath('~/.cache/composer/repo/https---packagist.org'));

        parent::setUp();
    }

    protected function tearDown()
    {
        Packagist::disableCacheFolders();

        parent::tearDown();
    }

}
