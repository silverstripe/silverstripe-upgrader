<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use PhpParser\NodeTraverser;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\AbstractRule;

/**
 * An upgrader, that generates a change for a given PHP file
 */
abstract class PHPUpgradeRule extends AbstractRule
{
    /**
     * Apply the given visitors to the given code, returning new code
     *
     * @param array $ast
     * @param array $visitors
     * @return string
     */
    protected function transformWithVisitors(array $ast, array $visitors)
    {
        $traverser = new NodeTraverser;
        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $traverser->traverse($ast);
    }

    /**
     * Returns true if this upgrad rule applies to the given file
     * Checks fileExtensions parameters
     *
     * @param ItemInterface $file
     * @return bool
     */
    public function appliesTo(ItemInterface $file)
    {
        if (empty($this->parameters['fileExtensions'])) {
            return true;
        }

        if (preg_match('/[^\/]*\.(.*)$/', $file->getPath(), $matches)) {
            $extension = $matches[1];
        } else {
            $extension = '';
        }

        return in_array($extension, $this->parameters['fileExtensions']);
    }
}
