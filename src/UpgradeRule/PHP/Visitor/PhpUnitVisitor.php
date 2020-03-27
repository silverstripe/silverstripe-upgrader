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
        // noop
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $changed = false;
        if ($node instanceof Node\Stmt\ClassMethod) {
            switch (strtolower($node->name)) {
                case 'setup':
                case 'teardown':
                    if (!$node->isProtected()) {
                        $changed = true;
                        static::changeVisibility($node, Class_::MODIFIER_PROTECTED);
                    }
                    break;
                case 'setUpBeforeClass':
                case 'tearDownAfterClass':
                    if (!$node->isPublic()) {
                        $changed = true;
                        static::changeVisibility($node, Class_::MODIFIER_PUBLIC);
                    }
                    if (!$node->isStatic()) {
                        $changed = true;
                        static::makeStatic($node);
                    }
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
                                $update = true;
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
                                $update = true;
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
                                $update = true;
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
                                $update = true;
                                break;
                            default:
                                $newComment[] = $line;
                        }
                    } else {
                        $newComment[] = $line;
                    }
                }
                if ($update) {
                    $changed = true;
                    $commentText = count($newComment) === 2 ? '' : implode("\n", array_reverse($newComment));
                    $comment = new Doc($commentText, $docBlock->getLine(), $docBlock->getFilePos());
                    $node->setDocComment($comment);
                }
            }
            if (in_array(strtolower($node->name), ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass'])) {
                if ($node->getReturnType() !== 'void') {
                    $changed = true;
                    $node = new Node\Stmt\ClassMethod($node->name, [
                        'flags' => $node->flags,
                        'byRef' => $node->returnsByRef(),
                        'params' => $node->getParams(),
                        'returnType' => 'void',
                        'stmts' => $node->getStmts(),
                    ], $node->getAttributes());
                }
            }
            if ($changed) {
                $this->source->replaceNode($node, $node);
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        // noop
    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {
        // noop
    }
}
