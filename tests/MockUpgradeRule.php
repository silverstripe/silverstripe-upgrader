<?php

namespace SilverStripe\Upgrader\Tests;

use SilverStripe\Upgrader\UpgradeRule\AbstractUpgradeRule;

/**
 * Simple mock upgrade rule to be used in test of other system components
 */
class MockUpgradeRule extends AbstractUpgradeRule
{
    public function upgradeFile($contents, $file)
    {
        $warnings = [];
        if (!empty($this->parameters['prefix'])) {
            $contents = $this->parameters['prefix'] . $contents;
        }

        if (!empty($this->parameters['warning'])) {
            $warnings[] = $this->parameters['warning'];
        }

        return [ $contents, $warnings ];
    }
}
