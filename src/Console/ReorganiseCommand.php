<?php

namespace SilverStripe\Upgrader\Console;

use SilverStripe\Upgrader\Util\ProjectReorganiser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to convert a SilverStripe 3 `_ss_environment.php` to a SilverStripe 4 `.env` file.
 */
class ReorganiseCommand extends AbstractCommand
{
    use FileCommandTrait;

        protected function configure()
    {
        $this->setName('reorganise')
            ->setDescription('Reorganise project folders from the SS3 `mysite` convention to the SS4 `app` convention')
            ->setDefinition([
                $this->getRootInputOption(),
                $this->getWriteInputOption()
            ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootPath = $this->getRootPath($input);
        $write = $input->getOption('write');

        $reorg = new ProjectReorganiser($rootPath);



        // Looking at the code folder
        switch ($reorg->checkCodeFolder())
        {
            case ProjectReorganiser::NOTHING:
                $output->writeln("Could not find a `code` folder");
                break;
            case ProjectReorganiser::ALREADY_UPGRADED:
                $output->writeln("Your `code` folder as already been renamed to `src`");
                break;
            case ProjectReorganiser::BLOCKED_LEGACY:
                $output->writeln("Moving your `code` folder would override other files.");
                break;
            case ProjectReorganiser::UPGRADABLE_LEGACY:
                $this->outputMove(
                    $reorg->moveCodeFolder(!$write),
                    $output
                );
                break;
        }

        // Looking at the project folder
        switch ($reorg->checkProjectFolder())
        {
            case ProjectReorganiser::NOTHING:
                $output->writeln("Could not find a `mysite` folder");
                break;
            case ProjectReorganiser::ALREADY_UPGRADED:
                $output->writeln("Your `mysite` folder as already been renamed to `app`");
                break;
            case ProjectReorganiser::BLOCKED_LEGACY:
                $output->writeln("Moving your `mysite` folder would override other files.");
                break;
            case ProjectReorganiser::UPGRADABLE_LEGACY:
                $this->outputMove(
                    $reorg->moveProjectFolder(!$write),
                    $output
                );
                break;
        }

        if ($write) {
            $output->writeln("Your project has been reorganised");
        } else {
            $output->writeln("Changes not saved; Run with --write to commit to disk");
        }

        return null;
    }

    /**
     * Outputs the old folder name and the new folder name.
     * @param  array  $moves List of file movement.
     * @param  OutputInterface $ouput
     */
    private function outputMove(array $moves, OutputInterface $ouput)
    {
        foreach ($moves as $org => $dest)
        {
            $ouput->writeln(sprintf(
                '`%s` becomes `%s`',
                $org,
                $dest
            ));
        }
    }
}
