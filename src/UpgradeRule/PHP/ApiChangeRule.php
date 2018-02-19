<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use Nette\DI\Container;
use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Upgrade and warn on API Changes
 */
class ApiChangeRule extends PHPUpgradeRule
{
    /**
     * @var Container
     */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }


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
        $tree = $source->getAst();
        // Perform pre-requisite serial visitations
        $this->transformWithVisitors($tree, [new NameResolver()]);
        $this->transformWithVisitors($tree, [new PHPStanScopeVisitor($this->container, $file)]);

        return $source->getModifiedString();
    }
}
