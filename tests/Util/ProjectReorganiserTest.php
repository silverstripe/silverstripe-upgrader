<?php

namespace SilverStripe\Upgrader\Tests\Util;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\Util\ProjectReorganiser;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use M1\Env\Parser;
use LogicException;

class ProjectReorganiserTest extends TestCase
{
    const NON_STANDARD_STRUCTURE = [
        'customProjectFolder' => [
            '_config.php' => '<?php',
            'code' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            'src' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
        ]
    ];
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
    const NON_STANDARD_SS3 = [
        'mysite' => [
            '_config.php' => '<?php',
            'src' => [
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
    const NON_STANDARD_UPGRADABLE_SS4 = [
        'app' => [
            '_config.php' => '<?php',
            'code' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            '_config' => [
                'config.yml' => '# Some YML comment'
            ]
        ]
    ];
    const NON_STANDARD_NON_UPGRADABLE_SS4 = [
        'app' => [
            '_config.php' => '<?php',
            'code' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            'src' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            '_config' => [
                'config.yml' => '# Some YML comment'
            ]
        ]
    ];
    const NON_STANDARD_NON_UPGRADABLE_SS3 = [
        'mysite' => [
            '_config.php' => '<?php',
            'code' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            'src' => [
                'Page.php' => '<?php class Page extends SiteTree { }'
            ],
            '_config' => [
                'config.yml' => '# Some YML comment'
            ]
        ]
    ];

    public function testCheckProjectFolder()
    {
        // Testing a folder that doesn't make sense to us
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_STRUCTURE);
        $this->assertEquals(
            $reorg->checkProjectFolder(),
            ProjectReorganiser::NOTHING,
            'Non-standard project folders should return `ProjectReorganiser::NOTHING`'
        );

        // Testing a folder that can be upgraded
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS3);
        $this->assertEquals(
            $reorg->checkProjectFolder(),
            ProjectReorganiser::UPGRADABLE_LEGACY,
            'A standard SS3 Project should return `ProjectReorganiser::UPGRADABLE_LEGACY`'
        );

        // Testing a folder that has already been upgraded
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS4);
        $this->assertEquals(
            $reorg->checkProjectFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            'A standard SS4 Project should return `ProjectReorganiser::ALREADY_UPGRADED`'
        );

        // Testing a project that has both mysite and app folders in it.
        $reorg = $this->initProjectReorganiser(array_merge(
            self::STANDARD_SS3,
            self::STANDARD_SS4
        ));
        $this->assertEquals(
            $reorg->checkProjectFolder(),
            ProjectReorganiser::BLOCKED_LEGACY,
            'A project with both a mysite and app folder should return `ProjectReorganiser::BLOCKED_LEGACY`'
        );
    }

