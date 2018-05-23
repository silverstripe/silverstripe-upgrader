<?php

namespace SilverStripe\Upgrader\Tests\Util;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Tests\Console\MockAutomatedCommand;
use SilverStripe\Upgrader\Tests\Console\MockFailedAutomatedCommand;
use SilverStripe\Upgrader\Util\CommandRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandRunnerTest extends TestCase
{

    const STANDARD_SS3 = [
        'mysite' => [
            '_config.php' => '<?php',
            'code' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            '_config' => [
                'config.yml' => '# Some YML comment'
            ]
        ]
    ];

    const STANDARD_SS4 = [
        'app' => [
            '_config.php' => '<?php',
            'src' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            '_config' => [
                'config.yml' => '# Some YML comment'
            ]
        ]
    ];

    public function testRun()
    {
        $app = new Application();
        $first = new MockAutomatedCommand('first');
        $second = new MockAutomatedCommand('second');

        $app->add($first);
        $app->add($second);

        $runner = new CommandRunner();
        $runner->run($app, ['first', 'second'], [], new BufferedOutput());

        $this->assertTrue(
            $first->state['execute'],
            'first command should have been executed.'
        );

        $this->assertTrue(
            $second->state['execute'],
            'second command should have been executed.'
        );

        $args = $first->updatedArguments();
        $this->assertEquals(1, $args['--exec'], 'exec should have been initialise by first.');

        $args = $second->updatedArguments();
        $this->assertEquals(2, $args['--exec'], 'exec should have been relayed to second and incremented.');
    }

    public function testFailedRun()
    {
        $this->expectException(RuntimeException::class);

        $app = new Application();
        $bad = new MockFailedAutomatedCommand('bad');
        $good = new MockAutomatedCommand('good');

        $app->add($bad);
        $app->add($good);

        $runner = new CommandRunner();
        $runner->run($app, ['bad', 'good'], ['--exec' => 0], new BufferedOutput());
    }

    public function testAddProjectFolders()
    {

        $runner = new CommandRunner();

        // Testing with an SS3 set up
        $root = vfsStream::setup('ss_project_root', null, self::STANDARD_SS3);
        $args = $runner->addProjectFolders(['--root-dir' => $root->url()]);
        $this->assertEquals(
            [
                '--root-dir' => $root->url(),
                'project-path' => $root->url() . DIRECTORY_SEPARATOR . 'mysite',
                'code-path' => $root->url() . DIRECTORY_SEPARATOR . 'mysite' . DIRECTORY_SEPARATOR . 'code',
            ],
            $args
        );

        // Testing with an SS4 set up
        $root = vfsStream::setup('ss_project_root', null, self::STANDARD_SS4);
        $args = $runner->addProjectFolders(['--root-dir' => $root->url()]);
        $this->assertEquals(
            [
                '--root-dir' => $root->url(),
                'project-path' => $root->url() . DIRECTORY_SEPARATOR . 'app',
                'code-path' => $root->url() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'src',
            ],
            $args
        );
    }
}
