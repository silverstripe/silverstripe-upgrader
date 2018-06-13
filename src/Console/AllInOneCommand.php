<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\Util\CommandRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

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
                    'Path to your composer executable.',
                    ''
                ),
                new InputOption(
                    'skip-add-namespace',
                    null,
                    InputOption::VALUE_NONE,
                    'Skip the `add-namespace` command.'
                ),
                new InputOption(
                    'namespace',
                    'N',
                    InputOption::VALUE_OPTIONAL,
                    'Path to your composer executable.',
                    'App\\Web'
                ),
                new InputOption(
                    'skip-reorganise',
                    null,
                    InputOption::VALUE_NONE,
                    'Skip the `reorganise` command.'
                ),
                new InputOption(
                    'skip-webroot',
                    null,
                    InputOption::VALUE_NONE,
                    'Skip the `webroot` command.'
                ),
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
        $skipNamespace = $input->getOption('skip-add-namespace');
        $namespace = $input->getOption('namespace');
        $skipReorganise = $input->getOption('skip-reorganise');
        $skipWebroot = $input->getOption('skip-webroot');

        // Build command list
        $commandList = [];
        $commandList[] = 'recompose';
        $commandList[] = 'environment';
        if (!$skipNamespace) {
            $commandList[] = 'add-namespace';
        }
        $commandList[] = 'upgrade';
        $commandList[] = 'inspect';
        if (!$skipReorganise) {
            $commandList[] = 'reorganise';
        }
        if (!$skipWebroot) {
            $commandList[] = 'webroot';
        }

        $runner = new CommandRunner();

        $runner->run(
            $this->getApplication(),
            $commandList,
            [
                '--composer-path' => $composerPath,
                '--recipe-core-constraint' => $recipeCoreConstraint,
                '--strict' => $strict,
                '--root-dir' => $rootPath,
                'namespace' => $namespace,
            ],
            $output
        );

        return null;
    }
}