    public function testCheckCodeFolder()
    {
        // Testing a folder that doesn't make sense to us
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_STRUCTURE);
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::NOTHING,
            'Non-standard project folders should return `ProjectReorganiser::NOTHING`'
        );

        // Testing a folder that can be upgraded
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS3);
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::UPGRADABLE_LEGACY,
            'A standard SS3 Project should return `ProjectReorganiser::UPGRADABLE_LEGACY`'
        );
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_UPGRADABLE_SS4);
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::UPGRADABLE_LEGACY,
            'A SS4 Project with a `code` folder should return `ProjectReorganiser::UPGRADABLE_LEGACY`'
        );

        // Testing a folder that has already been upgraded
        $root = vfsStream::setup('ss_project_root', null, self::STANDARD_SS4);
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS4);
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            'A standard SS4 Project should return `ProjectReorganiser::ALREADY_UPGRADED`'
        );
        $root = vfsStream::setup('ss_project_root', null, self::STANDARD_SS3);
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS4);
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            'A SS3 Project with `src` folder should return `ProjectReorganiser::ALREADY_UPGRADED`'
        );

        // Testing a project that has both code and src folders in it.
        $reorg = $this->initProjectReorganiser(array_merge(
            self::STANDARD_SS3,
            self::STANDARD_SS4
        ));
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::BLOCKED_LEGACY,
            'A project with both a mysite and app folder should return `ProjectReorganiser::BLOCKED_LEGACY`'
        );
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_NON_UPGRADABLE_SS3);
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::BLOCKED_LEGACY,
            'An SS3 project with both a code and src folder should return `ProjectReorganiser::BLOCKED_LEGACY`'
        );
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_NON_UPGRADABLE_SS4);
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::BLOCKED_LEGACY,
            'An SS4 project with both a code and src folder should return `ProjectReorganiser::BLOCKED_LEGACY`'
        );
    }

    public function testMoveProjectFolder()
    {
        // Standard migration
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS3);
        $this->assertEquals(
            $reorg->moveProjectFolder(),
            ['vfs://ss_project_root/mysite' => 'vfs://ss_project_root/app'],
            '`mysite` should have been renamed to `app`'
        );
        $this->assertEquals(
            $reorg->checkProjectFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            '`checkProjectFolder` should return `ProjectReorganiser::ALREADY_UPGRADED` after sucessfull `moveProjectFolder`.'
        );

        // Trying to migrate something that doesn't need to be migrated.
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS4);
        $this->assertEmpty(
            $reorg->moveProjectFolder(),
            'Moving the project folder in a regular SS4 set up should not do anything.'
        );
        $this->assertEquals(
            $reorg->checkProjectFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            '`checkProjectFolder` should still returned `ProjectReorganiser::ALREADY_UPGRADED` after sucessfull `moveProjectFolder`.'
        );

        // Trying to migrate something for which we can't find a mysite folder.
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_STRUCTURE);
        $this->assertEmpty(
            $reorg->moveProjectFolder(),
            'Moving the project folder in non standard setup should not do anything.'
        );
        $this->assertEquals(
            $reorg->checkProjectFolder(),
            ProjectReorganiser::NOTHING,
            '`checkProjectFolder` should still returned `ProjectReorganiser::NOTHING` after calling `moveProjectFolder` on a non-standard project folder.'
        );

    }

    public function testInvalidMoveProjectFolder()
    {
        $this->expectException(
            LogicException::class,
            '`moveProjectFolder` should not let you override an existing folder.'
        );

        // Trying to migrate something where our moving mysite would override something else.
        $reorg = $this->initProjectReorganiser(array_merge(
            self::STANDARD_SS3,
            self::STANDARD_SS4
        ));
        $reorg->moveProjectFolder();

    }

    public function testMoveCodeFolder()
    {
        // Standard use case where we rename `code` to `src`
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS3);
        $this->assertEquals(
            $reorg->moveCodeFolder(),
            ['vfs://ss_project_root/mysite/code' => 'vfs://ss_project_root/mysite/src'],
            '`code` should have been renamed to `src`'
        );
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            '`checkCodeFolder` should return `ProjectReorganiser::ALREADY_UPGRADED` after sucessfull `moveCodeFolder`.'
        );
        $this->assertEmpty(
            $reorg->moveCodeFolder(),
            'Successive call to `moveCodeFolder` should not do anything'
        );

        // `code` has already been renamed.
        $reorg = $this->initProjectReorganiser(self::STANDARD_SS4);
        $this->assertEmpty(
            $reorg->moveCodeFolder(),
            'Moving the project folder in a regular SS4 set up should not do anything.'
        );
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            '`checkProjectFolder` should still returned `ProjectReorganiser::ALREADY_UPGRADED` after sucessfull `moveCodeFolder`.'
        );

        // `app` has `code` folder that needs to be renamed to `src`
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_UPGRADABLE_SS4);
        $this->assertEquals(
            $reorg->moveCodeFolder(),
            ['vfs://ss_project_root/app/code' => 'vfs://ss_project_root/app/src'],
            '`code` should have been renamed to `src`'
        );
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            '`checkProjectFolder` should still returned `ProjectReorganiser::ALREADY_UPGRADED` after sucessfull `moveCodeFolder`.'
        );

        // There's no `mysite`, no `app`
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_STRUCTURE);
        $this->assertEmpty(
            $reorg->moveCodeFolder(),
            'Moving the code folder in non standard setup should not do anything.'
        );
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::NOTHING,
            '`checkCodeFolder` should still returned `ProjectReorganiser::NOTHING` after calling `moveCodeFolder` on a non-standard project folder.'
        );

        // Our mysite folder only has a `src` folder
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_SS3);
        $this->assertEmpty(
            $reorg->moveCodeFolder(),
            'Calling `moveCodeFolder` when there\'s only a `src` folder in mysite should not do anything.'
        );
        $this->assertEquals(
            $reorg->checkCodeFolder(),
            ProjectReorganiser::ALREADY_UPGRADED,
            '`checkCodeFolder` should still returned `ProjectReorganiser::ALREADY_UPGRADED` after calling `moveCodeFolder` on a non-standard project folder.'
        );

    }

    public function testInvalidMoveCodeFolder()
    {
        $this->expectException(
            LogicException::class,
            '`moveCodeFolder` should not let you override an existing folder.'
        );

        // You have an `app` folder with both a `src` and `code` sub-folder.
        $reorg = $this->initProjectReorganiser(self::NON_STANDARD_NON_UPGRADABLE_SS4);
        $reorg->moveCodeFolder();
    }

    private function initProjectReorganiser($structure)
    {
        $root = vfsStream::setup('ss_project_root', null, $structure);
        return new ProjectReorganiser($root->url());
    }

}
