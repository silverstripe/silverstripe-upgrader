<?php

namespace SilverStripe\Upgrader;

use SilverStripe\Upgrader\CodeCollection\CollectionInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Passes code into various transformation operations
 */
class Upgrader
{

    private $logger = null;

    public function __construct(UpgradeSpec $spec)
    {
        $this->spec = $spec;
    }

    public function setLogger(OutputInterface $logger)
    {
        $this->logger = $logger;
    }

    public function upgrade(CollectionInterface $code)
    {
        $changeset = new CodeChangeSet();

        foreach ($code->iterateItems() as $item) {
            $path = $item->getPath();
            $contents = $updatedContents = $item->getContents();

            foreach ($this->spec->rules() as $upgradeRule) {
                if ($upgradeRule->appliesTo($path)) {
                    $this->log("Applying " . get_class($upgradeRule) . " to " . $item->getPath() . "...");
                    list($updatedContents, $warnings) = $upgradeRule->upgradeFile($updatedContents, $path);
                    if ($warnings) {
                        $changeset->addWarnings($path, $warnings);
                    }
                }
            }

            if ($contents !== $updatedContents) {
                $changeset->addFileChange($path, $updatedContents);
            }
        }

        return $changeset;
    }

    private function log($message)
    {
        if ($this->logger) {
            $this->logger->writeln("[" . date('Y-m-d H:i:s') . "] $message");
        }
    }
}
