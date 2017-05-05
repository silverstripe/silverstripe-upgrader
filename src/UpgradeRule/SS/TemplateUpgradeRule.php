<?php
namespace SilverStripe\Upgrader\UpgradeRule\SS;

use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\AbstractRule;

abstract class TemplateUpgradeRule extends AbstractRule
{
    /**
     * Returns true if this upgrade rule applies to the given file
     * Checks fileExtensions parameters
     *
     * @param ItemInterface $file
     * @return bool
     */
    public function appliesTo(ItemInterface $file)
    {
        return preg_match('#\.ss$#', $file->getFullPath());
    }
}
