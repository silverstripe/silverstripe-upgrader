<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Similar to ApiChangeWarningsRule, but does actual upgrading
 */
class ApiChangeRewriteRule extends PHPUpgradeRule
{
    public function appliesTo(ItemInterface $file)
    {
        return 'php' === $file->getExtension();
    }

    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        // Technically this doesn't have to be mutable
        $source = new MutableSource($contents);

        // Convert rewrites to proper spec objects
        $rewrites = $this->parameters['rewrites'] ?? [];
        $visitors = [
            new NameResolver(),
            new PHPStanScopeVisitor($file)
        ];
        $this->transformWithVisitors($source->getAst(), $visitors);

        return $source->getModifiedString();
    }
}
