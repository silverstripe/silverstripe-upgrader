<?php

namespace SilverStripe\Upgrader\UpgradeRule;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\NodeVisitorAbstract;
use PhpParser\BuilderFactory;

use SilverStripe\Upgrader\Util\MutableSource;

/**
 * PHP-Parser Visitor to handle class renaming upgrade handler for a renamed class
 */
class RenameClassesVisitor extends NodeVisitorAbstract
{
    protected $map;
    protected $namespaceCorrections;
    protected $source;
    protected $used;
    protected $useStatements = [];
    protected $insertUseStatementsAfter = null;

    public function __construct(MutableSource $source, $map, $namespaceCorrections = null)
    {
        $this->source = $source;
        $this->map = $map;
        $this->namespaceCorrections = $namespaceCorrections;

        foreach ($this->map as $k => $v) {
            $slashPos = strrpos($this->map[$k], '\\');
            $baseName = ($slashPos === false) ? $this->map[$k] : substr($this->map[$k], $slashPos + 1);
            $this->addClassAlias($k, $baseName);
        }
    }

    protected function addClassAlias($className, $alias)
    {
        $this->classAliases[$className] = $alias;
    }

    protected function handleStringUpdate(Node $stringNode)
    {
        $replacement = $this->getReplacementClass($stringNode->value);
        if ($replacement !== null) {
            $this->source->replaceNode($stringNode, "'$replacement'");
        }
    }

    /**
     * Return the fully-qualified classname to use instead of the given one
     */
    protected function getReplacementClass($className)
    {
        // Regular remapping
        if (array_key_exists($className, $this->map)) {
            return $this->map[$className];
        }

        // Classes within namespaces to be corrected
        if ($this->namespaceCorrections) {
            $slashPos = strrpos($className, '\\');
            if ($slashPos !== false) {
                $namespace = substr($className, 0, $slashPos);

                if (array_key_exists($namespace, $this->namespaceCorrections)
                && !in_array($className, $this->namespaceCorrections[$namespace])) {
                    // Remove the namespace - it has been added erroneously when shifting classes between namespaces

                    return substr($className, $slashPos+1);
                }
            }
        }

        return null;
    }

    /**
     * Log a use statement for the given fully-qualified class name
     */
    protected function logUseStatement($className)
    {
        $slashPos = strrpos($className, '\\');
        $baseName = ($slashPos === false) ? $className : substr($className, $slashPos + 1);
        $this->useStatements[$className] = $baseName;

        return $baseName;
    }

    protected function handleNameUpdate(Node $classNode)
    {
        if ($classNode instanceof Expr\StaticPropertyFetch || $classNode instanceof Expr\PropertyFetch) {
            return $classNode;
        }

        if (!$classNode instanceof Node\Name) {
            echo get_class($classNode) . "\n";
            echo " - WARNING: New class instantied by a dynamic value on line "
                . $classNode->getAttribute('startLine') . "\n";
            return $classNode;
        }

        $className = $classNode->toString();

        $replacement = $this->getReplacementClass($className);

        if ($replacement !== null) {
            $baseName = $this->logUseStatement($replacement);
            $this->source->replaceNode($classNode, new Name([ $baseName ]));
        }
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
        if ($node instanceof Stmt\Namespace_ || $node instanceof Stmt\Use_) {
            if ($this->insertUseStatementsAfter === null ||
                $node->getAttribute('startFilePos') > $this->insertUseStatementsAfter->getAttribute('startFilePos')) {
                $this->insertUseStatementsAfter = $node;
            }
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

            $useNodesStr = "\n" . $this->source->createString($useNodes);

            if ($this->insertUseStatementsAfter !== null) {
                $this->source->insertAfter($this->insertUseStatementsAfter, $useNodesStr);
            } else {
                $this->source->insertBefore($this->source->getAst()[0], $useNodesStr);
            }
        }

        return $nodes;
    }
}
