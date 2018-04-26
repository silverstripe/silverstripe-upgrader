<?php

namespace SilverStripe\Upgrader\Composer;

use GuzzleHttp\Client;
use Spatie\Packagist\Packagist;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/**
 * Represent a packagist package
 */
class Package {

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Client
     */
    protected $http;

    /**
     * @var array
     */
    private $data;

    /**
     * Instanciate a new Package object.
     * @param string $packageName   Name of the package to fetch.
     * @param array  $data          Initial data to populate the package with.
     * @param Client $http          HTTP client use to retrieve the pacakge data from pacakagist.
     */
    public function __construct(
        string $packageName,
        array $data = [],
        Client $http=null
    ) {
        $this->name = $packageName;
        $this->data = $data;
        $this->http = $http ?: new Client();
    }

    /**
     * Get the raw data associated to this package.
     * @return array
     */
    public function getData(): array
    {
        if (!$this->data) {
            $packagist = new Packagist($this->http);
            $json = $packagist->findPackageByName($this->name);

            $this->data = $json['package'];
        }

        return $this->data;
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

        return new PackageVersion($this->getData()['versions'][$versionID]);
    }

    /**
     * Return an array of versions for this packages meeting the provided constraing sorted by stability first and most
     * recent second.
     * @param  string $constraint
     * @return string[]
     */
    public function getVersionNumbers(string $constraint = '*')
    {
        $versions = array_keys($this->getData()['versions']);
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



}
