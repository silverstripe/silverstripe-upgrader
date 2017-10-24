<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\FunctionWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

class FunctionWarningsVisitorTest extends BaseVisitorTest
{
    public function testGlobal()
    {

        $input = <<<PHP
<?php

myFunction('some-arg');

otherFunction();

class MyClass
{
    function myFunction()
    {
        \$this->myFunction();
    }
}
PHP;

        $visitor = new FunctionWarningsVisitor([
            (new ApiChangeWarningSpec('myFunction()', 'Test function'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test function', $warnings[0]->getMessage());
        $this->assertContains('myFunction(\'some-arg\')', $this->getLineForWarning($input, $warnings[0]));
    }

    public function testIgnoresDynamic()
    {

        $input = <<<PHP
<?php

myFunction('some-arg');

\$myFunction();

class MyClass
{
    function foo()
    {
        \$this->\$myFunction();
    }
}
PHP;

        $visitor = new FunctionWarningsVisitor([
            (new ApiChangeWarningSpec('myFunction()', 'Test function'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);

        $this->assertContains('Test function', $warnings[0]->getMessage());
        $this->assertContains('myFunction(\'some-arg\')', $this->getLineForWarning($input, $warnings[0]));
    }
}
