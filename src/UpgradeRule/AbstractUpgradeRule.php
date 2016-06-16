<?php

namespace SilverStripe\Upgrader\UpgradeRule;

use PhpParser\NodeTraverser;
use SilverStripe\Upgrader\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;

/**
 * An upgrader, that generates a change for a given item
 */
abstract class AbstractUpgradeRule
{

    protected $parameters = [];
    protected $warningCollector = [];

    /**
     * Called on a code collection prior to upgrade
     *
     * @param CollectionInterface $code
     * @param CodeChangeSet $changeset
     */
    public function beforeUpgrade(CollectionInterface $code, CodeChangeSet $changeset)
    {
    }

    /**
     * Called on a code collection after upgrade
     *
     * @param CollectionInterface $code
     * @param CodeChangeSet $changeset
     */
    public function afterUpgrade($code, $changeset)
    {
    }

    /**
     * Add a warning message for this upgrade rule
     *
     * @param int $line
     * @param string $message
     */
    protected function addWarning($line, $message)
    {
        $this->warningCollector[] = [$line, $message];
    }

    /**
     * Get name for this rule
     *
     * @return string
     */
    public function getName()
    {
        $reflection = new \ReflectionClass($this);
        return $reflection->getShortName();
    }

    /**
     * Upgrades the contents of the given file
     * Returns two results as a 2-element array:
     *  - The first item is a string of the new code
     *  - The second item is an array of warnings, each of which is a 2 element array, for line & message
     * @param string $contents
     * @param ItemInterface $file
     * @return array
     */
    abstract public function upgradeFile($contents, $file);

    /**
     * Apply the parameters to this object and return $this, for fluent call-style
     *
     * @param array $parameters
     * @return $this
     */
    public function withParameters(array $parameters)
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Apply the given visitors to the given code, returning new code
     *
     * @param array $ast
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
     *
     * @param ItemInterface $file
     * @return bool
     */
    public function appliesTo($file)
    {
        if (empty($this->parameters['fileExtensions'])) {
            return true;
        }

        if (preg_match('/[^\/]*\.(.*)$/', $file->getPath(), $matches)) {
            $extension = $matches[1];
        } else {
            $extension = '';
        }

        return in_array($extension, $this->parameters['fileExtensions']);
    }
}
