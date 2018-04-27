<?php

namespace SilverStripe\Upgrader\Composer;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/**
 * Represent a packagist package
 */
class Package {

    const SUPPORTED_MODULES = [
        "assertchris/hash-compat",
        "colymba/gridfield-bulk-editing-tools",
        "composer/installers",
        "cwp-themes/default",
        "cwp/agency-extensions",
        "cwp/cwp",
        "cwp/cwp-core",
        "cwp/cwp-pdfexport",
        "cwp/cwp-recipe-basic",
        "cwp/cwp-recipe-basic-dev",
        "cwp/cwp-recipe-blog",
        "cwp/cwp-search",
        "cwp/starter-theme",
        "cwp/watea-theme",
        "guzzle/guzzle",
        "hafriedlander/phockito",
        "hafriedlander/silverstripe-phockito",
        "symbiote/silverstripe-gridfieldextensions",
        "symbiote/silverstripe-advancedworkflow",
        "silverstripe/akismet",
        "silverstripe/auditor",
        "silverstripe/blog",
        "silverstripe/campaign-admin",
        "silverstripe/cms",
        "silverstripe/comment-notifications",
        "silverstripe/comments",
        "silverstripe/contentreview",
        "silverstripe/content-widget",
        "silverstripe/cwp-recipe-cms",
        "silverstripe/cwp-recipe-core",
        "silverstripe/cwp-recipe-search",
        "silverstripe/documentconverter",
        "silverstripe/environmentcheck",
        "silverstripe/errorpage",
        "silverstripe/externallinks",
        "silverstripe/framework",
        "silverstripe/fulltextsearch",
        "silverstripe/graphql",
        "silverstripe/graphql-devtools",
        "silverstripe/html5",
        "silverstripe/hybridsessions",
        "silverstripe/iframe",
        "silverstripe/lumberjack",
        "silverstripe/mimevalidator",
        "symbiote/silverstripe-multivaluefield",
        "symbiote/silverstripe-queuedjobs",
        "silverstripe/recipe-authoring-tools",
        "silverstripe/recipe-blog",
        "silverstripe/recipe-collaboration",
        "silverstripe/recipe-form-building",
        "silverstripe/recipe-reporting-tools",
        "silverstripe/recipe-services",
        "silverstripe/registry",
        "silverstripe/reports",
        "silverstripe/restfulserver",
        "silverstripe/secureassets",
        "silverstripe/securityreport",
        "silverstripe/segment-field",
        "silverstripe/selectupload",
        "silverstripe/sharedraftcontent",
        "silverstripe/siteconfig",
        "silverstripe/sitewidecontent-report",
        "silverstripe/spamprotection",
        "silverstripe/spellcheck",
        "silverstripe/subsites",
        "silverstripe/tagfield",
        "silverstripe/taxonomy",
        "silverstripe/textextraction",
        "silverstripe/translatable",
        "silverstripe/userforms",
        "silverstripe/vendor-plugin",
        "silverstripe/versioned-admin",
        "symbiote/silverstripe-versionedfiles",
        "silverstripe/versionfeed",
        "silverstripe/widgets",
        "symfony/yaml",
        "tijsverkoyen/akismet",
        "tractorcow/silverstripe-fluent",
        "undefinedoffset/sortablegridfield",
    ];

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    private $data;

    /**
     * Instanciate a new Package object.
     * @param string $packageName   Name of the package to fetch.
     * @param array  $data          Initial data to populate the package with.
     */
    public function __construct(
        string $packageName,
        array $data = []
    ) {
        $this->name = $packageName;
        $this->data = $data;
    }

    /**
     * Get the raw data associated to this package.
     * @return array
     */
    public function getData(): array
    {
        if (!$this->data) {
            $packagist = new Packagist();
            $json = $packagist->findPackageByName($this->name);

            $this->data = $json['packages'][$this->name];
        }

        return $this->data;
    }

    public function getDevMaster(): array
    {
        return $this->getData()['dev-master'];
    }

    /**
     * Retrieve the most recent version matching the provided constraint. If no version of this package match the
     * constraint, null will be returned.
     * @param  string $constraint
     * @return PackageVersion|null
     */
    public function getVersion(string $constraint)
    {
        // Find all version that meet our constraint
        $versions = Semver::satisfiedBy($this->getVersionNumbers(), $constraint);

        // If we can't fin any version that meet our constaint, return null
        if (empty($versions)) {
            return null;
        }

        // Get the latest version ID
        $versions = $this->sortVersions($versions);
        $versionID = array_shift($versions);

        return new PackageVersion($this->getData()[$versionID]);
    }

    /**
     * Return an array of versions for this packages meeting the provided constraing sorted by stability first and most
     * recent second.
     * @param  string $constraint
     * @return string[]
     */
    public function getVersionNumbers(string $constraint = '*')
    {
        $versions = array_keys($this->getData());

        $versions = Semver::satisfiedBy($versions, $constraint);
        $versions = $this->sortVersions($versions);

        return $versions;
    }

    /**
     * Take a list of versions and sort first by stability and by version. (Most recent stable first, Second most r
     * ecent stable sec, etc.)
     * @param  string[]  $versions
     * @return string[] Sorted version
     */
    protected function sortVersions(array $versions): array
    {
        $versions = Semver::rsort($versions);
        $sortedVersions = [
            'stable' => [],
            'RC' => [],
            'beta' => [],
            'alpha' => [],
            'dev' => []
        ];

        foreach ($versions as $ver) {
            $stability = VersionParser::parseStability($ver);
            $sortedVersions[$stability][] = $ver;
        }

        return array_merge(
            $sortedVersions['stable'],
            $sortedVersions['RC'],
            $sortedVersions['beta'],
            $sortedVersions['alpha'],
            $sortedVersions['dev']
        );
    }

    /**
     * Check if this package is specifically designed to work with SilverStripe, based off its type.
     * @return bool
     */
    public function isSilverStripeRelated(): bool
    {
        return (bool)preg_match('/^silverstripe-.*$/', $this->getType());
    }

    /**
     * Check if this package is specifically designed to work with SilverStripe, based off its type.
     * @return bool
     */
    public function isSilverStripeModule(): bool
    {
        return (bool)preg_match('/^silverstripe-.*module/', $this->getType());
    }

    public function getType()
    {
        return $this->getDevMaster()['type'];
    }


    /**
     * Get the latest version of the package that is compatible with the provided version of silverstripe.
     * @param string $ssVersion
     * @return PackageVersion|null
     */
    public function getVersionBySilverStripeCompatibility(string $ssVersion)
    {
        $versionNumbers = $this->getVersionNumbers();

        foreach ($versionNumbers as $versionNum) {
            $packageVersion = new PackageVersion($this->getData()[$versionNum]);
            $constraint = $packageVersion->getFrameworkConstraint();
            if ($constraint && Semver::satisfies($ssVersion, $constraint)) {
                return $packageVersion;
            }
        }

        return null;
    }


}
