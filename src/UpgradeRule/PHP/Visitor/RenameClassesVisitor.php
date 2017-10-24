<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * PHP-Parser Visitor to handle class renaming upgrade handler for a renamed class
 */
class RenameClassesVisitor extends NodeVisitorAbstract
{
    use VisitorTrait;

    protected $map;
    protected $source;
    protected $used;
    protected $useStatements = [];
    protected $classAliases;

    /**
     * Position in file to insert use statements at
     *
     * @var int
     */
    protected $insertUseStatementsAfter = null;

    /**
     * List of config strings to ignore rewriting
     *
     * @var array
     */
    protected $skipConfigs;

    public function __construct(MutableSource $source, $map, $skipConfigs = [])
    {
        $this->source = $source;
        $this->map = $map;
        $this->skipConfigs = $skipConfigs;

        foreach ($this->map as $k => $v) {
            $slashPos = strrpos($this->map[$k], '\\');
            $baseName = ($slashPos === false) ? $this->map[$k] : substr($this->map[$k], $slashPos + 1);
            $this->addClassAlias($k, $baseName);
        }
    }

    protected function addClassAlias($className, $alias)
    {
        // @todo - Not used?
        $this->classAliases[$className] = $alias;
    }

    /**
     * Check string node for replacement
     *
     * @param Scalar\String_ $stringNode
     */
    protected function handleStringUpdate(Scalar\String_ $stringNode)
    {
        $replacement = $this->getReplacementClass($stringNode->value);
        if ($replacement) {
            if (! $this->isNodeRewritable($stringNode)) {
                return;
            }

            // Substitute MyClass::class literal in place of string
            $baseName = $this->logUseStatement($replacement);
            $replacementNode = new Expr\ClassConstFetch(new Name([ $baseName ]), 'class');
            $this->source->replaceNode($stringNode, $replacementNode);
        }
    }

    /**
     * Return the fully-qualified classname to use instead of the given one
     *
     * @param string $className
     * @return string
     */
    protected function getReplacementClass($className)
    {
        // Regular remapping
        if (array_key_exists($className, $this->map)) {
            return $this->map[$className];
        }
        return null;
    }

    /**
     * Log a use statement for the given fully-qualified class name
     *
     * @param string $className
     * @return string
     */
    protected function logUseStatement($className)
    {
        $slashPos = strrpos($className, '\\');
        $baseName = ($slashPos === false) ? $className : substr($className, $slashPos + 1);
        $this->useStatements[$className] = $baseName;

        return $baseName;
    }

    /**
     * Check if the given node should be replaced
     *
     * @param Node $classNode
     */
    protected function handleNameUpdate(Node $classNode)
    {
        if ($classNode instanceof Expr\StaticPropertyFetch || $classNode instanceof Expr\PropertyFetch) {
            return;
        }

        if (!$classNode instanceof Node\Name) {
            echo get_class($classNode) . "\n";
            echo " - WARNING: New class instantied by a dynamic value on line "
                . $classNode->getAttribute('startLine') . "\n";
            return;
        }

        $className = $classNode->toString();

        // Check if a replacement exists
        $replacement = $this->getReplacementClass($className);
        if (!$replacement) {
            return;
        }

        // Detect if this node is in a blacklist (e.g. belongs to a private static array key)
        if (! $this->isNodeRewritable($classNode)) {
            return;
        }

        $baseName = $this->logUseStatement($replacement);
        $this->source->replaceNode($classNode, new Name([ $baseName ]));
    }

    /**
     * Check if the given node should be re-written.
     * These sets of conditions are semi hard-coded:
     *  - Non-string class literal, or
     *  - Not a config in skipConfigs, and
     *  - Not an array key in any context, and
     *  - Not a const
     *
     * Note: This method relies on {@see ParentConnector}
     *
     * @param Node $node
     * @return bool
     */
    protected function isNodeRewritable(Node $node)
    {
        // Always rewrite non-string class literals
        if (! $node instanceof Scalar\String_) {
            return true;
        }

        // Check context of this string by parent tree
        $parent = $node->getAttribute('parent');

        // Const strings aren't rewritten
        if ($parent instanceof Node\Const_) {
            return false;
        }

        // If this is a key in an array, then don't rewrite
        if ($parent instanceof Expr\ArrayItem && $parent->key === $node) {
            return false;
        }

        // Check if there are config options that should be skipped
        if ($this->skipConfigs) {
            $config = $this->detectConfigOption($node);
            if ($config && in_array($config, $this->skipConfigs)) {
                return false;
            }
        }

        // Validate that node doesn't have @skipUpgrade in a comment somewhere
        if ($this->detectSkipUpgrade($node)) {
            return false;
        }

        return true;
    }

