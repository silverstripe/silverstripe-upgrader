<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitor;
use SilverStripe\Upgrader\Util\MutableSource;

class PhpUnitVisitor implements NodeVisitor
{
    use VisitorTrait;

    /**
     * @var MutableSource
     */
    protected $source;

    public function __construct(MutableSource $source)
    {
        $this->source = $source;
    }

    /**
     * @param $node
     * @param int $visibility
     * @return mixed
     */
    protected static function changeVisibility($node, $visibility = Class_::MODIFIER_PROTECTED)
    {
        // remove other flags
        if ($visibility != Class_::MODIFIER_PRIVATE) {
            $node->flags = $node->flags & (~ Class_::MODIFIER_PRIVATE);
        }
        if ($visibility != Class_::MODIFIER_PROTECTED) {
            $node->flags = $node->flags & (~ Class_::MODIFIER_PROTECTED);
        }
        if ($visibility != Class_::MODIFIER_PUBLIC) {
            $node->flags = $node->flags & (~ Class_::MODIFIER_PUBLIC);
        }

        // add our flag
        $node->flags |= $visibility;

        return $node;
    }

    protected static function makeStatic($node)
    {
        $node->flags |= Class_::MODIFIER_STATIC;
        return $node;
    }

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
        // TODO: Implement beforeTraverse() method.
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            switch (strtolower($node->name)) {
                case 'setup':
                    $this->source->replaceNode($node, static::changeVisibility($node));
                    break;
                case 'teardown':
                    $this->source->replaceNode($node, static::changeVisibility($node));
                    break;
                case 'setUpBeforeClass':
                    $node = static::changeVisibility($node, Class_::MODIFIER_PUBLIC);
                    $node = static::makeStatic($node);
                    $this->source->replaceNode($node, $node);
                    break;
                case 'tearDownAfterClass':
                    $node = static::changeVisibility($node, Class_::MODIFIER_PUBLIC);
                    $node = static::makeStatic($node);
                    $this->source->replaceNode($node, $node);
                    break;
            }
            if ($docBlock = $node->getDocComment()) {
                $lines = array_reverse(explode("\n", $docBlock->getText()));
                $newComment = [];
                $update = false;
                foreach ($lines as $line) {
                    $rawLine = trim($line, "* \t\n\r\0\x0B");
                    if ($rawLine && preg_match('/^@([a-z]+)\s+(.*)/i', $rawLine, $matches)) {
                        $annotation = $matches[1];
                        $argument = $matches[2];
                        if (in_array($annotation, ['expectedException', 'expectedExceptionCode', 'expectedExceptionMessage', 'expectedExceptionMessageRegExp'])) {
                            $update = true;
                            switch ($annotation) {
                                case 'expectedException':
                                    $call = new Node\Expr\MethodCall(
                                        new Node\Expr\Variable('this'),
                                        'expectedException',
                                        [
                                            new Node\Arg(new Node\Expr\ClassConstFetch(
                                                new Node\Name($argument),
                                                'class'
                                            )),
                                        ]
                                    );
                                    array_unshift($node->stmts, $call);
                                    break;
                                case 'expectedExceptionMessageRegExp':
                                    $call = new Node\Expr\MethodCall(
                                        new Node\Expr\Variable('this'),
                                        'expectExceptionMessageMatches',
                                        [
                                            new Node\Arg(new Node\Scalar\String_($argument)),
                                        ]
                                    );
                                    array_unshift($node->stmts, $call);
                                    break;
                                case 'expectedExceptionCode':
                                    $call = new Node\Expr\MethodCall(
                                        new Node\Expr\Variable('this'),
                                        'expectedExceptionCode',
                                        [
                                            new Node\Arg(new Node\Scalar\String_($argument)),
                                        ]
                                    );
                                    array_unshift($node->stmts, $call);
                                    break;
                                case 'expectedExceptionMessage':
                                    $call = new Node\Expr\MethodCall(
                                        new Node\Expr\Variable('this'),
                                        'expectedExceptionMessage',
                                        [
                                            new Node\Arg(new Node\Scalar\String_($argument)),
                                        ]
                                    );
                                    array_unshift($node->stmts, $call);
                                    break;
                            }
                        } else {
                            $newComment[] = $line;
                        }
                    } else {
                        $newComment[] = $line;
                    }
                }
                if ($update) {
                    if (count($newComment) === 2) {
                        // empty comment
                        $comment = new Doc('', $docBlock->getLine(), $docBlock->getFilePos());
                    } else {
                        $comment = new Doc(implode("\n", array_reverse($newComment)), $docBlock->getLine(), $docBlock->getFilePos());
                    }
                    $node->setDocComment($comment);
                    $this->source->replaceNode($node, $node);
                }
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        // TODO: Implement afterTraverse() method.
    }
}
