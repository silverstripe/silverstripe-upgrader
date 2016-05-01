<?php

namespace Sminnee\Upgrader\UpgradeRule;

use PhpParser\NodeVisitor\NameResolver;
use Sminnee\Upgrader\Util\MutableSource;

class RenameClasses extends AbstractUpgradeRule
{
    public function upgradeFile($contents, $filename)
    {
        if (!$this->appliesTo($filename)) {
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
