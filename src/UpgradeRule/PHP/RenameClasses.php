<?php

namespace SilverStripe\Upgrader\UpgradeRule\PHP;

use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ParentConnector;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\RenameClassesVisitor;
use SilverStripe\Upgrader\Util\MutableSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RenameClasses extends PHPUpgradeRule
{
    /**
     * @var bool
     */
    private $showPrompt;

    /**
     * @var Command
     */
    protected $command;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * RenameClasses constructor.
     * @param bool $showPrompt
     * @param $command
     * @param $input
     * @param $output
     */
    public function __construct($showPrompt = false, $command = null, $input = null, $output = null)
    {
        $this->showPrompt = $showPrompt;
        $this->command = $command;
        $this->input = $input;
        $this->output = $output;
    }

    public function upgradeFile($contents, ItemInterface $file, CodeChangeSet $changeset)
    {
        if (!$this->appliesTo($file)) {
            return $contents;
        }

        $source = new MutableSource($contents);

        $mappings = isset($this->parameters['mappings']) ? $this->parameters['mappings'] : [];
        $skipConfig = isset($this->parameters['skipConfigs']) ? $this->parameters['skipConfigs'] : [];
        $renameWarnings = isset($this->parameters['renameWarnings']) ? $this->parameters['renameWarnings'] : [];
        $showPrompt = $this->showPrompt;

        $this->transformWithVisitors($source->getAst(), [
            new NameResolver(), // Add FQN for class references
            new ParentConnector(), // Link child nodes to parents
            new RenameClassesVisitor($source, $mappings, $skipConfig, $renameWarnings, $showPrompt, $changeset, $file, $this->command, $this->input, $this->output),
        ]);

        return $source->getModifiedString();
    }
}
