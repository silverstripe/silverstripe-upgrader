<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use PhpParser\NodeVisitor\NameResolver;
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
 * Fuzzy detection of used APIs based on certain markers in the code.
 * Not accurate enough to automatically rewrite code,
 * but gives the user an indication what needs manual attention.
 */
class ApiChangeWarningsRule extends PHPUpgradeRule
{

    public function appliesTo(ItemInterface $file)
    {
        return preg_match('#\.php$#', $file->getFullPath());
    }

    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        // Technically this doesn't have to be mutable
        $source = new MutableSource($contents);

        // Convert warnings to proper spec objects
        $warnings = isset($this->parameters['warnings']) ? $this->parameters['warnings'] : [];
        $classWarnings = $this->transformSpec(isset($warnings['classes']) ? $warnings['classes'] : []);
        $methodWarnings = $this->transformSpec(isset($warnings['methods']) ? $warnings['methods'] : []);
        $functionWarnings = $this->transformSpec(isset($warnings['functions']) ? $warnings['functions'] : []);
        $constantWarnings = $this->transformSpec(isset($warnings['constants']) ? $warnings['constants'] : []);
        $propWarnings = $this->transformSpec(isset($warnings['props']) ? $warnings['props'] : []);

        $visitors = [
            new NameResolver(),
            new PHPStanScopeVisitor($file),
            new SymbolContextVisitor(),
            new ClassWarningsVisitor($classWarnings, $file),
            new MethodWarningsVisitor($methodWarnings, $file),
            new FunctionWarningsVisitor($functionWarnings, $file),
            new ConstantWarningsVisitor($constantWarnings, $file),
            new PropertyWarningsVisitor($propWarnings, $file)
        ];
        $this->transformWithVisitors($source->getAst(), $visitors);

        foreach ($visitors as $visitor) {
            if (!$visitor instanceof ContainsWarnings) {
                continue;
            }

            $this->addWarningsFromVisitor($file, $visitor, $changeset);
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
