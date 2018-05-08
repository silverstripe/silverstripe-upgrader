<?php

namespace SilverStripe\Upgrader\Composer;

/**
 * Represent a Packagist package
 */
class Recipe
{

    /**
     * Get a list of known recipes.
     * @yields Recipe
     * @return \Generator
     */
    public static function getKnownRecipes()
    {
        foreach (Package::SUPPORTED_MODULES as $packageName) {
            if (self::isRecipe($packageName)) {
                yield new self(new Package($packageName));
            }
        }
    }

    /**
     * Check if the provided package name looks like a recipe name.
     * @param  string $packageName
     * @return boolean
     */
    public static function isRecipe(string $packageName): bool
    {
        return
            preg_match('/^cwp\/cwp-recipe-/', $packageName) ||
            preg_match('/^silverstripe\/recipe-/', $packageName);
    }

    /**
     * @var Package
     */
    private $package;

    /**
     * Recipe constructor.
     * @param Package $package Base package info for this recipe.
     */
    public function __construct(Package $package)
    {
        $this->package = $package;
    }

    /**
     * Get the name of the recipe as defined in the package.
     * @return string
     */
    public function getName()
    {
        return $this->package->getName();
    }

    /**
     * Generate an array of dependencies where recipe packages can contains sub dependencies. A dependency can only
     * appear in branch once.
     * @param string[] $branch A flat array of dependency already in the branch that should not be added again. This is
     * just there in case we end up with recursive dependencies.
     * @return array
     */
    public function buildDependencyTree(array $branch = []): array
    {
        $requires = $this->package->getRequiredPackages();
        $branch[] = $this->package->getName();
        $tree = [];
        foreach ($requires as $require) {
            if ($require == 'silverstripe/recipe-plugin') {
                // Ignore recipe-plugin
                continue;
            }

            if (in_array($require, $branch)) {
                // IF this package is already on our branch we'll just ignore it
                // to make sure we don't have an infinite loop
                continue;
            } elseif (self::isRecipe($require)) {
                $subRecipe = new self(new Package($require));
                $tree[$require] = $subRecipe->buildDependencyTree($branch);
            } else {
                $tree[$require] = [];
            }
        }

        return $tree;
    }

    /**
     * Retrieve a subset of dependencies that could be remove from the provided list if this recipe was installed.
     *
     * Will return an empty array if the provided dependencies that do not contain all the dependencies required by
     * this recipe.
     * @param  string[] $dependencies List of dependencies are package currently has installed.
     * @param  array $tree Tree of dependencies to loop through. If left blank we'll use the tree of this recipe.
     * @return array List of dependencies this recipe can replace.
     */
    public function subsetOf(array $dependencies, array $tree = []): array
    {
        $tree = $tree ?: $this->buildDependencyTree();

        $intersection = [];
        foreach ($tree as $branch => $subtree) {
            if (in_array($branch, $dependencies)) {
                // We found our branch explicitly defined in our list of dependencies.
                $intersection[] = $branch;
                // Add any sub branches to our results array.
                if (!empty($subTree)) {
                    $subSetIntersection = $this->subsetOf($dependencies, $subtree);
                    $intersection = array_merge($intersection, $subSetIntersection);
                }
            } elseif (!empty($subTree)) {
                // We are dealing with a recipe. If all the dependencies of our recipe are in the list.
                // We'll implicetly include it.
                $subSetIntersection = $this->subsetOf($dependencies, $subtree);
                if ($subSetIntersection) {
                    $intersection[] = $branch;
                    $intersection = array_merge($intersection, $subSetIntersection);
                } else {
                    // Dependency was not met
                    return [];
                }
            } else {
                // Dependency was not met
                return [];
            }
        }

        return array_unique($intersection);
    }
}
