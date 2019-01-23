<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Fixes class literals that were once in non-namespaced classes:
 *  - Top level classes are added with `use \<classname>`
 *  - Classes in the same namespace have the namespace prefix removed
 *
 * Unlike RenameClassesVisitor this only fixes class literals, not strings
 */
class ClassQualifierVisitor extends NameResolver
{
    /**
     * Ignore Name instances with these values
     *
     * @var array
     */
    protected $specialNames = [
        'self',
        'parent',
        'static',
        'bool',
        'boolean',
        'integer',
        'float',
        'double',
        'string',
        'array',
        'object',
        'callable',
        'iterable',
        'resource',
        'null',
    ];

    /**
     * Source file to modify
     *
     * @var MutableSource
     */
    protected $source = null;

    /**
     * Namespace for this file
     *
     * @var string
     */
    protected $newNamespace = null;

    /**
     * List of already-added 'use' declarations.
     * [ Alias => FQN ]
     *
     * @var array
     */
    protected $existingAliases = [];

    /**
     * List of new 'use' declarations to add.
     * [ Alias => FQN ]
     *
     * @var array
     */
    protected $newAliases = [];

    /**
     * Get location to insert namespace aliases
     *
     * @var int
     */
    protected $insertUseStatementsAfter = 0;

    /**
     * List of classes defined in this file
     *
     * @var array
     */
    protected $fileClasses = [];

    public function __construct(MutableSource $source, $namespace, $classes, $insertAt)
    {
        $this->fileClasses = $classes;
        $this->source = $source;
        $this->newNamespace = $namespace;
        $this->insertUseStatementsAfter = $insertAt;
    }

    protected function addAlias(Node\Stmt\UseUse $use, $type, Node\Name $prefix = null)
    {
        parent::addAlias($use, $type, $prefix);

        // Record existing aliases
        $parts = (string)$use->name;
        $alias = (string)$use->alias;
        $this->existingAliases[$alias] = $parts;
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        // Check last use statement to add new uses to
        if ($node instanceof Node\Stmt\Namespace_
            || $node instanceof Node\Stmt\Use_
            || $node instanceof Node\Stmt\GroupUse
        ) {
            // Put any new aliases after existing `use` statements. +1 for trailing `;`
            $this->insertUseStatementsAfter = max(
                $this->insertUseStatementsAfter,
                $node->getAttribute('endFilePos') + 1
            );
        }
    }

    /**
     * Rewrite literal class
     *
     * @param Node\Name $node
     * @return Node\Name
     */
    protected function resolveClassName(Node\Name $node)
    {
        // Follow core name resolver to fully qualify this name
        $node = parent::resolveClassName($node);

        // Record alias for un-namespaced classes
        /** @var Node\Name $node */
        if (count($node->parts) === 1) {
            $name = $node->toString();

            // If this class is declared in this file, then don't alias,
            // as it will continue to work when applied to the same namespace
            // Classes declared in other files will, however, need a `use` alias.
            if (!in_array(strtolower($name), $this->specialNames) && !in_array($name, $this->fileClasses)) {
                $this->newAliases[$name] = $name;
            }
        }

        // Remove redundant aliases for classes in the same namespace
        if (count($node->parts) > 1) {
            // Check namespace for this item
            $parts = $node->parts;
            $className = array_pop($parts);
            $namespace = implode('\\', $parts);

            // Remove namespace from this since it's no longer necessary
            if ($namespace === $this->newNamespace) {
                // Note; Not actually returned.
                // We want to rewrite the source, not actually modify the current AST.
                $newNode = new Node\Name($className);
                $this->source->replaceNode($node, $newNode);
            }
        }
        return $node;
    }

    public function afterTraverse(array $nodes)
    {
        // Set all namespaces
        $useNodes = [];
        $factory = new BuilderFactory();
        foreach ($this->newAliases as $alias => $class) {
            // Check for conflict
            if (isset($this->existingAliases[$alias])) {
                $existing = $this->existingAliases[$alias];

                // Theoretically shouldn't occur; Existing namespaced classes should be skipped even if aliased.
                if ($existing !== $class) {
                    throw new \LogicException(
                        "Cannot redeclare alias \"{$alias}\" from class \"{$existing}\" to \"{$class}\""
                    );
                }
                continue;
            }

            // Build node
            $useNodes[] = $factory->use($class)->as($alias)->getNode();
        }

        // Merge string with source file
        $useNodesStr = "\n" . $this->source->createString($useNodes);
        $this->source->insert($this->insertUseStatementsAfter, $useNodesStr);
    }
}
