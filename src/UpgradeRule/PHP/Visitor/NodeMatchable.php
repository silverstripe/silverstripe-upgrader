<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * Convenience methods for comparing nodes
 *
 * Trait NodeMatchable
 * @package SilverStripe\Upgrader\UpgradeRule\PHP\Visitor
 */
trait NodeMatchable
{
    /**
     * Check if a node matches the class and symbol
     *
     * @param Node $node
     * @param string $class FQCN
     * @param string $symbol
     * @return bool
     */
    protected function nodeMatchesClassSymbol(Node $node, $class, $symbol)
    {
        return $this->nodeMatchesSymbol($node, $symbol)
            && $this->nodeMatchesClass($node, $class);
    }

    /**
     * Extracts the name of a node, this is necessary as some node types do not store its name

     * @param Node $node
     * @return mixed
     */
    private function getNodeName(Node $node)
    {
        $string = $this->source->getNodeString($node);
        if ($node instanceof Property) {
            preg_match('/\$([^=\s]+)/', $string, $matches);
            return $matches[1];
        } elseif ($node instanceof ClassMethod) {
            preg_match('/function\s+([^\(\s]+)/', $string, $matches);
            return $matches[1];
        } elseif ($node instanceof Name) {
            return $node->getLast();
        }
        return $node->getName();
    }

    /**
     * Check if a node matches a name
     *
     * @param Node $node
     * @param string $name
     * @return bool
     */
    protected function nodeMatchesSymbol(Node $node, $name)
    {
        if (!isset($node->name)) {
            $nodeName = $this->getNodeName($node);
        } else {
            $nodeName = $node->name;

            // Don't resolve expressions e.g. $obj->{"get".$name}
            if ($nodeName instanceof Expr) {
                return false;
            }
        }

        return strcasecmp((string)$nodeName, $name) === 0;
    }

    /**
     * Check if the type of the given node matches the given class name
     *
     * @param Node $node
     * @param string $class
     * @return bool
     */
    protected function nodeMatchesClass(Node $node, $class)
    {
        // Validate all classes
        $classCandidates = $node->getAttribute('contextTypes'); // Classes this node could be
        if (empty($classCandidates)) {
            if (!$node->hasAttribute('scope')) {
                return false;
            }

            $scope = $node->getAttribute('scope');
            if (!$scope->isInClass()) {
                return false;
            }
            $distances = $scope->getClassReflection()->getClassHierarchyDistances();
            return array_key_exists($class, $distances);
        }

        // Check if any possible contexts are of the class type
        foreach ($classCandidates as $classCandidate) {
            if ($this->matchesClass($classCandidate, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given class matches the spec class
     *
     * @param string $candidate Class to test
     * @param string $class Class to test against
     * @return bool
     */
    protected function matchesClass($candidate, $class)
    {
        if (empty($candidate) || empty($class)) {
            false;
        }
        // equality will bypass classloading
        if (strcasecmp($class, $candidate) === 0) {
            return true;
        }
        // Check if subclass
        if (class_exists($class) && class_exists($candidate) && is_a($candidate, $class, true)) {
            return true;
        }
        return false;
    }


    /**
     * Is static, and matches class and property name
     *
     * @param Node $node
     * @param string $class FQCN
     * @param string $property
     * @return bool
     */
    protected function matchesStaticClassProperty(Node $node, $class, $property)
    {
        return ($node instanceof StaticPropertyFetch || $node instanceof PropertyProperty || $node instanceof Property)
            && $this->nodeMatchesClassSymbol($node, $class, $property);
    }

    /**
     * Is instance, matches class and property name
     *
     * @param Node $node
     * @param string $class
     * @param string $property
     * @return bool
     */
    protected function matchesInstanceClassProperty(Node $node, $class, $property)
    {
        return ($node instanceof PropertyFetch || $node instanceof PropertyProperty
                || $node instanceof Property || $node instanceof ClassMethod)
            && $this->nodeMatchesClassSymbol($node, $class, $property);
    }

    /**
     * Is static, and matches property name
     *
     * @param Node $node
     * @param string $property
     * @return bool
     */
    protected function matchesStaticProperty(Node $node, $property)
    {
        return ($node instanceof StaticPropertyFetch || $node instanceof PropertyProperty || $node instanceof Property)
            && $this->nodeMatchesSymbol($node, $property);
    }

    /**
     * Is instance, and matches property name
     *
     * @param Node $node
     * @param string $property
     * @return bool
     */
    protected function matchesInstanceProperty(Node $node, $property)
    {
        return ($node instanceof PropertyFetch || $node instanceof PropertyProperty || $node instanceof Property)
            && $this->nodeMatchesSymbol($node, $property);
    }
}
