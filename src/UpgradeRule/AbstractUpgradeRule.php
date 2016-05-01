<?php

namespace Sminnee\Upgrader\UpgradeRule;

use PhpParser\NodeTraverser;

/**
 * An upgrader, that generates a change for a given item
 */
abstract class AbstractUpgradeRule
{

    protected $parameters = [];
    protected $warningCollector = [];

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
    protected function transformWithVisitors(array $ast, array $visitors)
    {
        $traverser = new NodeTraverser;
        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $traverser->traverse($ast);
    }

    /**
     * Returns true if this upgrad rule applies to the given file
     * Checks fileExtensions parameters
     */
    public function appliesTo($filename)
    {
        if (empty($this->parameters['fileExtensions'])) {
            return true;
        }

        if (preg_match('/[^\/]*\.(.*)$/', $filename, $matches)) {
            $extension = $matches[1];
        } else {
            $extension = '';
        }

        return in_array($extension, $this->parameters['fileExtensions']);
    }
}
