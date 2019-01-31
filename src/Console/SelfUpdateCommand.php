<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\Util\UpdateChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to automate the installation of the latest version of the upgrader.
 */
class SelfUpdateCommand extends Command
{

    protected function configure()
    {
        $this->setName('self-update')
            ->setDescription('Get the latest version of the SilverStripe upgrader.')
            ->setDefinition([
                new InputOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation.'),
                new InputOption('rollback', 'r', InputOption::VALUE_NONE, 'Rollback the latest self-update.')
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console = new SymfonyStyle($input, $output);
        $yes = $input->getOption('yes');
        $rollback = $input->getOption('rollback');

        if ($rollback) {
            $this->rollback($console, $yes);
        } else {
            $this->update($console, $yes);
        }
    }

    /**
     * Update the phar executable.
     * @param SymfonyStyle $console
     * @param bool $yes
     */
    private function update(SymfonyStyle $console, bool $yes)
    {
        $updater = UpdateChecker::getUpdater($this->getApplication()->getVersion());

        if (!$updater->hasUpdate()) {
            $console->success('You\'re already running the latest stable release.');
        }

        $nextVersion = $updater->getNewVersion();

        // Check that we are running as a phar
        if (!\Phar::running()) {
            $console->warning(
                "Release $nextVersion is available. However, only the PHAR distribution of the upgrader can be " .
                "self-updated. If you've installed the upgrader globally with composer, run this command to get " .
                "the latest release.\n\ncomposer global update silverstripe/upgrader"
            );
            return;
        }

        // Do the updating
        if ($yes || $console->confirm("Release $nextVersion is available. Do you want to update now?", false)) {
            // This can fail for a variety of reason. We'll just let the exception bubble up.
            $result = $updater->update();

            if ($result) {
                $console->success(
                    "You are now running the latest version. Use the `--rollback` flag to revert to the older version."
                );
            }
        }
    }

    /**
     * Rollback to an older version if available.
     * @param SymfonyStyle $console
     * @param bool $yes
     */
    private function rollback(SymfonyStyle $console, bool $yes)
    {
        $updater = UpdateChecker::getUpdater($this->getApplication()->getVersion());

        // Check that we are running as a phar.
        if (!\Phar::running()) {
            $console->error('You can only rollback the upgrader when it is installed as a PHAR executable.');
            return;
        }

        if ($yes || $console->confirm("Do you want to rollback the silverstripe upgrader?", false)) {
            // This can fail for a variety of reason. We'll just let the exception bubble up.
            $result = $updater->rollback();

            if ($result) {
                $console->success("You have rollback your Upgrader installation.");
            }
        }
    }
}
