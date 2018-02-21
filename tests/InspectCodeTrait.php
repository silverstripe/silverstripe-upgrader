<?php


namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\Autoload\CollectionAutoloader;
use SilverStripe\Upgrader\Util\PHPStanState;

/**
 * Initialises phpstan and autoloading for inspection tests
 */
trait InspectCodeTrait
{
    /**
     * @var PHPStanState
     */
    protected $state = null;

    /**
     * @var CollectionAutoloader
     */
    protected $autoloader = null;

    protected function setUpInspect()
    {
        // Setup state and autoloading
        $this->state = new PHPStanState();
        $this->state->init();

        $this->autoloader = new MockCollectionAutoloader();
        $this->autoloader->register();
    }

    protected function tearDownInspect()
    {
        // Disable autoloader
        $this->autoloader->unregister();
        $this->autoloader->setCollections([]);
    }
}
