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
use PHPStan\DependencyInjection\ContainerFactory;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

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
     * @var Container
     */
    protected $container = null;

    public function __construct(ItemInterface $file)
    {
        // Setup application for this parse
        $containerFactory = new ContainerFactory(getcwd());
        $this->container = $containerFactory->create(sys_get_temp_dir(), []);
        $this->resolver = $this->container->getByType(NodeScopeResolver::class);
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
        $this->resolver->processNodes(
            [$node],
            $this->scope,
            function (Node $node, Scope $scope) {

            });
    }

    public function leaveNode(Node $node)
    {
    }

    public function afterTraverse(array $nodes)
    {
    }
}
