<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\Util\CommandRunner;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to try to update a composer file to use SS4.
 */
class AllInOneCommand extends AbstractCommand
{
    use FileCommandTrait;

    protected function configure()
    {
        $this->setName('all')
            ->setDescription('Aggregate all the commands required to upgrade a SilverStripe project.')
            ->setDefinition([
                $this->getRootInputOption(),
                new InputOption(
                    'strict',
                    'S',
                    InputOption::VALUE_NONE,
                    'Prefer ~ to ^ avoid accidental updates.'
                ),
                new InputOption(
                    'recipe-core-constraint',
                    'R',
                    InputOption::VALUE_OPTIONAL,
                    'Version of `silverstripe/recipe-core` you are targeting. Defaults to the last stable',
                    '*'
                ),
                new InputOption(
                    'cwp-constraint',
                    null,
                    InputOption::VALUE_OPTIONAL,
                    'Version of `cwp/cwp-recipe-core` you are targeting. If left blank, ' .
                    '`cwp-recipe-core` will not be constrained. Overrides `recipe-core-constraint`.',
                    ''
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
                    'Path to your composer executable.'
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
                new InputOption(
                    'psr4',
                    'p',
                    InputOption::VALUE_NONE,
                    'When used with the recursive option, assume directories and namespaces are PSR-4 compliant.'
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
        $cwpConstraint = $input->getOption('cwp-constraint');
        $strict = $input->getOption('strict');

        $skipAddNamespace = $input->getOption('skip-add-namespace');
        $namespace = $input->getOption('namespace');
        $psr4 = $input->getOption('psr4');

        $skipReorganise = $input->getOption('skip-reorganise');
        $skipWebroot = $input->getOption('skip-webroot');

        // Make sure our combination of namespace argument makes senses
        $this->validateNamespaceInputCombination($skipAddNamespace, $namespace, $psr4);


        // Build command list
        $commandList = [];
        $commandList[] = 'recompose';
        $commandList[] = 'environment';
        if (!$skipAddNamespace) {
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
                '--cwp-constraint' => $cwpConstraint,
                '--strict' => $strict,
                '--root-dir' => $rootPath,
                'namespace' => $namespace,
                '--psr4' => $psr4
            ],
            $output
        );

        return null;
    }

    /**
     * Validate the combination of namespace argument provided.
     * @param null|bool $skipAddNamespace
     * @param null|string $namespace
     * @param null|bool $psr4
     * @throws InvalidArgumentException
     */
    private function validateNamespaceInputCombination($skipAddNamespace, $namespace, $psr4)
    {
        if ($skipAddNamespace && $psr4) {
            throw new InvalidArgumentException(
                'The `--skip-add-namespace` and `--psr4` flag cannot be used simultaneously.'
            );
        }

        if ($skipAddNamespace && $namespace) {
            throw new InvalidArgumentException(
                'The `--skip-add-namespace` and `--namespace` flag cannot be used simultaneously.'
            );
        }

        if (!$skipAddNamespace && !$namespace) {
            throw new InvalidArgumentException(
                'You must use the `--namespace` flag to specify which namespace you want to use for your project. ' .
                'If you do not want to namespace your project, set the `--skip-add-namespace` flag.'
            );
        }
    }
}
