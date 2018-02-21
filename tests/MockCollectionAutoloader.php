<?php

namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\Autoload\CollectionAutoloader;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Autoloads mock code items that may not actually exist on disk
 */
class MockCollectionAutoloader extends CollectionAutoloader
{
    /**
     * Array of file sha1 that have been loaded
     *
     * @var array
     */
    protected static $evaled = [];

    protected function loadItem(ItemInterface $file, $class)
    {
        // Only override mock item loading
        if (!$file instanceof MockCodeItem) {
            return parent::loadItem($file, $class);
        }

        // Get file contents, trimming leading `<?php`
        $contents = $file->getContents();
        $this->evalOnce($contents);
        return class_exists($class, false);
    }

    /**
     * Eval a string, but only once this process
     *
     * @param string $contents
     */
    protected function evalOnce($contents)
    {
        // Can't eval leading <?php
        if (stripos($contents, '<?php') === 0) {
            $contents = trim(substr($contents, 6));
        }
        // Check if already evaled
        $key = sha1($contents);
        if (isset(static::$evaled[$key])) {
            return;
        }
        static::$evaled[$key] = true;
        eval($contents);
    }
}
