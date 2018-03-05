<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\Warnings;

use SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\BaseVisitorTest;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\ConstantWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\MutableSource;

class ConstantWarningsVisitorTest extends BaseVisitorTest
{
    /**
     * @runInSeparateProcess
     */
    public function testGlobalConstantAssignment()
    {
        $myClass = <<<PHP
<?php

namespace MyNamespace;

class MyClass
{
    function assignConstant()
    {
        \$foo = 'before' . REMOVED_CONSTANT . 'after';
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new ConstantWarningsVisitor([
            new ApiChangeWarningSpec('REMOVED_CONSTANT', [
                'message' => 'Test REMOVED_CONSTANT',
                'replacement' => 'NEW_CONSTANT',
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test REMOVED_CONSTANT', $warnings[0]->getMessage());
        $this->assertContains(
            '\'before\' . REMOVED_CONSTANT . \'after\'',
            $this->getLineForWarning($myClass, $warnings[0])
        );

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

namespace MyNamespace;

class MyClass
{
    function assignConstant()
    {
        \$foo = 'before' . NEW_CONSTANT . 'after';
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }

    /**
     * @runInSeparateProcess
     */
    public function testClassConstant()
    {
        $myClass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;

class MyClass
{
    function assignConstant()
    {
        \$ignore = REMOVED_CONSTANT;
        
        echo(SomeClass::REMOVED_CONSTANT);
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new ConstantWarningsVisitor([
            new ApiChangeWarningSpec('SomeNamespace\\SomeClass::REMOVED_CONSTANT', [
                'message' => 'Test REMOVED_CONSTANT',
                'replacement' => '\\SomeNamespace\\AnotherClass::NEW_CONSTANT', // Replace entire string
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test REMOVED_CONSTANT', $warnings[0]->getMessage());
        $this->assertContains(
            'SomeClass::REMOVED_CONSTANT',
            $this->getLineForWarning($myClass, $warnings[0])
        );

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;

class MyClass
{
    function assignConstant()
    {
        \$ignore = REMOVED_CONSTANT;
        
        echo(\SomeNamespace\AnotherClass::NEW_CONSTANT);
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }

    /**
     * Tests that replacements that don't specify the class replace only the name component
     *
     * @runInSeparateProcess
     */
    public function testClassConstantSimpleReplace()
    {
        $myClass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;

class MyClass
{
    function assignConstant()
    {
        \$ignore = REMOVED_CONSTANT;
        
        echo(SomeClass::REMOVED_CONSTANT);
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new ConstantWarningsVisitor([
            new ApiChangeWarningSpec('SomeNamespace\\SomeClass::REMOVED_CONSTANT', [
                'message' => 'Test REMOVED_CONSTANT',
                'replacement' => 'NEW_CONSTANT', // note: no `Class::' prefix
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test REMOVED_CONSTANT', $warnings[0]->getMessage());
        $this->assertContains(
            'SomeClass::REMOVED_CONSTANT',
            $this->getLineForWarning($myClass, $warnings[0])
        );

        // Ensure rewrite works
        $newClass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;

class MyClass
{
    function assignConstant()
    {
        \$ignore = REMOVED_CONSTANT;
        
        echo(SomeClass::NEW_CONSTANT);
    }
}
PHP;
        $actualClass = $source->getModifiedString();
        $this->assertNotEquals($source->getOrigString(), $actualClass);
        $this->assertEquals($newClass, $actualClass);
    }
}
