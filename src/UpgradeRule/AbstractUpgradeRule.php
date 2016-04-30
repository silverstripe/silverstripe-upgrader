<?php

namespace Sminnee\Upgrader\UpgradeRule;

use PhpParser\ParserFactory;
use PhpParser\Parser;
use PhpParser\Lexer;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\NodeTraverser;

/**
 * An upgrader, that generates a change for a given item
 */
abstract class AbstractUpgradeRule
{

    protected $parameters = [];
    protected $warningCollector = [];

    /**
     * Apply the parameters to this object and return $this, for fluent call-style
     */
    public function withParameters(array $parameters)
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Apply the given visitors to the given code, returning new code
     *
     * @param string $code
     * @param array $visitors
     * @return string
     */
    protected function transformWithVisitors($code, array $visitors)
    {
        $parser = $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP5);
        $prettyPrinter = new PrettyPrinter;
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser;
        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $ast = $traverser->traverse($ast);
        return '<?php' . "\n\n" . $prettyPrinter->prettyPrint($ast);
    }

    /**
     * Upgrades the contents of the given file
     * Returns two results as a 2-element array:
     *  - The first item is a string of the new code
     *  - The second item is an array of warnings, each of which is a 2 element array, for line & message
     * @param string $contents
     * @param string $filename
     * @return array
     */
    abstract public function upgradeFile($contents, $filename);
}
