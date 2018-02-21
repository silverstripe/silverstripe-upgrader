<?php

namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\Autoload\CollectionAutoloader;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Autoloads mock code items that may not actually exist on disk
 */
class MockCollectionAutoloader extends CollectionAutoloader
{
    protected function loadItem(ItemInterface $file, $class)
    {
        // Only override mock item loading
        if (!$file instanceof MockCodeItem) {
            return parent::loadItem($file, $class);
        }

        // Get file contents, trimming leading `<?php`
        $contents = $file->getContents();
        if (stripos($contents, '<?php') === 0) {
            $contents = trim(substr($contents, 6));
        }
        eval($contents);
        return class_exists($class, false);
    }
}
