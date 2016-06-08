<?php

namespace SilverStripe\Upgrader\UpgradeRule;

use Exception;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitor\NameResolver;
use SilverStripe\Upgrader\CodeCollection\ItemInterface;
use SilverStripe\Upgrader\Util\ConfigFile;
use SilverStripe\Upgrader\Util\MutableSource;

/**
 * Upgrade rule that applies namespaces to files
 *
 * @package SilverStripe\Upgrader\UpgradeRule
 */
class AddNamespaceRule extends AbstractUpgradeRule
{
    /**
     * Root dir for project to use to find project files
     *
     * @var string
     */
    protected $root = null;

    /**
     * When adding namespaces to a file, mark each classes in these files as renamed
     * from non-namespaced name => namespaced name
     *
     * @var array
     */
    protected $renamedClasses = [];

    /**
     * Get list of namespaces modifications to add
     *
     * @return array
     */
    protected function getAddedNamespaces()
    {
        if (empty($this->parameters['add-namespace'])) {
            return [];
        }
        return $this->parameters['add-namespace'];
    }

    /**
     * Gets the namespace we want to apply to a given file
     *
     * @param ItemInterface $file
     * @return string
     */
    public function getNamespaceForFile($file)
    {
        $path = $file->getFullPath();
        foreach ($this->getAddedNamespaces() as $modification) {
            // If $path is inside the directory, or matches the file of
            // any add-namespace config, then match it against base rules.
            $nextPath = $this->root . $modification['path'];
            if (stripos($path, $nextPath) === 0) {
                return $modification['namespace'];
            }
        }
        return null;
    }

    /**
     * Set root directory
     *
     * @param string $root
     * @return $this
     */
    public function withRoot($root)
    {
        $this->root = $root;
        return $this;
    }

    public function upgradeFile($contents, $file)
    {
        $this->warningCollector = [];
        if (!$this->appliesTo($file)) {
            return [ $contents, $this->warningCollector ];
        }

        // Build AST from this file
        $source = new MutableSource($contents);

        // Do initial parse of this file
        $visitor = $this->findClasses($source);
        $currentNamespace = $visitor->getNamespace();
        $classes = $visitor->getClasses();

        // We can only add namespace if none is already applied
        $newNamespace = $this->getNamespaceForFile($file);
        if ($currentNamespace) {
            // Validate any already-applied namespace
            $this->validateNamespaceMatches($currentNamespace, $newNamespace);
        } else {
            // add namespace and record mapped classes
            $this->addNamespace($source, $newNamespace, $classes);
            foreach ($classes as $class) {
                $this->renamedClasses[$class] = "{$newNamespace}\\{$class}";
            }
        }

        return [ $source->getModifiedString(), $this->warningCollector ];
    }

    /**
     * Check that the given namespace matches the one we wish to apply to this file
     *
     * @param Namespace_ $current
     * @param string $new
     */
    protected function validateNamespaceMatches($current, $new)
    {
        // Can't apply namespace to existing namespace
        if ((string)$current->name !== $new) {
            $this->addWarning(
                $current->getLine(),
                "Namespace already declared: \"{$current->name}\", skipping file."
            );
        }
    }

    /**
     * Add the given namespace to the source file
     *
     * @param MutableSource $source
     * @param string $namespace
     * @param array $classes List of class names in this file
     */
    protected function addNamespace(MutableSource $source, $namespace, $classes)
    {
        $content = $source->getOrigString();
        // Sanity check
        if (stripos($content, '<?php') !== 0) {
            $this->addWarning(0, "File doesn't start with <?php");
            return;
        }

        // Manually merge namespace; Using AST doesn't respect comments so it comes out ugly.
        $namespaceObj = new Namespace_(new Name($namespace));
        $source->replace(0, 5, "<?php\n\n" . $source->createString($namespaceObj));

        // All class-identifiers in this file will now break. E.g. `new File` will now need to be new `\File`
        $this->transformWithVisitors($source->getAst(), [
            new ClassQualifierVisitor($source, $namespace, $classes, 5),
        ]);
    }

    /**
     * Finds namespace and list of classes/traits/interfaces in this file
     *
     * @param MutableSource $source
     * @return FindClassVisitor
     */
    protected function findClasses(MutableSource $source)
    {
        $classVisitor = new FindClassVisitor();
        $this->transformWithVisitors($source->getAst(), [
            new NameResolver(),
            $classVisitor
        ]);
        return $classVisitor;
    }

    /**
     * Add all namespaced classes to known rename rules
     *
     * @param string $path Path to YML file. Creates this file if it doesn't exist
     * @return number of classes saved
     * @throws Exception
     */
    public function saveMappings($path)
    {
        // Skip if no classes to map
        if (empty($this->renamedClasses)) {
            return 0;
        }

        // Load config file
        $config = ConfigFile::loadConfig($path);

        // Merge all class mapping
        if (!isset($config['mappings'])) {
            $config['mappings'] = [];
        }

        foreach ($this->renamedClasses as $from => $to) {
            // First map any existing rename that maps to an obsolete class name
            $oldFrom = array_search($from, $config['mappings']);
            if ($oldFrom) {
                $config['mappings'][$oldFrom] = $to;
            }
            // Add current mapping
            $config['mappings'][$from] = $to;
        }

        // Write back yaml
        ConfigFile::saveConfig($path, $config);
        return count($this->renamedClasses);
    }

    public function appliesTo($file)
    {
        return $this->getNamespaceForFile($file)
            && parent::appliesTo($file);
    }
}
