<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP;

use PHPUnit_Framework_TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule;

class ApiChangeWarningsRuleTest extends PHPUnit_Framework_TestCase
{
    use FixtureLoader;

    public function testClassExtendsWithoutReplacement()
    {

        $input = <<<PHP
<?php

use SomeNamespaced\NamespacedClass;

class MyClass extends Object
{
}
PHP;

        $parameters = [
            'warnings' => [
                'classes' => [
                    'Object' => [
                        'message' => 'Classes extending Object need to use traits',
                        'url' => 'https://some-url'
                    ]
                ]
            ]
        ];

        $updater = (new ApiChangeWarningsRule())->withParameters($parameters);

        // Build mock collection
        $code = new MockCodeCollection([
            'test.php' => $input
        ]);
        $file = $code->itemByPath('test.php');
        $changeset = new CodeChangeSet();

        $updater->upgradeFile($input, $file, $changeset);

        $this->assertTrue($changeset->hasWarnings('test.php'));
        $warnings = $changeset->warningsForPath('test.php');
        $this->assertEquals(count($warnings), 1);
        $this->assertContains('test.php:5', $warnings[0]);
        $this->assertContains('Classes extending Object need to use traits', $warnings[0]);
        $this->assertContains('class MyClass extends Object', $this->getLineForWarning($input, $warnings[0]));
    }

    /**
     * @param $input
     * @param $warning
     * @return string|null
     */
    protected function getLineForWarning($input, $warning)
    {
        // Expects "<info>path/file.php:5<info><comment>...</comment>"
        $line = preg_replace('/.*:(\d+).*/', '$1', $warning);
        $lines = explode("\n", $input);

        return is_numeric($line) ? $lines[$line-1] : null;
    }
}
