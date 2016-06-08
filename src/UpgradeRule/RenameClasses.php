<?php

namespace SilverStripe\Upgrader\UpgradeRule;

use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\Util\MutableSource;

class RenameClasses extends AbstractUpgradeRule
{
    public function upgradeFile($contents, $file)
    {
        if (!$this->appliesTo($file)) {
            return [ $contents, [] ];
        }
        $this->warningCollector = [];

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

        $this->transformWithVisitors($source->getAst(), [
            new NameResolver(),
            new RenameClassesVisitor($source, $this->parameters['mappings'], $namespaceCorrections),
        ]);

        return [ $source->getModifiedString(), $this->warningCollector ];
    }
}
