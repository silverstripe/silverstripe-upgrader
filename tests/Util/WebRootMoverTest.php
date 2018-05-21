<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\Composer\MockComposer;
use SilverStripe\Upgrader\Util\WebRootMover;
use InvalidArgumentException;

class WebRootMoverTest extends TestCase
{
    // Structure of a vendor folder containing recipe-core
    private $vendorFolder = [
        'vendor' => [
            'silverstripe' => [
                'recipe-core' => [
                    '.htaccess' => '# root htaccess file',
                    'public' => [
                        '.htaccess' => '# public .htaccess',
                        'web.config' => '<!-- public web.config -->',
                        '.gitignore' => '# some files should be ignored',
                        'index.php' => '<?php echo "Do Stuff"'
                    ]
                ]
            ]
        ]
    ];

    // Structure of an assets folder
    private $assetsFolder = [
        'assets' => [
            '.htaccess' => '#boom',
            'web.config' => '<config></config>',
            'Uploads' => [
                'cat.jpg' => 'picture of a cat.'
            ]
        ]
    ];

    // Common files that the root of a project  might contain.
    private $rootFiles = [
        'favicon.ico' => '16x16 pixels of adorable kitten',
        'index.php' => '<?php echo "do some old stuff";'
    ];

    // Server config files that are copied over from silverstripe-installer.
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

    // Server config files that are different from the ones you could get from silverstripe-installer.
    private $changedServerConfig = [
        '.htaccess' => "RewriteEngine On\nRewriteRule ^(.*)$ framework/main.php\$1\n",
        'web.config' => <<<EOF
<!-- Routing configuration for Microsoft IIS web server -->
<configuration>CHANGE!!!!</configuration>

EOF
    ];


    /**
     * @internal checkPrerequisites doesn't have an output. The main thing we care about is that it should throw
     * exceptions.
     */
    public function testCheckPrerequisites()
    {
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Basic test
        $root = vfsStream::setup('ss_project_root');
        $mover = new WebRootMover($root->url(), $composer);
        $this->assertNull($mover->checkPrerequisites());

        // Test with another version of 1.x
        $composer->showOutput[0]['version'] = '1.2.3';
        $this->assertNull($mover->checkPrerequisites());

        // Test with a major upgrade version ... we're future proofing here.
        $composer->showOutput[0]['version'] = '2.0.0';
        $this->assertNull($mover->checkPrerequisites());

        // Test with an empty public folder
        vfsStream::newDirectory('public')->at($root);

        $this->assertNull($mover->checkPrerequisites());
    }

    public function testCheckPrerequisitesFailedNoRecipeCore()
    {
        $this->expectException(
            InvalidArgumentException::class,
            'Recipe-core needs to be installed. An exception should be thrown if it isn\'t'
        );

        $composer = new MockComposer();

        // Basic test
        $root = vfsStream::setup('ss_project_root');
        $mover = new WebRootMover($root->url(), $composer);
        $mover->checkPrerequisites();
    }

    public function testCheckPrerequisitesFailedRecipeCoreVersion()
    {
        $this->expectException(InvalidArgumentException::class);

        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.0.0',
            'description' => 'bla bla bla'
        ]];

        // Basic test
        $root = vfsStream::setup('ss_project_root');
        $mover = new WebRootMover($root->url(), $composer);
        $mover->checkPrerequisites();
    }

    public function testCheckPrerequisitesFailedRecipeCoreDevVersion()
    {
        $this->expectException(InvalidArgumentException::class);

        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.x-dev e7b0f14',
            'description' => 'bla bla bla'
        ]];

        // Basic test
        $root = vfsStream::setup('ss_project_root');
        $mover = new WebRootMover($root->url(), $composer);
        $mover->checkPrerequisites();
    }

    /**
     * @internal checkPrerequisites doesn't have an output. The main thing we care about is that it should throw
     * exceptions.
     */
    public function testCheckPrerequisitesFailedPublicNotEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Test with a npn-empty public folder
        $root = vfsStream::setup('ss_project_root');
        $mover = new WebRootMover($root->url(), $composer);
        $dir = vfsStream::newDirectory('public')->at($root);
        $dir->addChild(vfsStream::newFile('.htaccess')->setContent(''));

        $mover->checkPrerequisites();
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

    public function testMoveAssets()
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
        $mover->moveAssets($diff);
        $this->assertEmpty(
            $diff->allChanges(),
            'moveAssets on an empty project should do nothing.'
        );

        // Testing with an empty project folder
        $root = vfsStream::setup(
            'ss_project_root',
            null,
            array_merge(
                $this->assetsFolder,
                $this->vendorFolder
            )
        );
        $diff = new CodeChangeSet();
        $mover = new WebRootMover($root->url(), $composer);
        $mover->moveAssets($diff);
        $this->assertEquals(
            [
                'assets' => ['path' => 'public/assets']
            ],
            $diff->allChanges(),
            'moveAssets on a project with an assets folder should have move it to public.'
        );
    }

    public function testMoveInstallerFiles()
    {
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Testing with an empty project folder
        $root = vfsStream::setup(
            'ss_project_root',
            null,
            array_merge(
                $this->rootFiles,
                $this->vendorFolder
            )
        );
        $diff = new CodeChangeSet();
        $mover = new WebRootMover($root->url(), $composer);
        $mover->moveInstallerFiles($diff);
        $this->assertEquals(
            [
                'public/index.php' => [
                    'new' => '<?php echo "Do Stuff"',
                    'old' => '',
                    'path' => 'public/index.php'
                ],
                'public/.gitignore' => [
                    'new' => '# some files should be ignored',
                    'old' => '',
                    'path' => 'public/.gitignore'
                ],
                'favicon.ico' => [
                    'path' => 'public/favicon.ico'
                ],
                'index.php' => [
                    'path' => false
                ]
            ],
            $diff->allChanges(),
            'moveInstallerFiles should have move the index.php, .gitignore and favicon.ico files.'
        );
    }

    public function testMove()
    {
        // Set up composer
        $composer = new MockComposer();
        $composer->showOutput = [[
            'name' => 'silverstripe/recipe-core',
            'version' => '1.1.0',
            'description' => 'bla bla bla'
        ]];

        // Set up fake project
        $root = vfsStream::setup(
            'ss_project_root',
            null,
            array_merge(
                $this->unchangedServerConfig,
                $this->vendorFolder,
                $this->rootFiles,
                $this->assetsFolder
            )
        );

        // We running a test on our typical set up. We're not going to run all possible combinations. The point is to
        // test that `move()` successively call our other methods.
        $mover = new WebRootMover($root->url(), $composer);
        $diff = $mover->move();

        $this->assertEquals(
            [
                'public/index.php' => [
                    'new' => '<?php echo "Do Stuff"',
                    'old' => '',
                    'path' => 'public/index.php'
                ],
                'public/.gitignore' => [
                    'new' => '# some files should be ignored',
                    'old' => '',
                    'path' => 'public/.gitignore'
                ],
                'favicon.ico' => [
                    'path' => 'public/favicon.ico'
                ],
                'index.php' => [
                    'path' => false
                ],
                'assets' => ['path' => 'public/assets'],
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
                ],


            ],
            $diff->allChanges(),
            'Calling move on a typical project should permform all the mover\'s operations.'
        );
    }
}
