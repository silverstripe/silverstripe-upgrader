<?php

namespace Sminnee\Upgrader\UpgradeRule;

use PhpParser\NodeVisitor\NameResolver;

class RenameClasses extends AbstractUpgradeRule
{
    public function upgradeFile($contents, $filename)
    {
        $this->warningCollector = [];

        $visitors = [
            new NameResolver(),
            new RenameClassesVisitor($this->parameters['mappings']),
        ];

        $output = $this->transformWithVisitors($contents, $visitors);

        return [ $output, $this->warningCollector ];
    }
}
