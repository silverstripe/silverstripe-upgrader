<?php

namespace SilverStripe\Upgrader;

use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\PHPUpgradeRule;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Passes code into various transformation operations
 */
class Upgrader
{
    /**
     * @var OutputInterface
     */
    private $logger = null;

    /**
     * @var UpgradeSpec
     */
    private $spec = null;

    public function __construct(UpgradeSpec $spec)
    {
        $this->spec = $spec;
    }

    public function setLogger(OutputInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param CollectionInterface $code
     * @return CodeChangeSet
     */
    public function upgrade(CollectionInterface $code)
    {
        $changeset = new CodeChangeSet();

        // Before-upgrade hook
        /** @var PHPUpgradeRule $upgradeRule */
        foreach ($this->spec->rules() as $upgradeRule) {
            $upgradeRule->beforeUpgradeCollection($code, $changeset);
        }

        /** @var ItemInterface $item */
        foreach ($code->iterateItems() as $item) {
            $path = $item->getPath();
            $filename = $item->getFilename();
            $contents = $updatedContents = $item->getContents();

            /** @var PHPUpgradeRule $upgradeRule */
            foreach ($this->spec->rules() as $upgradeRule) {
                $ruleName = $upgradeRule->getName();
                if ($upgradeRule->appliesTo($item)) {
                    $this->log("Applying <info>{$ruleName}</info> to <info>{$filename}</info>...");
                    $updatedContents = $upgradeRule->upgradeFile($updatedContents, $item, $changeset);
                }
            }

            if ($contents !== $updatedContents) {
                $changeset->addFileChange($path, $updatedContents, $contents);
            }
        }

        // After-upgrade hook
        /** @var PHPUpgradeRule $upgradeRule */
        foreach ($this->spec->rules() as $upgradeRule) {
            $upgradeRule->afterUpgradeCollection($code, $changeset);
        }

        return $changeset;
    }

    private function log($message)
    {
        if ($this->logger) {
            $this->logger->writeln("<comment>[" . date('Y-m-d H:i:s') . "]</comment> $message");
        }
    }
}
