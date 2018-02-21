<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP;

use PHPUnit_Framework_TestCase;
use SilverStripe\Upgrader\Autoload\CollectionAutoloader;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\Tests\MockCollectionAutoloader;
use SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule;
use SilverStripe\Upgrader\Util\PHPStanState;

class ApiChangeWarningsRuleTest extends PHPUnit_Framework_TestCase
{
    use FixtureLoader;

    /**
     * @var PHPStanState
     */
    protected $state = null;

    /**
     * @var CollectionAutoloader
     */
    protected $autoloader = null;

    protected function setUp()
    {
        parent::setUp();

        // Setup state and autoloading
        $this->state = new PHPStanState();
        $this->state->init();

        $this->autoloader = new MockCollectionAutoloader();
        $this->autoloader->register();
    }

    protected function tearDown()
    {
        // Disable autoloader
        $this->autoloader->unregister();
        parent::tearDown();
    }

    public function testClassExtendsWithoutReplacement()
    {

        $input = <<<PHP
<?php

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

        $updater = (new ApiChangeWarningsRule($this->state->getContainer()))->withParameters($parameters);

        // Build mock collection
        $code = new MockCodeCollection([
            'MyClass.php' => $input,
            'object.php' => '<?php class object {} ',
        ]);
        $this->autoloader->addCollection($code);
        $file = $code->itemByPath('MyClass.php');
        $changeset = new CodeChangeSet();

        $updater->upgradeFile($input, $file, $changeset);

        $this->assertTrue($changeset->hasWarnings('MyClass.php'));
        $warnings = $changeset->warningsForPath('MyClass.php');
        $this->assertEquals(count($warnings), 1);
        $this->assertContains('MyClass.php:3', $warnings[0]);
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
