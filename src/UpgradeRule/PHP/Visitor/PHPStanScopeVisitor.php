<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use Nette\DI\Container;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Broker\Broker;
use PHPStan\Rules\Registry;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * Decorates all nodes with a $scope variable
 */
class PHPStanScopeVisitor implements NodeVisitor
{
    /**
     * @var NodeScopeResolver
     */
    protected $resolver = null;

    /**
     * @var Scope
     */
    protected $scope = null;

    /**
     * @var ItemInterface
     */
    protected $file = null;

    /**
     * @var Registry
     */
    protected $registry = null;

    /**
     * @var Container
     */
    protected $container;

    public function __construct(Container $container, ItemInterface $file)
    {
        $this->container = $container;
        $this->resolver = $this->container->getByType(NodeScopeResolver::class);
        $this->registry = $this->container->getByType(Registry::class);
        $this->file = $file;
    }


    public function beforeTraverse(array $nodes)
    {
        /** @var Broker $broker */
        $broker = $this->container->getByType(Broker::class);
        /** @var Standard $printer */
        $printer = $this->container->getByType(Standard::class);
        /** @var TypeSpecifier $type */
        $type = $this->container->getByType(TypeSpecifier::class);
        // Reset scope
        $this->scope = new Scope($broker, $printer, $type, $this->file->getFullPath());
    }

    public function enterNode(Node $node)
    {
        // Pass to phpstan
        $this->resolver->processNodes(
            [$node],
            $this->scope,
            function (Node $node, Scope $scope) {
                // Record scope
                $this->scope = $scope;
                $node->setAttribute('scope', $scope);

                // Process rules for this node
                foreach ($this->registry->getRules(get_class($node)) as $rule) {
                    $rule->processNode($node, $scope);
                }
            });
    }

    public function leaveNode(Node $node)
    {
    }

    public function afterTraverse(array $nodes)
    {
    }
}
