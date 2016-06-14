<?php

namespace SilverStripe\Upgrader;

use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use SilverStripe\Upgrader\CodeCollection\DiskItem;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\AbstractUpgradeRule;
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

        /** @var ItemInterface $item */
        foreach ($code->iterateItems() as $item) {
            $path = $item->getPath();
            $filename = $item->getFilename();
            $contents = $updatedContents = $item->getContents();

            /** @var AbstractUpgradeRule $upgradeRule */
            foreach ($this->spec->rules() as $upgradeRule) {
                $ruleName = $upgradeRule->getName();
                if ($upgradeRule->appliesTo($item)) {
                    $this->log("Applying <info>{$ruleName}</info> to <info>{$filename}</info>...");
                    list($updatedContents, $warnings) = $upgradeRule->upgradeFile($updatedContents, $item);
                    if ($warnings) {
                        $changeset->addWarnings($path, $warnings);
                    }
                }
            }

            if ($contents !== $updatedContents) {
                $changeset->addFileChange($path, $updatedContents, $contents);
            }
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
