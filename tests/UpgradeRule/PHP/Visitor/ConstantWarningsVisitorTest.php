<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ConstantWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

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
        $visitor = new ConstantWarningsVisitor([
            (new ApiChangeWarningSpec('REMOVED_CONSTANT', 'Test REMOVED_CONSTANT'))
        ], $input);

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test REMOVED_CONSTANT', $warnings[0]->getMessage());
        $this->assertContains(
            '\'before\' . REMOVED_CONSTANT . \'after\'',
            $this->getLineForWarning($myClass, $warnings[0])
        );
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
        $visitor = new ConstantWarningsVisitor([
            (new ApiChangeWarningSpec('SomeNamespace\\SomeClass::REMOVED_CONSTANT', 'Test REMOVED_CONSTANT'))
        ], $input);

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test REMOVED_CONSTANT', $warnings[0]->getMessage());
        $this->assertContains(
            'SomeClass::REMOVED_CONSTANT',
            $this->getLineForWarning($myClass, $warnings[0])
        );
    }
}
