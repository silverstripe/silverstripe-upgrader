<?php

namespace SilverStripe\Upgrader\Console;

use Nette\InvalidArgumentException;
use SilverStripe\Upgrader\CodeCollection\DiskCollection;
use SilverStripe\Upgrader\Util\WebRootMover;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\Composer\ComposerExec;

/**
 * Command to switch to public web root.
 */
class WebrootCommand extends AbstractCommand implements AutomatedCommand
{
    use FileCommandTrait;
    use AutomatedCommandTrait;

    protected function configure()
    {
        $this->setName('webroot')
            ->setDescription('Update a SilverStripe project to use the `public` webroot.')
            ->setDefinition([
                $this->getRootInputOption(),
                $this->getWriteInputOption(),
                new InputOption(
                    'composer-path',
                    'P',
                    InputOption::VALUE_OPTIONAL,
                    'Path to the composer executable.',
                    ''
                )
            ]);
    }

    /**
     * @inheritdoc
     * @param array $args
     * @return array
     */
    protected function enrichArgs(array $args): array
    {
        $args['--write'] = true;
        return array_intersect_key(
            $args,
            array_flip([
                '--write',
                '--root-dir',
                '--composer-path'
            ])
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get our input variables
        $rootPath = $this->getRootPath($input);
        $write = $input->getOption('write');

        $composerPath = $input->getOption('composer-path');

        $console = new SymfonyStyle($input, $output);

        // Initialise our mover
        $composer = new ComposerExec($rootPath, $composerPath, $output);
        $mover = new WebRootMover($rootPath, $composer);

        try {
            $diff = $mover->move();
        } catch (\InvalidArgumentException $ex) {
            // It's not a big deal if the command fails. It shouldn't stop an automated execution.
            $console->warning($ex->getMessage());
            return null;
        }


        // Show changes
        $this->setDiff($diff);
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $diff);

        // Write changes
        if ($write) {
            $disk = new DiskCollection($rootPath, true);
            $disk->applyChanges($diff);
            $console->note("Changes have been saved.");
            $console->warning(
                'Don\'t forget to update your VHOST to point to the `public` folder and to update any hardcoded links.'
            );
        } else {
            $console->note("Changes not saved; Run with --write to commit to disk");
        }


        return null;
    }
}
