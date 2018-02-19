<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use Nette\DI\Container;
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

        // Convert warnings to proper spec objects
        $warnings = isset($this->parameters['warnings']) ? $this->parameters['warnings'] : [];
        $classWarnings = $this->transformSpec(isset($warnings['classes']) ? $warnings['classes'] : []);
        $methodWarnings = $this->transformSpec(isset($warnings['methods']) ? $warnings['methods'] : []);
        $functionWarnings = $this->transformSpec(isset($warnings['functions']) ? $warnings['functions'] : []);
        $constantWarnings = $this->transformSpec(isset($warnings['constants']) ? $warnings['constants'] : []);
        $propWarnings = $this->transformSpec(isset($warnings['props']) ? $warnings['props'] : []);

        // Prepare mutable source with AST
        $source = new MutableSource($contents);
        $tree = $source->getAst();

        // Perform pre-requisite serial visitations
        $this->transformWithVisitors($tree, [new NameResolver()]);
        $this->transformWithVisitors($tree, [new PHPStanScopeVisitor($this->container, $file)]);

        // Rule helper
        /** @var RuleLevelHelper $ruleLevelHelper */
        $ruleLevelHelper = $this->container->getByType(RuleLevelHelper::class);

        // Perform parallel visitations based on upgrade rules
        $visitors = [
            //new ClassWarningsVisitor($classWarnings, $file),
            new SymbolContextVisitor($ruleLevelHelper),
            new MethodWarningsVisitor($methodWarnings, $file),
            //new FunctionWarningsVisitor($functionWarnings, $file),
            //new ConstantWarningsVisitor($constantWarnings, $file),
            //new PropertyWarningsVisitor($propWarnings, $file)
        ];
        $this->transformWithVisitors($tree, $visitors);

        // Save all warnings from visitors
        foreach ($visitors as $visitor) {
            if ($visitor instanceof ContainsWarnings) {
                $this->addWarningsFromVisitor($file, $visitor, $changeset);
            }
        }

        return $source->getModifiedString();
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
}
