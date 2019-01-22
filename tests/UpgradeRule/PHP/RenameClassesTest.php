<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\PHP\RenameClasses;

class RenameClassesTest extends TestCase
{
    use FixtureLoader;

    /**
     * @return array
     */
    public function provideTests()
    {
        return [
            ['rename-classes.testfixture'],
            ['rename-simple.testfixture'],
            ['rename-strings.testfixture'],
        ];
    }

    /**
     * @dataProvider provideTests
     * @param string $fixture
     */
    public function testNamespaceAddition($fixture)
    {
        list($parameters, $input, $output) = $this->loadFixture(__DIR__.'/fixtures/'.$fixture);
        $updater = (new RenameClasses())->withParameters($parameters);

        // Build mock collection
        $code = new MockCodeCollection([
            'test.php' => $input
        ]);
        $file = $code->itemByPath('test.php');
        $changset = new CodeChangeSet();

        $generated = $updater->upgradeFile($input, $file, $changset);

        $this->assertFalse($changset->hasWarnings('test.php'));
        $this->assertEquals($output, $generated);
    }

    public function testRenameClasses()
    {
        $fixture = 'ambiguous-renames.testfixture';

        list($parameters, $input, $output) = $this->loadFixture(__DIR__.'/fixtures/'.$fixture);
        $updater = (new RenameClasses())->withParameters($parameters);

        // Build mock collection
        $code = new MockCodeCollection([
            'test.php' => $input
        ]);
        $file = $code->itemByPath('test.php');
        $changeset = new CodeChangeSet();

        $generated = $updater->upgradeFile($input, $file, $changeset);

        $this->assertTrue($changeset->hasWarnings('test.php'));
        $this->assertEquals($output, $generated);
    }
}
