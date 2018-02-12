<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

class RewriteSymbolsRule extends PHPUpgradeRule
{

    /**
     * Upgrades the contents of the given file
     * Returns string containing the new code.
     *
     * @param string $contents
     * @param ItemInterface $file
     * @param CodeChangeSet $changeset Changeset to add warnings to
     * @return string
     */
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {

    }
}
