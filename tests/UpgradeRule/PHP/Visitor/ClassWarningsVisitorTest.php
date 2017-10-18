<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP\Visitor;

use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\ClassWarningsVisitor;
use SilverStripe\Upgrader\Util\ApiChangeWarningSpec;

class ClassWarningsVisitorTest extends BaseVisitorTest
{
    public function testBaseClass()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

class MyClass extends SomeClass
{
}
PHP;

        $visitor = new ClassWarningsVisitor([
            (new ApiChangeWarningSpec('SomeClass', 'Error with SomeClass'))
        ], $this->getMockFile($input));

        $this->traverseWithVisitor($input, $visitor);

        $warnings = $visitor->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertContains('Error with SomeClass', $warnings[0]->getMessage());
        $this->assertContains('class MyClass extends SomeClass', $this->getLineForWarning($input, $warnings[0]));
    }

    public function testBaseClassWithNamespace()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

use SomeNamespace\SomeClass;

class MyClass extends SomeClass
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
        $this->assertContains('class MyClass extends SomeClass', $this->getLineForWarning($input, $warnings[0]));
    }

    public function testBaseClassWithInlineNamespace()
    {

        $input = <<<PHP
<?php

namespace MyNamespace;

class MyClass extends SomeNamespace\SomeClass
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
            'class MyClass extends SomeNamespace\\SomeClass',
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
