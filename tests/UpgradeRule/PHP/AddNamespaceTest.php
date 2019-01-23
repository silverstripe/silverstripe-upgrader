<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\PHP\AddNamespaceRule;

class AddNamespaceTest extends TestCase
{
    use FixtureLoader;

    /**
     * Test applying namespaces to a folder
     */
    public function testNamespaceFolder()
    {
        list($parameters, $input1, $output1, $input2, $output2) =
            $this->loadFixture(__DIR__ .'/fixtures/add-namespace.testfixture');

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
        $namespacer->beforeUpgradeCollection($code, $changeset);
        $this->assertEquals(
            [
                'ExampleSubclass',
                'RenamedInterface',
                'Traitee',
            ],
            $namespacer->getClassesInNamespace('Upgrader\\NewNamespace')
        );


        // Check loading namespace from config
        $this->assertEquals('Upgrader\\NewNamespace', $namespacer->getNamespaceForFile($file1));
        $this->assertEquals('Upgrader\\NewNamespace', $namespacer->getNamespaceForFile($file2));
        $this->assertNull($namespacer->getNamespaceForFile($otherfile));

        // Test upgrading file1
        $generated1 = $namespacer->upgradeFile($input1, $file1, $changeset);
        $this->assertFalse($changeset->hasWarnings($file1->getPath()));
        $this->assertEquals($output1, $generated1);

        // Test upgrading file2
        $generated2 = $namespacer->upgradeFile($input2, $file2, $changeset);
        $this->assertFalse($changeset->hasWarnings($file2->getPath()));
        $this->assertEquals($output2, $generated2);
    }

    /**
     * Test that skipClasses skips certain files
     */
    public function testNamespaceSkipsClasses()
    {
        list($parameters, $input, $output) =
            $this->loadFixture(__DIR__ .'/fixtures/add-namespace-skipped.testfixture');

        // Build mock collection
        $code = new MockCodeCollection([
            'dir/test1.php' => $input,
        ]);
        $file1 = $code->itemByPath('dir/test1.php');

        // Add spec to rule
        $namespacer = new AddNamespaceRule();
        $namespacer
            ->withParameters($parameters)
            ->withRoot('');

        // Test that pre-post hooks skips all skippedClasses
        $changeset = new CodeChangeSet();
        $namespacer->beforeUpgradeCollection($code, $changeset);
        $this->assertEmpty($namespacer->getClassesInNamespace('Upgrader\\NewNamespace'));

        // Test upgrading file1 is no-op
        $generated = $namespacer->upgradeFile($input, $file1, $changeset);
        $this->assertEquals($output, $input);
        $this->assertEquals($output, $generated);
    }

    public function testPsr4Option()
    {
        list($parameters, $input1, $output1, $input2, $output2, $input3, $output3, $input4, $output4) =
            $this->loadFixture(__DIR__ .'/fixtures/add-namespace-psr4.testfixture');

        // Build mock collection
        $code = new MockCodeCollection([
            'test1.php' => $input1,
            'Foo/test2.php' => $input2,
            'Bar/test3.php' => $input3,
            'Bin/Baz/test4.php' => $input4,
        ]);
        $file1 = $code->itemByPath('test1.php');
        $file2 = $code->itemByPath('Foo/test2.php');
        $file3 = $code->itemByPath('Bar/test3.php');
        $file4 = $code->itemByPath('Bin/Baz/test4.php');

        // Add spec to rule
        $namespacer = new AddNamespaceRule();
        $namespacer
            ->withParameters($parameters)
            ->withRoot('');

        // Check loading namespace from config
        $this->assertEquals('Upgrader\\NewNamespace', $namespacer->getNamespaceForFile($file1));
        $this->assertEquals('Upgrader\\NewNamespace\\Foo', $namespacer->getNamespaceForFile($file2));
        $this->assertEquals('Upgrader\\NewNamespace\\Bar', $namespacer->getNamespaceForFile($file3));
        $this->assertEquals('Upgrader\\NewNamespace\\Bin\\Baz', $namespacer->getNamespaceForFile($file4));

        $changeset = new CodeChangeSet();

        // Test upgrading file1
        $generated1 = $namespacer->upgradeFile($input1, $file1, $changeset);
        $this->assertFalse($changeset->hasWarnings($file1->getPath()));
        $this->assertEquals($output1, $generated1);

        // Test upgrading file2
        $generated2 = $namespacer->upgradeFile($input2, $file2, $changeset);
        $this->assertFalse($changeset->hasWarnings($file2->getPath()));
        $this->assertEquals($output2, $generated2);

        // Test upgrading file3
        $generated3 = $namespacer->upgradeFile($input3, $file3, $changeset);
        $this->assertFalse($changeset->hasWarnings($file3->getPath()));
        $this->assertEquals($output3, $generated3);

        // Test upgrading file4
        $generated4 = $namespacer->upgradeFile($input4, $file4, $changeset);
        $this->assertFalse($changeset->hasWarnings($file4->getPath()));
        $this->assertEquals($output4, $generated4);
    }

    /**
     * Test applying namespaces to a file using scalar parameter type and return types.
     */
    public function testNamespaceUseStatement()
    {
        list($parameters, $input, $output) =
            $this->loadFixture(__DIR__ .'/fixtures/add-namespace-use-statement.testfixture');

        // Build mock collection
        $code = new MockCodeCollection([
            'dir/test1.php' => $input,
        ]);
        $file1 = $code->itemByPath('dir/test1.php');

        // Add spec to rule
        $namespacer = new AddNamespaceRule();
        $namespacer
            ->withParameters($parameters)
            ->withRoot('');

        // Test that pre-post hooks detect namespaced classes
        $changeset = new CodeChangeSet();
        $namespacer->beforeUpgradeCollection($code, $changeset);
        $this->assertEquals(
            ['RenamedInterface'],
            $namespacer->getClassesInNamespace('Upgrader\\NewNamespace')
        );


        // Check loading namespace from config
        $this->assertEquals('Upgrader\\NewNamespace', $namespacer->getNamespaceForFile($file1));

        // Test upgrading file1
        $generated1 = $namespacer->upgradeFile($input, $file1, $changeset);
        $this->assertFalse($changeset->hasWarnings($file1->getPath()));
        $this->assertEquals($output, $generated1);
    }
}
