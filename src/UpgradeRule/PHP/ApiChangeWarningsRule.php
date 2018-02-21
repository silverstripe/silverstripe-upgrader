<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use Nette\DI\Container;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\Rules\RuleLevelHelper;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ClassWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ConstantWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\FunctionWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\MethodWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PropertyWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\SymbolContextVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\WarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\ContainsWarnings;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Upgrade and warn on API Changes
 */
class ApiChangeWarningsRule extends PHPUpgradeRule
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

        // Build all rules from warnings config
        $visitors = $this->buildRuleVisitors($file);

        // Mutate (note: public for testing)
        $source = $this->mutateSourceWithVisitors($contents, $file, $visitors);

        // Save all warnings from visitors
        foreach ($visitors as $visitor) {
            if ($visitor instanceof ContainsWarnings) {
                $this->addWarningsFromVisitor($file, $visitor, $changeset);
            }
        }

        return $source->getModifiedString();
    }

    /**
     * Traverse the contents with the given list of visitors
     *
     * @param string $contents File contents
     * @param ItemInterface $file File container
     * @param NodeVisitor[] $visitors
     * @return MutableSource
     */
    public function mutateSourceWithVisitors($contents, ItemInterface $file, $visitors)
    {
        // Prepare mutable source with AST
        $source = new MutableSource($contents);
        $tree = $source->getAst();

        // Rule helper
        /** @var RuleLevelHelper $ruleLevelHelper */
        $ruleLevelHelper = $this->container->getByType(RuleLevelHelper::class);

        // Perform pre-requisite serial visitations
        $this->transformWithVisitors($tree, [new NameResolver()]);
        $this->transformWithVisitors($tree, [new PHPStanScopeVisitor($this->container, $file)]);
        $this->transformWithVisitors($tree, [new SymbolContextVisitor($ruleLevelHelper)]);
        $this->transformWithVisitors($tree, $visitors);

        return $source;
    }

    /**
     * @param ItemInterface $file
     * @param ContainsWarnings $visitor
     * @param CodeChangeSet $changeset
     */
    protected function addWarningsFromVisitor(ItemInterface $file, ContainsWarnings $visitor, CodeChangeSet $changeset)
    {
        foreach ($visitor->getWarnings() as $warning) {
            $changeset->addWarning(
                $file->getPath(),
                $warning->getLine(),
                $warning->getMessage()
            );
        }
    }

    /**
     * @param array $specs
     * @return ApiChangeWarningSpec[]
     */
    protected function transformSpec($specs)
    {
        $out = [];
        foreach ($specs as $symbol => $spec) {
            $url = isset($spec['url']) ? $spec['url'] : null;
            $out[] = (new ApiChangeWarningSpec($symbol, $spec['message']))->setUrl($url);
        }

        return $out;
    }

    /**
     * Get list of warning visitors to check for this config
     *
     * @param ItemInterface $file
     * @return WarningsVisitor[]
     */
    protected function buildRuleVisitors(ItemInterface $file)
    {
        // Nothing configured
        if (empty($this->parameters['warnings'])) {
            return [];
        }

        // Convert warnings to specs
        $warnings = array_map(function ($spec) {
            return $this->transformSpec($spec);
        }, $this->parameters['warnings']);

        // Build visitors based on warning types
        $visitors = [];
        if (isset($warnings['classes'])) {
            $visitors[] = new ClassWarningsVisitor($warnings['classes'], $file);
        }
        if (isset($warnings['methods'])) {
            $visitors[] = new MethodWarningsVisitor($warnings['methods'], $file);
        }
        if (isset($warnings['functions'])) {
            $visitors[] = new FunctionWarningsVisitor($warnings['functions'], $file);
        }
        if (isset($warnings['constants'])) {
            $visitors[] = new ConstantWarningsVisitor($warnings['constants'], $file);
        }
        if (isset($warnings['props'])) {
            $visitors[] = new PropertyWarningsVisitor($warnings['props'], $file);
        }

        return $visitors;
    }
}
