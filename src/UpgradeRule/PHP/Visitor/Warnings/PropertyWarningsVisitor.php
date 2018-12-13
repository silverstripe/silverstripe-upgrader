<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Relies on {@link SymbolContextVisitor} to annotate the
 * 'symbolContext' attribute of nodes.
 */
class PropertyWarningsVisitor extends WarningsVisitor
{
    /**
     * @var array
     */
    private $options;

    public function __construct(array $specs, MutableSource $source, ItemInterface $file, $options = [])
    {
        parent::__construct($specs, $source, $file);

        $this->options = $options;
    }

    public function matchesNode(Node $node)
    {
        // Must be property
        $isPropNode = (
            $node instanceof Property ||
            $node instanceof PropertyProperty ||
            $node instanceof PropertyFetch ||
            $node instanceof StaticPropertyFetch
        );
        if (!$isPropNode) {
            return false;
        }

        // Must have name
        if ((!$node instanceof Property) && !isset($node->name)) {
            return false;
        }

        // Don't process dynamic fetches ($obj->$someField)
        if ((!$node instanceof Property) && $node->name instanceof Variable) {
            return false;
        }

        return true;
    }

    /**
     * @param Node $node
     * @param ApiChangeWarningSpec $spec
     * @return bool
     */
    protected function matchesSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        $symbol = $spec->getSymbol();

        // ::myProperty() or MyNamespace\MyClass::myProperty()
        if (preg_match('/^(?<class>[\w\\\\]*)?::(?<property>[\w]+)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesStaticProperty($node, $matches['property']);
            }
            return $this->matchesStaticClassProperty($node, $matches['class'], $matches['property']);
        }

        // ->myProperty() or MyNamespace\MyClass->myProperty()
        if (preg_match('/^(?<class>[\w\\\\]*)?->(?<property>[\w]+)$/', $symbol, $matches)) {
            if (empty($matches['class'])) {
                return $this->matchesInstanceProperty($node, $matches['property']);
            }
            return $this->matchesInstanceClassProperty($node, $matches['class'], $matches['property']);
        }

        // myProperty()
        if (preg_match('/^(?<property>[\w]+)$/', $symbol, $matches)) {
            return $this->nodeMatchesSymbol($node, $matches['property']);
        }

        // Invalid rule
        $spec->invalidRule("Invalid property spec: {$symbol}");
        return false;
    }

    /**
     * @param Node|PropertyProperty|PropertyFetch|StaticPropertyFetch $node
     * @param ApiChangeWarningSpec $spec
     */
    protected function rewriteWithSpec(Node $node, ApiChangeWarningSpec $spec)
    {
        // Update visibility if necessary
        if ($node instanceof Property
            && !$this->options['skip-visibility']
            && !$spec->getReplacement()
        ) {
            $visibility = $spec->getVisibilityBitMask();
            if ($visibility && !self::hasVisibility($node, $visibility)) {
                $this->source->replaceNode($node, self::changeVisibility($node, $visibility));
            }
            return;
        }

        // Skip if there is no replacement
        if ($replacement = $spec->getReplacement()) {
            // Replace name only
            $this->replaceNodePart($node, $node->name, $replacement);
        }
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
        return ($node instanceof PropertyFetch || $node instanceof PropertyProperty || $node instanceof Property)
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
        return ($node instanceof PropertyFetch || $node instanceof PropertyProperty)
            && $this->nodeMatchesSymbol($node, $property);
    }
}
