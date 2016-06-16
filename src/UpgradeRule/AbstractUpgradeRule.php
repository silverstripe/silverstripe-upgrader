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

    /**
     * Called on a code collection prior to upgrade
     *
     * @param CollectionInterface $code
     * @param CodeChangeSet $changeset
     */
    public function beforeUpgradeCollection(CollectionInterface $code, CodeChangeSet $changeset)
    {
    }

    /**
     * Called on a code collection after upgrade
     *
     * @param CollectionInterface $code
     * @param CodeChangeSet $changeset
     */
    public function afterUpgradeCollection(CollectionInterface $code, CodeChangeSet $changeset)
    {
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
     * Returns string containing the new code.
     *
     * @param string $contents
     * @param ItemInterface $file
     * @param CodeChangeSet $changeset Changeset to add warnings to
     * @return string
     */
    abstract public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset);

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
