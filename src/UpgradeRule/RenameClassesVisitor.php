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

/**
 * PHP-Parser Visitor to handle class renaming upgrade handler for a renamed class
 */
class RenameClassesVisitor extends NodeVisitorAbstract
{
    protected $map;
    protected $used;

    public function __construct($map)
    {
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
            $stringNode->value = $this->map[$stringNode->value];
        }

        return $stringNode;
    }

    protected function handleNameUpdate(Node $classNode)
    {
        $className = $classNode->toString();

        if (array_key_exists($className, $this->map)) {
            $baseName = substr($this->map[$className], strrpos($this->map[$className], '\\')+1);
            $this->useStatements[$this->map[$className]] = $baseName;
        }

        if (array_key_exists($className, $this->classAliases)) {
            return new Name([ $this->classAliases[$className] ], $classNode->getAttributes());
        }

        return $classNode;
    }

    public function leaveNode(Node $node)
    {
        // Class definitions
        if ($node instanceof Stmt\Class_) {
            if ($node->extends !== null) {
                $node->extends = $this->handleNameUpdate($node->extends);
            }

            if ($node->implements !== null) {
                foreach ($node->implements as $i => $part) {
                    $node->implements[$i] = $this->handleNameUpdate($part);
                }
            }
        }

        // Static method calls
        if ($node instanceof Expr\StaticCall) {
            $node->class = $this->handleNameUpdate($node->class);
        }

        // Object instantations
        if ($node instanceof Expr\New_) {
            $node->class = $this->handleNameUpdate($node->class);
        }

        // Typed parameters
        if ($node instanceof Param && $node->type !== null) {
            $node->type = $this->handleNameUpdate($node->type);
        }

        // instanceof statements
        if ($node instanceof Expr\Instanceof_) {
            $node->class = $this->handleNameUpdate($node->class);
        }

        // Strings containing only the class name
        if ($node instanceof Scalar\String_) {
            $node = $this->handleStringUpdate($node);
        }

        // use statements need to be added to the class aliases
        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->addClassAlias($use->name->toString(), $use->alias);
            }
        }

        return $node;
    }

    public function afterTraverse(array $nodes)
    {
        $factory = new BuilderFactory;
        $useNodes = [];

        foreach ($this->useStatements as $from => $to) {
            $useNodes[] = $factory->use($from)->as($to)->getNode();
        }

        return array_merge($useNodes, $nodes);
    }
}
