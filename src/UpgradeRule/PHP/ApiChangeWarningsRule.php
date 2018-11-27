<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use Nette\DI\Container;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\Rules\RuleLevelHelper;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\SymbolContextVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\ClassWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\ConstantWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\FunctionWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\MethodWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\PropertyWarningsVisitor;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\WarningsVisitor;
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

    /**
     * @var array
     */
    private $options;

    /**
     * ApiChangeWarningsRule constructor.
     * @param Container $container
     * @param array $options
     */
    public function __construct(Container $container, array $options = [])
    {
        $this->container = $container;
        $this->options = $options;
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

        // Build all rules from warnings config
        $visitors = $this->buildRuleVisitors($source, $file);

        // Mutate (note: public for testing)
        $this->mutateSourceWithVisitors($source, $file, $visitors);

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
     * @param MutableSource $source File contents
     * @param ItemInterface $file File container
     * @param NodeVisitor[] $visitors
     */
    public function mutateSourceWithVisitors(MutableSource $source, ItemInterface $file, $visitors)
    {
        // Rule helper
        /** @var RuleLevelHelper $ruleLevelHelper */
        $ruleLevelHelper = $this->container->getByType(RuleLevelHelper::class);

        // Perform pre-requisite serial visitations
        $tree = $source->getAst();
        $this->transformWithVisitors($tree, [new NameResolver()]);
        $this->transformWithVisitors($tree, [new PHPStanScopeVisitor($this->container, $file)]);
        $this->transformWithVisitors($tree, [new SymbolContextVisitor($ruleLevelHelper)]);
        $this->transformWithVisitors($tree, $visitors);
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
            $out[] = new ApiChangeWarningSpec($symbol, $spec);
        }
        return $out;
    }

    /**
     * Get list of warning visitors to check for this config
     *
     * @param MutableSource $source
     * @param ItemInterface $file
     * @return WarningsVisitor[]
     */
    protected function buildRuleVisitors(MutableSource $source, ItemInterface $file)
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
            $visitors[] = new ClassWarningsVisitor($warnings['classes'], $source, $file);
        }
        if (isset($warnings['methods'])) {
            $visitors[] = new MethodWarningsVisitor($warnings['methods'], $source, $file);
        }
        if (isset($warnings['functions'])) {
            $visitors[] = new FunctionWarningsVisitor($warnings['functions'], $source, $file);
        }
        if (isset($warnings['constants'])) {
            $visitors[] = new ConstantWarningsVisitor($warnings['constants'], $source, $file);
        }
        if (isset($warnings['props'])) {
            $visitors[] = new PropertyWarningsVisitor($warnings['props'], $source, $file, $this->options);
        }

        return $visitors;
    }
}
