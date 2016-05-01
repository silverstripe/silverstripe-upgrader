<?php

namespace Sminnee\Upgrader\UpgradeRule;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\NodeVisitorAbstract;
use PhpParser\BuilderFactory;

use Sminnee\Upgrader\Util\MutableSource;

/**
 * PHP-Parser Visitor to handle class renaming upgrade handler for a renamed class
 */
class RenameClassesVisitor extends NodeVisitorAbstract
{
    protected $map;
    protected $source;
    protected $used;
    protected $useStatements = [];
    protected $insertUseStatementsAfter = null;

    public function __construct(MutableSource $source, $map)
    {
        $this->source = $source;
        $this->map = $map;

        foreach ($this->map as $k => $v) {
            $baseName = substr($this->map[$k], strrpos($this->map[$k], '\\')+1);
            $this->addClassAlias($k, $baseName);
        }
    }

    protected function addClassAlias($className, $alias)
    {
        $this->classAliases[$className] = $alias;
    }

    protected function handleStringUpdate(Node $stringNode)
    {
        if (array_key_exists($stringNode->value, $this->map)) {
            $this->source->replaceNode($stringNode, "'" . $this->map[$stringNode->value] . "'");
        }
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

        if (array_key_exists($className, $this->map)) {
            $baseName = substr($this->map[$className], strrpos($this->map[$className], '\\')+1);
            $this->useStatements[$this->map[$className]] = $baseName;
        }

        if (array_key_exists($className, $this->classAliases)) {
            $this->source->replaceNode(
                $classNode,
                new Name([ $this->classAliases[$className] ])
            );
        }

        return $classNode;
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

        // Static method calls
        if ($node instanceof Expr\StaticCall) {
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

            if ($this->insertUseStatementsAfter !== null) {
                $this->source->insertAfter($this->insertUseStatementsAfter, $useNodes);
            } else {
                $this->source->insertBefore($this->source->getAst()[0], $useNodes);
            }
        }

        return $nodes;
    }
}
