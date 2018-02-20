<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\NodeVisitor;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleLevelHelper;

/**
 * Accumulates symbols found in a class that might be meaningful
 * in a fuzzy use matching. For example, if the file contains
 * a "use SilverStripe\Forms\GridField\GridField" statement,
 * and later references "$myField->someRemovedMethod()",
 * we can infer that a rule with "GridField->someRemovedMethod()"
 * likely applies.
 *
 * The context can change for each node in an AST, for example a static method
 * call to "MyClass::myMethod()" will set $staticInvokedClass,
 * and invoke it after finishing processing of the method call node.
 *
 * Since the PHP syntax parser works sequentially, it will only determine
 * context that's defined before the symbol in question is used.
 *
 * List of inferred types for any node can be retrieved with $node->getAttribute('contextTypes')
 */
class SymbolContextVisitor implements NodeVisitor
{
    /**
     * @var RuleLevelHelper
     */
    protected $ruleLevelHelper;

    /**
     * Build context decorator with a phpstan rule helper for added context extraction
     *
     * @param RuleLevelHelper $ruleLevelHelper
     */
    public function __construct(RuleLevelHelper $ruleLevelHelper)
    {
        $this->ruleLevelHelper = $ruleLevelHelper;
    }

    public function beforeTraverse(array $nodes)
    {
    }

    public function enterNode(Node $node)
    {
        if (!$node->hasAttribute('scope')) {
            throw new LogicException("PHPStan not properly initialised");
        }

        /** @var Scope $scope */
        $scope = $node->getAttribute('scope');

        // Infer types from phpstan
        $types = [];
        if ($node instanceof MethodCall ||
            $node instanceof PropertyFetch
        ) {
            // Resolve $var->something() types
            $types = $this->resolveExpressionTypes($scope, $node->var);
        } elseif ($node instanceof StaticCall ||
            $node instanceof StaticPropertyFetch ||
            $node instanceof ClassConstFetch
        ) {
            // Resolve Class::something() types
            $types = $this->getStaticClasses($scope, $node);
        }

        // Set all types (even if empty)
        $node->setAttribute('contextTypes', $types);
    }

    public function leaveNode(Node $node)
    {
    }

    public function afterTraverse(array $nodes)
    {
    }

    /**
     * Namespaces are inlined via NameResolver parent class already.
     *
     * Basically reverse engineered from phpstan
     * @see \PHPStan\Rules\Classes\ClassConstantRule
     *
     * @param Scope $scope
     * @param Node|StaticCall|StaticPropertyFetch|ClassConstFetch $node
     * @return array
     */
    public function getStaticClasses(Scope $scope, Node $node)
    {
        // Check node class. If variable, delegate to variable lookup
        $classNode = $node->class;
        if (!$classNode instanceof Node\Name) {
            return $this->resolveExpressionTypes($scope, $classNode);
        }

        // Resolve name to literal class
        $class = $classNode->toString();

        // Resolve static prefixes
        switch ($class) {
            case 'self':
            case 'static':
                return [$scope->getClassReflection()->getName()];
            case 'parent':
                return [$scope->getClassReflection()->getParentClass()->getName()];
            default:
                return [$class];
        }
    }

    /**
     * Get list of clasess for a named variable node
     *
     * Basically reverse engineered from phpstan
     * @see \PHPStan\Rules\Methods\CallMethodsRule
     *
     * @param Scope $scope
     * @param Expr $expression Any expression to type. Could be a variable, or a complex expression
     * @return string[] List of resolved candidate classes
     */
    protected function resolveExpressionTypes($scope, $expression)
    {
        return $this
            ->ruleLevelHelper
            ->findTypeToCheck($scope, $expression, "Could not resolve expression to type")
            ->getType()
            ->getReferencedClasses();
    }
}
