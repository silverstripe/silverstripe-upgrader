<?php

namespace Sminnee\Upgrader\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Sminnee\Upgrader\Upgrader;
use Sminnee\Upgrader\UpgradeSpec;
use Sminnee\Upgrader\CodeCollection\DiskCollection;
use Sminnee\Upgrader\ChangeDisplayer;

class UpgradeCommand extends Command
{

    protected function configure()
    {
        $this->setName('upgrade')
            ->setDescription('Upgrade a set of code files to work with a newer version of a library ')
            ->setDefinition([
                new InputArgument(
                    'path',
                    InputArgument::REQUIRED,
                    'The path to your code needing to be upgraded (e.g.'
                ),
                new InputOption(
                    'from',
                    'f',
                    InputOption::VALUE_REQUIRED,
                    'The current version of the library that your code is being upgraded from (e.g. 3.999)'
                ),
                new InputOption(
                    'upgrade-spec',
                    's',
                    InputOption::VALUE_REQUIRED,
                    'Location of the library\'s upgrade specs (e.g. framework/.upgrade)'
                ),
                new InputOption(
                    'write',
                    'w',
                    InputOption::VALUE_NONE,
                    'Actually write the changes, rather than merely displaying them'
                )
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $settings = array_merge($input->getOptions(), $input->getArguments());

        // Load the upgrade spec and create an upgrader
        //$spec = UpgradeSpec::loadFromPath($settings['upgrade-spec']);
        $spec = new UpgradeSpec([
            (new \Sminnee\Upgrader\UpgradeRule\RenameClasses())->withParameters([
                'fileExtensions' => [ 'php' ],
                'mappings' => [
                    'ArrayList' => 'SilverStripe\Model\ArrayList',
                    'DB' => 'SilverStripe\Model\DB',
                    'DataDifferencer' => 'SilverStripe\Model\DataDifferencer',
                    'DataExtension' => 'SilverStripe\Model\DataExtension',
                    'DataList' => 'SilverStripe\Model\DataList',
                    'DataObject' => 'SilverStripe\Model\DataObject',
                    'DataObjectInterface' => 'SilverStripe\Model\DataObjectInterface',
                    'DataQuery' => 'SilverStripe\Model\DataQuery',
                    'DatabaseAdmin' => 'SilverStripe\Model\DatabaseAdmin',
                    'GroupedList' => 'SilverStripe\Model\GroupedList',
                    'SS_HTMLValue' => 'SilverStripe\Model\HTMLValue',
                    'HasManyList' => 'SilverStripe\Model\HasManyList',
                    'HiddenClass' => 'SilverStripe\Model\HiddenClass',
                    'Hierarchy' => 'SilverStripe\Model\Hierarchy',
                    'PolymorphicHasManyList' => 'SilverStripe\Model\PolymorphicHasManyList',
                    'SS_HTMLValue' => 'SilverStripe\Model\SS_HTMLValue',
                    'UnsavedRelationList' => 'SilverStripe\Model\UnsavedRelationList',
                    'SS_List' => 'SilverStripe\Model\SS_List',
                    'DataModel' => 'SilverStripe\Model\DataModel',

                    'Database' => 'SilverStripe\Model\Connect\Database',
                    'DatabaseException' => 'SilverStripe\Model\Connect\DatabaseException',
                    'DBConnector' => 'SilverStripe\Model\Connect\DBConnector',
                    'DBQueryManager' => 'SilverStripe\Model\Connect\DBQueryManager',
                    'DBSchemaManager' => 'SilverStripe\Model\Connect\DBSchemaManager',
                    'MySQLDatabase' => 'SilverStripe\Model\Connect\MySQLDatabase',
                    'MySQLiConnector' => 'SilverStripe\Model\Connect\MySQLiConnector',
                    'MySQLQuery' => 'SilverStripe\Model\Connect\MySQLQuery',
                    'MySQLQueryBuilder' => 'SilverStripe\Model\Connect\MySQLQueryBuilder',
                    'MySQLSchemaManager' => 'SilverStripe\Model\Connect\MySQLSchemaManager',
                    'MySQLStatement' => 'SilverStripe\Model\Connect\MySQLStatement',
                    'PDOConnector' => 'SilverStripe\Model\Connect\PDOConnector',
                    'PDOQuery' => 'SilverStripe\Model\Connect\PDOQuery',
                    'Query' => 'SilverStripe\Model\Connect\Query',

                    'SS_Filterable' => 'SilverStripe\Model\Filterable',
                    'SS_Sortable' => 'SilverStripe\Model\Sortable',
                    'SS_Limitable' => 'SilverStripe\Model\Limitable',
                    'SilverStripe\Model\SS_Sortable' => 'SilverStripe\Model\Sortable',
                    'SilverStripe\Model\SS_Filterable' => 'SilverStripe\Model\Filterable',
                    'SilverStripe\Model\SS_Limitable' => 'SilverStripe\Model\Limitable',

                    'SilverStripe\Model\SQLSelect' => 'SQLSelect',
                    'SilverStripe\Model\Connect\SQLSelect' => 'SQLSelect',

                    'SilverStripe\Model\SS_Database' => 'SilverStripe\Model\Connect\SS_Database',
                    'SilverStripe\Model\FieldList' => 'FieldList',

                    'SilverStripe\Model\ViewableData' => 'ViewableData',
                    'SilverStripe\Model\i18nEntityProvider' => 'i18nEntityProvider',
                    'SilverStripe\Model\Object' => 'Object',
                    'SilverStripe\Model\Extension' => 'Extension',
                    'SilverStripe\Model\ClassInfo' => 'ClassInfo',
                    'SilverStripe\Model\ArrayAccess' => 'ArrayAccess',
                    'SilverStripe\Model\Cookie' => 'Cookie',
                    'SilverStripe\Model\Injector' => 'Injector',
                    'SilverStripe\Model\Config' => 'Config',
                    'SilverStripe\Model\Connect\Config' => 'Config',
                    'SilverStripe\Model\Connect\Iterator' => 'Iterator',
                    'SilverStripe\Model\Connect\MySQLi' => 'MySQLi',
                    'SilverStripe\Model\Connect\mysqli_stmt' => 'mysqli_stmt',
                    'SilverStripe\Model\Connect\SQLFormatter' => 'SQLFormatter',
                    'SilverStripe\Model\Connect\Exception' => 'Exception',
                    'SilverStripe\Model\Connect\Object' => 'Object',
                    'SilverStripe\Model\Connect\Convert' => 'Convert',
                ],
            ])
        ]);

        $upgrader = new Upgrader($spec);
        $upgrader->setLogger($output);

        // Load the code to be upgraded and run the upgrade process
        $output->writeln("Running upgrades on " . $settings['path']);
        $code = new DiskCollection($settings['path']);
        $changes = $upgrader->upgrade($code, $settings['from']);

        print_r($changes);

        // Display the resulting changes
        $display = new ChangeDisplayer();
        $display->displayChanges($output, $changes);

        // Apply them to the project
        if (!empty($settings['write'])) {
            $code->applyChanges($changes);
        }

    }
}
