<?php

namespace SilverStripe\Upgrader\UpgradeRule;

use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\Util\MutableSource;

class RenameClasses extends AbstractUpgradeRule
{
    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        $source = new MutableSource($contents);

        $namespaceCorrections = [];
        if (isset($this->parameters['namespaceCorrections'])) {
            foreach ($this->parameters['namespaceCorrections'] as $namespace) {
                $namespaceCorrections[$namespace] = [];
                foreach ($this->parameters['mappings'] as $className) {
                    if (substr($className, 0, strrpos($className, '\\')) === $namespace) {
                        $namespaceCorrections[$namespace][] = $className;
                    }
                }
            }
        }

        $mappings = isset($this->parameters['mappings']) ? $this->parameters['mappings'] : [];
        $skipConfig = isset($this->parameters['skipConfigs']) ? $this->parameters['skipConfigs'] : [];

        $this->transformWithVisitors($source->getAst(), [
            new NameResolver(), // Add FQN for class references
            new ParentConnector(), // Link child nodes to parents
            new RenameClassesVisitor($source, $mappings, $namespaceCorrections, $skipConfig),
        ]);

        return $source->getModifiedString();
    }
}
