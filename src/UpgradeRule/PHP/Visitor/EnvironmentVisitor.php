<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;

/**
 * Go through an `_ss_environment.php` file to see if it's using complex logic that might make it impossible to
 * accurately convert it to a `.env` file.
 *
 * We're trying to flag anything that isn't:
 * * a straight `define` call
 * * a comment or other kind of non-statement
 * * setting a value in the global `$_FILE_TO_URL_MAPPING`
 *
 */
class EnvironmentVisitor extends NodeVisitorAbstract
{

    /**
     * flag to be set when an invalid construct is found.
     * @var boolean
     */
    private $isValid = true;

    /**
     * Called when entering a node.
     *
     * Return value semantics:
     *  * null:      $node stays as-is
     *  * otherwise: $node is set to the return value
     *
     * @param Node $node Node
     *
     * @return null|int|Node Node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof FuncCall) {
            if ($node->name == 'define') {
                if ($node->args[1]->value instanceof Scalar) {
                    // Defining a new const with a scalar value is acceptable.
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
            }
        }

        if ($node instanceof Nop) {
            // Statement that don't do anything are allowed.
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Global_) {
            // `global` is OK.
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Assign) {
            if ($node->var instanceof ArrayDimFetch) {
                if ($node->var->var instanceof Variable) {
                    if ($node->var->var->name == '_FILE_TO_URL_MAPPING') {
                        // assigning values to `$_FILE_TO_URL_MAPPING` allowed
                        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                    }
                }
            }
        }

        return $this->invalidate();
    }

    /**
     * Mark the ast as invalid and stop traversing.
     * @return integer
     */
    private function invalidate()
    {
        $this->isValid = false;
        return NodeTraverser::STOP_TRAVERSAL;
    }

    /**
     * Check if the visitor has found invalid statement while traversing the AST.
     * @return boolean
     */
    public function getIsValid()
    {
        return $this->isValid;
    }
}
