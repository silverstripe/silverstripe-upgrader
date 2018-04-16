<?php

namespace SilverStripe\Upgrader\Util;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\Lexer;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\EnvironmentVisitor;

/**
 * Utility class to help parse the content of an environment file.
 */
class LegacyEnvParser
{

    /**
     * Content of the file to parse
     * @var ItemInterface
     */
    private $file;

    /**
     * `abstract syntax tree` representing the content of our environement file.
     * @var array
     */
    private $ast;

    /**
     * Root Path of the SS3 site.
     * @var string
     */
    private $rootPath;

    public function __construct(ItemInterface $file, string $rootPath)
    {
        $this->file = $file;
        $this->rootPath = $rootPath;
        $this->parse();
    }

    public function isValid()
    {
        $traverser = new NodeTraverser;

        $visitor = new EnvironmentVisitor();

        $traverser->addVisitor($visitor);
        $traverser->traverse($this->ast);

        return $visitor->getIsValid();
    }

    // private function

    private function parse() {
        $lexer = new Lexer\Emulative([
            'usedAttributes' => ['comments', 'startFilePos', 'endFilePos', 'startLine', 'endLine']
        ]);

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP5, $lexer);
        $this->ast = $parser->parse($this->file->getContents());
    }

    public function getSSFourEnv()
    {
        $orig = get_defined_constants();
        include $this->file->getFullPath();
        $after = get_defined_constants();
        $consts = array_diff_key($after, $orig);

        global $_FILE_TO_URL_MAPPING;
        if (isset($_FILE_TO_URL_MAPPING[$this->rootPath])) {
            $consts['SS_BASE_URL'] = $_FILE_TO_URL_MAPPING[$this->rootPath];
        }

        return $consts;
    }
}
