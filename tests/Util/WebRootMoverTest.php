<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\Composer\MockComposer;
use SilverStripe\Upgrader\Util\WebRootMover;
use InvalidArgumentException;

class WebRootMoverTest extends TestCase
{

    private $vendorFolder = [
        'vendor' => [
            'silverstripe' => [
                'recipe-core' => [
                    '.htaccess' => '# root htaccess file',
                    'public' => [
                        '.htaccess' => '# public .htaccess',
                        'web.config' => '<!-- public web.config -->'
                    ]
                ]
            ]
        ]
    ];

    private $unchangedServerConfig = [
        '.htaccess' => "RewriteEngine On\nRewriteRule ^(.*)$ public/\$1\n",
        'web.config' => <<<EOF
<!-- Routing configuration for Microsoft IIS web server -->
<configuration>
	<system.webServer>
		<security>
			<requestFiltering>
				<hiddenSegments>
					<add segment="silverstripe-cache" />
					<add segment="vendor" />
					<add segment="composer.json" />
					<add segment="composer.lock" />
				</hiddenSegments>
				<fileExtensions allowUnlisted="true" >
					<add fileExtension=".ss" allowed="false"/>
					<add fileExtension=".yml" allowed="false"/>
				</fileExtensions>
			</requestFiltering>
		</security>
	</system.webServer>
</configuration>

EOF
    ];
    private $changedServerConfig = [
        '.htaccess' => "RewriteEngine On\nRewriteRule ^(.*)$ framework/main.php\$1\n",
        'web.config' => <<<EOF
<!-- Routing configuration for Microsoft IIS web server -->
<configuration>CHANGE!!!!</configuration>

EOF
    ];

    /**
     * @var  vfsStreamDirectory
     */
    private $root;

    public function setUp()
    {
        $this->root = vfsStream::setup('ss_project_root');
    }

    /**
     * @internal checkPrerequesites doesn't have an output. The main thing we care about is that it should throw
     * exceptions.
     */
    public function testCheckPrerequesites()
    {
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Basic test
        $mover = new WebRootMover($this->root->url(), $composer);
        $this->assertNull($mover->checkPrerequesites());

        // Test with another version of 1.x
        $composer->showOutput[0]['version'] = '1.2.3';
        $this->assertNull($mover->checkPrerequesites());

        // Test with a major upgrade version ... we're future proofing here.
        $composer->showOutput[0]['version'] = '2.0.0';
        $this->assertNull($mover->checkPrerequesites());

        // Test with an empty public folder
        vfsStream::newDirectory('public')->at($this->root);
        $this->assertNull($mover->checkPrerequesites());
    }

    public function testCheckPrerequesitesFailedNoRecipeCore()
    {
        $this->expectException(InvalidArgumentException::class);

        $composer = new MockComposer();

        // Basic test
        $mover = new WebRootMover($this->root->url(), $composer);
        $mover->checkPrerequesites();
    }

    public function testCheckPrerequesitesFailedRecipeCoreVersion()
    {
        $this->expectException(InvalidArgumentException::class);

        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.0.0',
            'description' => 'bla bla bla'
        ]];

        // Basic test
        $mover = new WebRootMover($this->root->url(), $composer);
        $mover->checkPrerequesites();
    }

    /**
     * @internal checkPrerequesites doesn't have an output. The main thing we care about is that it should throw
     * exceptions.
     */
    public function testCheckPrerequesitesFailedPublicNotEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Test with a npn-empty public folder
        $mover = new WebRootMover($this->root->url(), $composer);
        $dir = vfsStream::newDirectory('public')->at($this->root);
        $dir->addChild(vfsStream::newFile('.htaccess')->setContent(''));

        $mover->checkPrerequesites();
    }

    public function testMoveServerConfigFile()
    {
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Testing with an empty project folder
        $root = vfsStream::setup('ss_project_root', null, $this->vendorFolder);
        $diff = new CodeChangeSet();
        $mover = new WebRootMover($root->url(), $composer);
        $mover->moveServerConfigFile($diff);
        $this->assertEquals(
            [
                '.htaccess' => [
                    'new' => '# root htaccess file',
                    'old' => false,
                    'path' => '.htaccess'
                ],
                'public/.htaccess' => [
                    'new' => '# public .htaccess',
                    'old' => false,
                    'path' => 'public/.htaccess'
                ],
                'public/web.config' => [
                    'new' => '<!-- public web.config -->',
                    'old' => false,
                    'path' => 'public/web.config'
                ]
            ],
            $diff->allChanges(),
            'moveServerConfigFile on an empty project should create .htaccess, public/.htaccess, public/web.config'
        );

        // Testing with unchanged server config files
        $root = vfsStream::setup(
            'ss_project_root',
            null,
            array_merge($this->unchangedServerConfig, $this->vendorFolder)
        );
        $diff = new CodeChangeSet();
        $mover = new WebRootMover($root->url(), $composer);
        $mover->moveServerConfigFile($diff);
        $this->assertEquals(
            [
                '.htaccess' => [
                    'new' => '# root htaccess file',
                    'old' => $this->unchangedServerConfig['.htaccess'],
                    'path' => '.htaccess'
                ],
                'public/.htaccess' => [
                    'new' => '# public .htaccess',
                    'old' => false,
                    'path' => 'public/.htaccess'
                ],
                'web.config' => [
                    'new' => '<!-- public web.config -->',
                    'old' => $this->unchangedServerConfig['web.config'],
                    'path' => 'public/web.config'
                ]
            ],
            $diff->allChanges(),
            'moveServerConfigFile on unchanged project should update'.
            'htaccess, create public/.htaccess, move web.config with updates'
        );

        // Testing with changed server config files
        $root = vfsStream::setup(
            'ss_project_root',
            null,
            array_merge($this->changedServerConfig, $this->vendorFolder)
        );
        $diff = new CodeChangeSet();
        $mover = new WebRootMover($root->url(), $composer);
        $mover->moveServerConfigFile($diff);
        $this->assertEquals(
            [
                '.htaccess' => [
                    'new' => '# root htaccess file',
                    'old' => $this->changedServerConfig['.htaccess'],
                    'path' => '.htaccess'
                ],
                'public/.htaccess' => [
                    'new' => $this->changedServerConfig['.htaccess'],
                    'old' => false,
                    'path' => 'public/.htaccess'
                ],
                'web.config' => [
                    'path' => 'public/web.config'
                ]
            ],
            $diff->allChanges(),
            'moveServerConfigFile on changed project should ' .
            'update .htaccess, create public/.htaccess, move public/web.config without updates'
        );
        $this->assertTrue(
            $diff->hasWarnings(
                'public/.htaccess'
            ),
            'public/.htaccess should have a warning'
        );
        $this->assertTrue(
            $diff->hasWarnings('web.config'),
            'web.config should have a warning'
        );
    }
}
