<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule;

use SilverStripe\Upgrader\CodeChangeSet;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\AddNamespaceRule;

class AddNamespaceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    protected function getFixtures()
    {
        // Get fixture from the file
        $fixture = file_get_contents(__DIR__ .'/fixtures/add-namespace.testfixture');

        list($parameters, $input1, $output1, $input2, $output2) = preg_split('/------+/', $fixture, 5);
        $parameters = json_decode($parameters, true);

        return [$parameters, trim($input1), trim($output1), trim($input2), trim($output2)];
    }

    /**
     * Test applying namespaces to a folder
     */
    public function testNamespaceFolder()
    {
        list($parameters, $input1, $output1, $input2, $output2) = $this->getFixtures();

        // Build mock collection
        $code = new MockCodeCollection([
            'dir/test1.php' => $input1,
            'dir/test2.php' => $input2,
        ]);
        $file1 = $code->itemByPath('dir/test1.php');
        $file2 = $code->itemByPath('dir/test2.php');
        $otherfile = $code->itemByPath('notdir/otherfile.php');

        // Add spec to rule
        $namespacer = new AddNamespaceRule();
        $namespacer
            ->withParameters($parameters)
            ->withRoot('');

        // Test that pre-post hooks detect namespaced classes
        $changeset = new CodeChangeSet();
        $namespacer->beforeUpgrade($code, $changeset);
        $this->assertEquals(
            [
                'ExampleSubclass',
                'RenamedInterface',
                'Traitee',
            ],
            $namespacer->getClassesInNamespace('Upgrader\NewNamespace')
        );


        // Check loading namespace from config
        $this->assertEquals('Upgrader\NewNamespace', $namespacer->getNamespaceForFile($file1));
        $this->assertEquals('Upgrader\NewNamespace', $namespacer->getNamespaceForFile($file2));
        $this->assertNull($namespacer->getNamespaceForFile($otherfile));

        // Test upgrading file1
        list($generated1, $warnings1) = $namespacer->upgradeFile($input1, $file1);
        $this->assertEquals([], $warnings1);
        $this->assertEquals($output1, $generated1);

        // Test upgrading file2
        list($generated2, $warnings2) = $namespacer->upgradeFile($input2, $file2);
        $this->assertEquals([], $warnings2);
        $this->assertEquals($output2, $generated2);
    }
}
