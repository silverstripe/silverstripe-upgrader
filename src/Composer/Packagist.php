<?php

namespace SilverStripe\Upgrader\Composer;

use GuzzleHttp\Client;

/**
 * Provides a simple interface for fetching Package information from Packagist.
 *
 * Adapted from by https://github.com/spatie/packagist-api/blob/master/src/Packagist.php by Spatie.
 */
class Packagist
{

    /**
     * @var string[]
     */
    private static $cacheFolders = [];

    /**
     * List of folders that will be check to see if they contain a cached version of the packagist json data.
     * @return string[]
     */
    public static function getCacheFolders(): array
    {
        return self::$cacheFolders;
    }

    /**
     * Add a folder to our caching list.
     * @param string $folder
     */
    public static function addCacheFolder(string $folder)
    {
        self::$cacheFolders[] = $folder;
    }

    /**
     * Explicitly specify the cache folder to check when trying to retrieve information about a package.
     * @param string[] $cacheFolders
     */
    public static function setCacheFolders(array $cacheFolders)
    {
        self::$cacheFolders = $cacheFolders;
    }

    /**
     * Disallow the use of the cache.
     */
    public static function disableCacheFolders()
    {
        self::$cacheFolders = [];
    }

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
    * @param \GuzzleHttp\Client $client
    * @param string             $baseUrl
    */
    public function __construct(Client $client = null, $baseUrl = 'https://packagist.org')
    {
        $this->client = $client ?: new Client();
        $this->baseUrl = $baseUrl;
    }

    /**
     * Retrieve the information for a specific package.
     *
     * @param string $vendor
     * @param string $packageName If left blank, will be retrieved from the vendor.
     *
     * @return array
     */
    public function findPackageByName(string $vendor, string $packageName = ''): array
    {
        // Split package name and vendor
        if ($packageName === '') {
            list($vendor, $packageName) = explode('/', $vendor);
        }

        // Try to find the package info from the cache.
        $data = $this->findCachedPackage($vendor, $packageName);

        // If the info wasn't in the cache let's make a query to packagist.
        if (!$data) {
            $data = $this->makeRequest("/p/{$vendor}/{$packageName}.json");
            $this->writeToCache($vendor, $packageName, $data);
        }

        return $data;
    }

    /**
     * Try to find the specified package in the cache.
     * @param  string $vendor
     * @param  string $packageName
     * @return array|false
     */
    protected function findCachedPackage(string $vendor, string $packageName)
    {
        $filename = $this->getExpectedCacheFilename($vendor, $packageName);

        // Loop through our cache folders until we find our cache file.
        foreach (self::$cacheFolders as $cacheFolder) {
            $fullpath = $cacheFolder . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($fullpath)) {
                $json = json_decode(file_get_contents($fullpath), true);

                return $json;
            }
        }

        // We haven't found our folder, let's bail.
        return false;
    }

    /**
     * Try to write the content of a request to a cache folder.
     */
    protected function writeToCache(string $vendor, string $packageName, array $data)
    {

        if (!empty(self::$cacheFolders)) {
            // We presume the first folder is the most important one so we'll write there.
            $filename =  $this->getExpectedCacheFilename($vendor, $packageName);
            $fullpath = self::$cacheFolders[0] . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($fullpath, json_encode($data));
        }
    }

    /**
     * Fire off a request to packagist.
     *
     * @param string $resource
     * @param array  $query
     *
     * @return array
     */
    public function makeRequest($resource, array $query = [])
    {
        $packages = $this->client
            ->get("{$this->baseUrl}{$resource}", compact('query'))
            ->getBody()
            ->getContents();
        return json_decode($packages, true);
    }

    /**
     * Build the expected cache file name following the composer naming convention. This allows us to piggy back off
     * the composer cache.
     * @param  string $vendor
     * @param  string $packageName
     * @return string
     */
    protected function getExpectedCacheFilename(string $vendor, string $packageName): string
    {
        return 'provider-' . $vendor . '$' . $packageName . '.json';
    }
}
