<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ClassWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

class ClassWarningsVisitorTest extends BaseVisitorTest
{
    /**
     * @runInSeparateProcess
     */
    public function testBaseClass()
    {
        // mock someclass
        $someclass = <<<PHP
<?php

namespace MyNamespace;

class SomeClass {}
PHP;
        $this->getMockFile($someclass, 'SomeClass.php');

        // Mock myclass
        $myclass = <<<PHP
<?php

namespace MyNamespace;

class MyClass extends SomeClass
{
}
PHP;
        $item = $this->getMockFile($myclass, 'MyClass.php');

        $visitor = new ClassWarningsVisitor([
            new ApiChangeWarningSpec('MyNamespace\\SomeClass', 'Error with SomeClass')
        ], $item);

        $this->traverseWithVisitor($item, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertContains('Error with SomeClass', $warnings[0]->getMessage());
        $this->assertContains('class MyClass extends SomeClass', $this->getLineForWarning($myclass, $warnings[0]));
    }

    /**
     * @runInSeparateProcess
     */
    public function testBaseClassWithNamespace()
    {
        // mock someclass
        $someclass = <<<PHP
<?php

namespace SomeNamespace;

class SomeClass {}
PHP;
        $this->getMockFile($someclass, 'SomeClass.php');

        // Mock myclass
        $myclass = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;

class MyClass extends SomeClass
{
}
PHP;

        $item = $this->getMockFile($myclass, 'MyClass.php');

        $visitor = new ClassWarningsVisitor([
            (new ApiChangeWarningSpec('SomeNamespace\\SomeClass', 'Error with SomeNamespace\\SomeClass'))
        ], $item);

        $this->traverseWithVisitor($item, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertContains('Error with SomeNamespace\\SomeClass', $warnings[0]->getMessage());
        $this->assertContains('class MyClass extends SomeClass', $this->getLineForWarning($myclass, $warnings[0]));
    }

    public function testBaseClassWithInlineNamespace()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

class MyClass extends \SomeNamespace\SomeClass
{
}
PHP;

        $visitor = new ClassWarningsVisitor([
            (new ApiChangeWarningSpec('SomeNamespace\\SomeClass', 'Error with SomeNamespace\\SomeClass'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertContains('Error with SomeNamespace\\SomeClass', $warnings[0]->getMessage());
        $this->assertContains(
            'class MyClass extends \SomeNamespace\\SomeClass',
            $this->getLineForWarning($input, $warnings[0])
        );
    }

    public function testStaticClassUse()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\\SomeClass;

class MyClass
{
    function foo()
    {
        SomeClass::bar();
    }
}
PHP;

        $visitor = new ClassWarningsVisitor([
            (new ApiChangeWarningSpec('SomeNamespace\\SomeClass', 'Error with SomeNamespace\\SomeClass'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertContains('Error with SomeNamespace\\SomeClass', $warnings[0]->getMessage());
        $this->assertContains('SomeClass::bar()', $this->getLineForWarning($input, $warnings[0]));
    }

    public function testInstanciation()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\\SomeClass;

class MyClass
{
    function foo()
    {
        \$foo = new SomeClass();
    }
}
PHP;

        $visitor = new ClassWarningsVisitor([
            (new ApiChangeWarningSpec('SomeNamespace\\SomeClass', 'Error with SomeNamespace\\SomeClass'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertContains('Error with SomeNamespace\\SomeClass', $warnings[0]->getMessage());
        $this->assertContains('new SomeClass()', $this->getLineForWarning($input, $warnings[0]));
    }
}
