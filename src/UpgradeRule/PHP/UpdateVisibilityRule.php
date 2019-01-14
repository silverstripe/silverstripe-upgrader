<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use Nette\DI\Container;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\Rules\RuleLevelHelper;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\SymbolContextVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\VisibilityVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Update property and method visibilities
 */
class UpdateVisibilityRule extends PHPUpgradeRule
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * UpdateVisibilityRule constructor.
     * @param Container $container
     * @param array $options
     */
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
        $source = new MutableSource($contents);

        $ruleLevelHelper = $this->container->getByType(RuleLevelHelper::class);
        $tree = $source->getAst();
        $this->transformWithVisitors($tree, [new NameResolver()]);
        $this->transformWithVisitors($tree, [new PHPStanScopeVisitor($this->container, $file)]);
        $this->transformWithVisitors($tree, [new SymbolContextVisitor($ruleLevelHelper)]);
        $this->transformWithVisitors($tree, [
            new VisibilityVisitor($source, $this->parameters['visibilities'])
        ]);

        return $source->getModifiedString();
    }
}
