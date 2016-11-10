<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

use GuzzleHttp\Client;
use Statamic\API\AssetContainer;
use Statamic\API\File as FileAPI;
use Statamic\API\Path;
use Statamic\API\Str;
use Statamic\API\Zip;
use Statamic\Contracts\Assets\Asset;
use Statamic\Extend\API;

/**
 * Class FaviconAPI
 * @package Statamic\Addons\Favicon
 */
class FaviconAPI extends API
{
    /** @var string */
    public $apiKey;

    /** @var string */
    public $assetContainerSlug;

    /** @var \Statamic\Assets\AssetContainer */
    public $assetContainer;

    /** @var \Closure */
    public $_trans;

    protected function init()
    {
        // config
        $this->apiKey = $this->getConfig('apikey');
        $this->assetContainerSlug = $this->getConfig('asset_container');

        if (!empty($this->assetContainerSlug)) {
            $this->assetContainer = AssetContainer::find($this->assetContainerSlug);
        }

        /**
         * Translator for templates
         *
         * @param string $key
         * @param bool|mixed $default Fallback text
         * @param string $prefix
         * @return string
         */
        $this->_trans = function ($key, $default = false, $prefix = 'default.') {
            $nkey = $prefix . $key;
            $text = $this->trans($nkey);

            if ($default !== false && Str::endsWith($text, $nkey)) {
                $text = $default;
            }

            return $text;
        };
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->apiKey) && $this->assetContainer;
    }

    /**
     * @param Asset $asset
     * @return array|string
     */
    public function invokeService(Asset $asset = null)
    {
        $folder = $this->ensureFolder();
        $targetPath = $this->assetContainer->url() . '/' . $folder->path();

        $useBase64 = false;
        $masterPicture = [
            'demo' => false,
        ];

        if ($asset) {
            $url = $asset->absoluteUrl();
            $useBase64 = $this->isLocalUrl($url);

            // see https://realfavicongenerator.net/api/interactive_api
            if ($useBase64) {
                $masterPicture['type'] = 'inline';
                $masterPicture['content'] = base64_encode(file_get_contents(root_path($asset->resolvedPath())));
            } else {
                $masterPicture['type'] = 'url';
                $masterPicture['url'] = $url;
            }
        }

        $data = [
            'favicon_generation' => [
                'api_key' => $this->apiKey,
                'master_picture' => $masterPicture,
                'files_location' => [
                    'type' => 'path',
                    'path' => $targetPath,
                ],
                'callback' => [
                    'type' => 'url',
                    'url' => route('favicon.callback'),
                ],
            ]
        ];

        $params = ['json_params' => json_encode($data)];
        if ($useBase64) {
            return [
                'url' => Config::API_URL,
                'params' => $params,
            ];
        } else {
            return Config::API_URL . '?' . http_build_query($params);
        }
    }

    public function processResponse(\stdClass $data)
    {
        //dd($data);

        // store response
        $this->storage->putJSON('current', $data);

        // download and extract zip file
        $local = $this->downloadFileToAsset($data->favicon->package_url, 'package.zip');
        $this->unpackPackage($local);


        // download preview
        $this->downloadFileToAsset($data->preview_picture_url, 'preview.png');

        // store html code
        FileAPI::disk('theme')->put(Config::PARTIAL_NAME, $data->favicon->html_code);

        // update assets
        $this->assetContainer->sync();
    }

    /**
     * TODO does this work with S3 containers?
     *
     * @param string $url
     * @param string $filename
     * @return string
     */
    private function downloadFileToAsset($url, $filename)
    {
        $directory = root_path($this->assetContainer->resolvedPath() . '/' . Config::ASSET_TARGET_FOLDER);
        $path = $directory . '/' . $filename;

        // download file
        $client = new Client();
        $client->request('GET', $url, ['sink' => $path]);

        return $path;
    }

    /**
     * @param $path
     */
    private function unpackPackage($path)
    {
        $zip = Path::makeRelative($path);
        Zip::extract($zip, dirname($path));
    }

    /**
     * Remove the current favicon
     */
    public function removeFavicon()
    {
        // delete stored data
        $this->storage->putJSON('current', null);

        // empty partial
        FileAPI::disk('theme')->put(Config::PARTIAL_NAME, '');

        // delete files
        $assets = $this->assetContainer->folder(Config::ASSET_TARGET_FOLDER)->assets();
        foreach ($assets as $asset) {
            /** @var Asset $asset */
            try {
                $asset->delete();
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * Tests if a url is a local url not available from the internet
     *
     * @param string $url
     * @return bool
     */
    public function isLocalUrl($url)
    {
        return
            preg_match('#^http(?:s)?://([^/]+\.)?(?:localhost|local|dev)(:\d+)?/#i', $url) || // check for local domains
            preg_match('#^http(?:s)?://(\d+)\.(\d+)\.(\d+)\.(\d+)(:\d+)?\/#i', $url) // check for IPv4 addresses
            ;
    }

    /**
     * @return \Statamic\Contracts\Assets\AssetFolder
     */
    private function ensureFolder()
    {
        $folder = $this->assetContainer->folder(Config::ASSET_TARGET_FOLDER);
        if (!$folder) {
            $folder = $this->assetContainer->createFolder(
                Config::ASSET_TARGET_FOLDER,
                [
                    'title' => $this->trans('default.favicon'),
                ]
            );
        }

        $localPath = root_path($folder->resolvedPath());
        if (!is_dir($localPath)) {
            // FIXME should be done with Disk::
            mkdir($localPath);
        }

        return $folder;
    }
}
