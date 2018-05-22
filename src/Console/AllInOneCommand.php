<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\Util\CommandRunner;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

use SilverStripe\Upgrader\ChangeDisplayer;
use SilverStripe\Upgrader\Composer\Package;
use SilverStripe\Upgrader\Composer\ComposerExec;
use SilverStripe\Upgrader\Composer\ComposerFile;
use SilverStripe\Upgrader\Composer\Rules;
use SilverStripe\Upgrader\Composer\Packagist;


use InvalidArgumentException;

/**
 * Command to try to update a composer file to use SS4.
 */
class AllInOneCommand extends AbstractCommand
{
    use FileCommandTrait;

    protected function configure()
    {
        $this->setName('all-in-one')
            ->setDescription('Aggregate all the commands required to upgrade a SilverStripe project.')
            ->setDefinition([
                $this->getRootInputOption(),
                new InputOption(
                    'strict',
                    'S',
                    InputOption::VALUE_NONE,
                    'Prefer ~ to ^ avoid accidental updates'
                ),
                new InputOption(
                    'recipe-core-constraint',
                    'R',
                    InputOption::VALUE_OPTIONAL,
                    'Version of `silverstripe/recipe-core` you are targeting. Defaults to the last stable',
                    '*'
                ),
                new InputOption(
                    'composer-path',
                    'P',
                    InputOption::VALUE_OPTIONAL,
                    'Path to your composer executable',
                    ''
                )
            ]);
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
        $composerPath = $input->getOption('composer-path');
        $recipeCoreConstraint = $input->getOption('recipe-core-constraint');
        $strict = $input->getOption('strict');

        $console = new SymfonyStyle($input, $output);

        $runner = new CommandRunner();

        $runner->run(
            $this->getApplication(),
            [
                'recompose',
                'environment',
            ],
            [
                '--composer-path' => $composerPath,
                '--recipe-core-constraint' => $recipeCoreConstraint,
                '--strict' => $strict,
                '--root-dir' => $rootPath
            ],
            $output
        );

        return null;
    }
}
