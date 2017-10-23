<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\NodeVisitor\NameResolver;

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
 * Since the PHP syntax parser doesn't actually understand symbol relationships,
 * and doesn't resolve dependencies and hierarchies, this isn't a very reliable
 * methodology, and should only be used for warnings rather than code rewrites.
 *
 * @package SilverStripe\Upgrader\Util
 */
class SymbolContextVisitor extends NameResolver
{

    /**
     * @var Node[]
     */
    protected $symbols = [];

    /**
     * @var array
     */
    protected $uses = [];

    /**
     * @var string[] Will be reset when exiting method scope
     */
    protected $methodClasses = [];

    /**
     * @var string Current class context
     */
    protected $parentClass;

    /**
     * @var string
     */
    protected $namespace;

    public function beforeTraverse(array $nodes)
    {
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Namespace_) {
            $this->namespace = implode('\\', $node->name->parts);
        }

        if ($node instanceof Class_) {
            $this->parentClass = implode(
                '\\',
                array_filter([$this->namespace, $node->name])
            );
        }

        if ($node instanceof Use_) {
            foreach ($node->uses as $use) {
                $this->uses[] = implode('\\', $use->name->parts);
            }
        }

        $staticClass = null;
        if ($node instanceof StaticCall || $node instanceof StaticPropertyFetch) {
            $staticClass = $this->getClass($node);
            $this->methodClasses[] = $staticClass;
        }

        if ($node instanceof New_) {
            $this->methodClasses[] = $this->getClass($node);
        }

        // Limit metadata to nodes we're actually interested in
        // within other visitors
        $isSymbolNode = (
            $node instanceof MethodCall ||
            $node instanceof StaticCall ||
            $node instanceof Class_ ||
            $node instanceof PropertyProperty ||
            $node instanceof PropertyFetch ||
            $node instanceof StaticPropertyFetch ||
            $node instanceof New_
        );

        if ($isSymbolNode) {
            $context = [
                'namespace' => $this->namespace,
                'uses' => $this->uses,
                'class' => $this->parentClass,
                'staticClass' => $staticClass,
                'methodClasses' => $this->methodClasses
            ];
            $node->setAttribute('symbolContext', $context);
            $this->symbols[] = $node;
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Class_) {
            $this->parentClass = null;
        }

        if ($node instanceof ClassMethod) {
            $this->methodClasses = [];
        }
    }

    public function afterTraverse(array $nodes)
    {
    }

    /**
     * @return Node[]
     */
    public function getSymbols()
    {
        return $this->symbols;
    }

    /**
     * Namespaces are inlined via NameResolver parent class already.
     *
     * @param Node $node
     * @return String
     */
    public function getClass(Node $node)
    {
        $class = '';

        if (isset($node->class->parts)) {
            $class = implode('\\', $node->class->parts);
        } else {
            $class = $node->class->name;
        }

        return $class;
    }
}
