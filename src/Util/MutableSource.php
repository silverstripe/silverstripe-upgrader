<?php

namespace Sminnee\Upgrader\Util;

use PhpParser\Lexer;
use PhpParser\ParserFactory;
use PhpParser\Parser;
use PhpParser\PrettyPrinter;
use PhpParser\Node;

/**
 * A repesentation of a source file designed to be mutated via nikic/PHP-Parser nodes
 */
class MutableSource
{

    private $source = null;
    private $askt = null;
    private $prettyPrinter = null;

    public function __construct($source)
    {
        $this->source = new MutableString($source);
        $this->prettyPrinter = new PrettyPrinter\Standard();

        $lexer = new Lexer\Emulative(['usedAttributes' => ['startFilePos', 'endFilePos']]);
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
     */
    public function setPrettyPrinter(PrettyPrinter $prettyPrinter)
    {
        $this->prettyPrinter = $prettyPrinter;
    }

    /**
     * Get the internal MutableString source
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get the internal PHP-Parser AST
     */
    public function getAst()
    {
        return $this->ast;
    }

    /**
     * Replace a node with the given replacement
     * @param string|Node|array The entity to replace with. A string, Node, or array of Nodes
     */
    public function replaceNode(Node $node, $replacement)
    {
        list($start, $length) = $this->nodeRange($node);
        $this->replace($start, $length, $this->createString($replacement));
    }

    /**
     * Replace a node with the given replacement string
     */
    public function replaceNodeWithString(Node $node, $replacement)
    {
        list($start, $length) = $this->nodeRange($node);
        $this->replace($start, $length, $replacement);
    }

    /**
     * @param Node $base The node to insert before
     * @param string|Node|array The entity to insert. A string, Node, or array of Nodes
     */
    public function insert($pos, $insertion)
    {
        return $this->source->insert($pos, $this->createString($insertion));
    }

    /**
     * @param Node $base The node to insert before
     * @param string|Node|array The entity to insert. A string, Node, or array of Nodes
     */
    public function insertBefore(Node $base, $insertion)
    {
        return $this->source->insert($this->nodeStart($base), $this->createString($insertion));
    }

    public function remove($pos, $len)
    {
        return $this->source->remove($pos, $len);
    }

    public function replace($pos, $len, $newString)
    {
        return $this->source->replace($pos, $len, $newString);
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
     * @param string|Node|array $entity The entity to replace with. A string, Node, or array of Nodes
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
     * @return int start of the given node
     */
    protected function nodeStart(Node $node)
    {
        $attributes = $node->getAttributes();
        if (empty($attributes['startFilePos'])) {
            throw new \LogicException("replaceNode requires startFilePos and endFilePos to be set. "
                . "Check your Lexer usedAttributes opton!");
        }
        return $attributes['startFilePos'];
    }

    /**
     * @return array [start, len] of the given node
     */
    protected function nodeRange(Node $node)
    {
        $attributes = $node->getAttributes();
        if (empty($attributes['startFilePos']) || empty($attributes['endFilePos'])) {
            throw new \LogicException("replaceNode requires startFilePos and endFilePos to be set. "
                . "Check your Lexer usedAttributes opton!");
        }

        return [
            $attributes['startFilePos'],
            $attributes['endFilePos'] - $attributes['startFilePos'] + 1,
        ];
    }
}