    /**
     * Determine the config option this node belongs to, and return it if found.
     *
     * @param Node $node
     * @return string Name of config option, or null if not a config
     */
    protected function detectConfigOption(Node $node = null)
    {
        if (!$node) {
            return null;
        }

        // If we've found a property, inspect its name and type
        if ($node instanceof Stmt\PropertyProperty) {
            // Since multiple properties can be declared against a single 'private static' advance up one level
            $property = $node->getAttribute('parent');
            if (!$property instanceof Stmt\Property) {
                throw new \InvalidArgumentException("Could not parse PropertyProperty without a parent Property");
            }
            if ($property->isPrivate() && $property->isStatic()) {
                return (string)$node->name;
            }
        }

        // Recurse up the stack
        $parent = $node->getAttribute('parent');
        return $this->detectConfigOption($parent);
    }

    public function leaveNode(Node $node)
    {
        // Class definitions
        if ($node instanceof Stmt\Class_) {
            if ($node->extends !== null) {
                $this->handleNameUpdate($node->extends);
            }

            if ($node->implements !== null) {
                foreach ($node->implements as $i => $part) {
                    $this->handleNameUpdate($part);
                }
            }
        }

        // Interface definitions
        if ($node instanceof Stmt\Interface_) {
            if ($node->extends !== null) {
                foreach ($node->extends as $i => $part) {
                    $this->handleNameUpdate($part);
                }
            }
        }

        // Trait uses
        if ($node instanceof Stmt\TraitUse) {
            if ($node->traits !== null) {
                foreach ($node->traits as $i => $part) {
                    $this->handleNameUpdate($part);
                }
            }
        }

        // Static method calls
        if ($node instanceof Expr\StaticCall) {
            $this->handleNameUpdate($node->class);
        }

        if ($node instanceof Expr\StaticPropertyFetch) {
            $this->handleNameUpdate($node->class);
        }

        if ($node instanceof Expr\ClassConstFetch) {
            $this->handleNameUpdate($node->class);
        }

        // Object instantations
        if ($node instanceof Expr\New_) {
            $this->handleNameUpdate($node->class);
        }

        // Typed parameters
        if ($node instanceof Param && $node->type instanceof Node) {
            $this->handleNameUpdate($node->type);
        }

        // instanceof statements
        if ($node instanceof Expr\Instanceof_) {
            $this->handleNameUpdate($node->class);
        }

        // catch statements
        if ($node instanceof Stmt\Catch_) {
            $this->handleNameUpdate($node->type);
        }

        // Strings containing only the class name
        if ($node instanceof Scalar\String_) {
            $this->handleStringUpdate($node);
        }

        // Defer the insertion of new use statements until after all other namespace or use statements.
        if ($node instanceof Stmt\Namespace_
            || $node instanceof Stmt\Use_
            || $node instanceof Stmt\GroupUse
        ) {
            // Put any new aliases after existing `use` statements. +1 for trailing `;`
            $this->insertUseStatementsAfter = max(
                $this->insertUseStatementsAfter,
                $node->getAttribute('endFilePos') + 1
            );
        }

        if ($node instanceof Stmt\Use_) {
            $mod = false;
            foreach ($node->uses as $i => $useuse) {
                $sourceClass = $useuse->name->toString();
                if (!empty($this->map[$sourceClass])) {
                    unset($node->uses[$i]);
                    $mod = true;
                }
            }
            if ($mod) {
                $this->source->replaceNode($node, $node->uses ? $node : '');
            }
        }

        return $node;
    }

    public function afterTraverse(array $nodes)
    {
        if ($this->useStatements) {
            $factory = new BuilderFactory;
            $useNodes = [];
            foreach ($this->useStatements as $from => $to) {
                $useNodes[] = $factory->use($from)->as($to)->getNode();
            }

            // Insert use statements
            $useNodesStr = "\n" . $this->source->createString($useNodes);
            $this->source->insert($this->getUseStatementInsertPosition(), $useNodesStr);
        }

        return $nodes;
    }

    /**
     * Get position to insert new use statements at
     *
     * @return int
     */
    protected function getUseStatementInsertPosition()
    {
        if ($this->insertUseStatementsAfter) {
            return $this->insertUseStatementsAfter;
        }

        return strlen("<?php\n");
    }
}
