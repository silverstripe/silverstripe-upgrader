<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\Warnings;

use SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor\BaseVisitorTest;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\Warnings\FunctionWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;
use SilverStripe\Upgrader\Util\MutableSource;

class FunctionWarningsVisitorTest extends BaseVisitorTest
{
    /**
     * @runInSeparateProcess
     */
    public function testGlobal()
    {
        $myClass = <<<PHP
<?php

function wrap() {
    myFunction('some-arg');    
    otherFunction();
}

class MyClass
{
    function myFunction()
    {
        \$this->myFunction();
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new FunctionWarningsVisitor([
            new ApiChangeWarningSpec('myFunction()', [
                'message' => 'Test function',
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test function', $warnings[0]->getMessage());
        $this->assertContains('myFunction(\'some-arg\')', $this->getLineForWarning($myClass, $warnings[0]));
    }

    /**
     * @runInSeparateProcess
     */
    public function testIgnoresDynamic()
    {
        $myClass = <<<PHP
<?php

function wrap() {
    myFunction('some-arg');
    \$myFunction();
}

class MyClass
{
    function foo()
    {
        \$this->\$myFunction();
    }
}
PHP;

        $input = $this->getMockFile($myClass);
        $source = new MutableSource($input->getContents());
        $visitor = new FunctionWarningsVisitor([
            new ApiChangeWarningSpec('myFunction()', [
                'message' => 'Test function',
            ])
        ], $source, $input);

        $this->traverseWithVisitor($source, $input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test function', $warnings[0]->getMessage());
        $this->assertContains('myFunction(\'some-arg\')', $this->getLineForWarning($myClass, $warnings[0]));
    }
}
