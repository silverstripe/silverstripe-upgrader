<?php

namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\AbstractUpgradeRule;

/**
 * Simple mock upgrade rule to be used in test of other system components
 */
class MockUpgradeRule extends AbstractUpgradeRule
{
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!empty($this->parameters['prefix'])) {
            $contents = $this->parameters['prefix'] . $contents;
        }

        if (!empty($this->parameters['warning'])) {
            $changeset->addWarning(
                $file->getPath(),
                $this->parameters['warning'][0],
                $this->parameters['warning'][1]
            );
        }

        return $contents;
    }
}
