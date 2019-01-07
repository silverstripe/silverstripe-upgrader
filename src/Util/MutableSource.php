<?php

namespace SilverStripe\Upgrader\Util;

use PhpParser\Lexer;
use PhpParser\Node\Stmt\Property;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;
use PhpParser\PrettyPrinterAbstract;

/**
 * A representation of a source file designed to be mutated via nikic/PHP-Parser nodes
 */
class MutableSource
{

    /**
     * @var MutableString
     */
    private $source = null;

    /**
     * Abstract syntax tree
     *
     * @var Node[]
     */
    private $ast = null;

    /**
     * @var PrettyPrinterAbstract
     */
    private $prettyPrinter = null;

    public function __construct($source)
    {
        $this->source = new MutableString($source);
        $this->prettyPrinter = new PrettyPrinter\Standard();

        $lexer = new Lexer\Emulative([
            'usedAttributes' => ['comments', 'startFilePos', 'endFilePos', 'startLine', 'endLine']
        ]);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP5, $lexer);
        $this->ast = $parser->parse($source);
    }

    /**
     * Get the PrettyPrinter used to turn nodes to strings
     */
    public function getPrettyPrinter()
    {
        return $this->prettyPrinter;
    }

    /**
     * Set the PrettyPrinter used to turn nodes to strings
     *
     * @param PrettyPrinterAbstract $prettyPrinter
     */
    public function setPrettyPrinter(PrettyPrinterAbstract $prettyPrinter)
    {
        $this->prettyPrinter = $prettyPrinter;
    }

    /**
     * Get the internal MutableString source
     *
     * @return MutableString
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get the internal PHP-Parser AST
     *
     * @return Node[]
     */
    public function getAst()
    {
        return $this->ast;
    }

    /**
     * Replace a node with the given replacement
     *
     * @param Node $node
     * @param string|Node|array $replacement The entity to replace with. A string, Node, or array of Nodes
     */
    public function replaceNode(Node $node, $replacement)
    {
        list($start, $length) = $this->nodeRange($node);

        # make sure we don't remove the semicolon
        if ($replacement instanceof Property) {
            $length -= 1;
        }

        $this->replace($start, $length, $replacement);
    }

    /**
     * Get original string for this node
     *
     * @param Node $node
     * @return string Value of string
     */
    public function getNodeString(Node $node)
    {
        list($start, $length) = $this->nodeRange($node);
        $original = $this->source->getOrigString();
        return substr($original, $start, $length);
    }

    /**
     * @param int $pos Position to insert before
     * @param string|Node|array The entity to insert. A string, Node, or array of Nodes
     */
    public function insert($pos, $insertion)
    {
        $this->source->insert($pos, $this->createString($insertion));
    }

    /**
     * @param Node $base The node to insert before
     * @param string|Node|array The entity to insert. A string, Node, or array of Nodes
     */
    public function insertBefore(Node $base, $insertion)
    {
        $this->insert($this->nodeStart($base), $insertion);
    }

    /**
     * @param Node $base The node to insert after
     * @param string|Node|array The entity to insert. A string, Node, or array of Nodes
     */
    public function insertAfter(Node $base, $insertion)
    {
        $this->source->insert($this->nodeEnd($base)+1, $this->createString($insertion));
    }

    public function remove($pos, $len)
    {
        $this->source->remove($pos, $len);
    }

    /**
     * Replace a range
     *
     * @param int $pos
     * @param int $len
     * @param string|Node|array $replacement The entity to replace with. A string, Node, or array of Nodes
     */
    public function replace($pos, $len, $replacement)
    {
        $this->source->replace($pos, $len, $this->createString($replacement));
    }

    public function getOrigString()
    {
        return $this->source->getOrigString();
    }

    public function getModifiedString()
    {
        return $this->source->getModifiedString();
    }


    /**
     * Generate a string representation of the given entity
     *
     * @param string|Node|array $entity The entity to replace with. A string, Node, or array of Nodes
     * @return string
     */
    public function createString($entity)
    {
        if (is_string($entity)) {
            return $entity;
        }

        if ($entity instanceof Node) {
            // Single nodes don't get trailling ;s
            $string = $this->getPrettyPrinter()->prettyPrint([$entity]);
            if (substr($string, -1) === ';') {
                $string = substr($string, 0, -1);
            }
            return $string;
        }

        if (is_array($entity)) {
            // Sets of node haves a trailing newline
            $string = $this->getPrettyPrinter()->prettyPrint($entity);
            $string .= "\n";
            return $string;
        }

        throw new \InvalidArgumentException('createString must be passed a string, Node, or array of Nodes');
    }

    /**
     * Gets start index of current node
     *
     * @param Node $node
     * @return int start of the given node
     */
    protected function nodeStart(Node $node)
    {
        $attributes = $node->getAttributes();
        if (!isset($attributes['startFilePos'])) {
            throw new \LogicException("replaceNode requires startFilePos and endFilePos to be set. "
                . "Check your Lexer usedAttributes option!");
        }
        return $attributes['startFilePos'];
    }

    /**
     * Gets end of given node
     *
     * @param Node $node
     * @return int end of the given node
     */
    protected function nodeEnd(Node $node)
    {
        $attributes = $node->getAttributes();
        if (!isset($attributes['startFilePos'])) {
            throw new \LogicException("replaceNode requires startFilePos and endFilePos to be set. "
                . "Check your Lexer usedAttributes option!");
        }
        return $attributes['endFilePos'];
    }

    /**
     * Gets range of the given node
     *
     * @param Node $node
     * @return array [start, len] of the given node
     */
    public function nodeRange(Node $node)
    {
        $attributes = $node->getAttributes();
        if (!isset($attributes['startFilePos']) || !isset($attributes['endFilePos'])) {
            throw new \LogicException("replaceNode requires startFilePos and endFilePos to be set. "
                . "Check your Lexer usedAttributes option!");
        }

        return [
            $attributes['startFilePos'],
            $attributes['endFilePos'] - $attributes['startFilePos'] + 1,
        ];
    }
}
