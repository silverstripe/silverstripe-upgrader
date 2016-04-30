<?php

namespace Sminnee\Upgrader\Tests;

use Sminnee\Upgrader\UpgradeRule\AbstractUpgradeRule;

/**
 * Simple mock upgrade rule to be used in test of other system components
 */
class MockUpgradeRule extends AbstractUpgradeRule
{
    public function upgradeFile($contents, $filename)
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
