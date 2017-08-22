<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use Statamic\API\AssetContainer;
use Statamic\API\File;
use Statamic\API\File as FileAPI;
use Statamic\API\Folder;
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

    /** @var string */
    public $assetFolder;

    /** @var string */
    public $partial;

    /** @var \Closure */
    public $_trans;

    protected function init()
    {
        // config
        $this->apiKey = $this->getConfig('apikey');
        $this->assetContainerSlug = $this->getConfig('asset_container');
        $this->assetFolder = $this->getConfig('asset_folder', 'favicon');
        $this->partial = $this->getConfig('partial', 'favicon');

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
        $tempDir = temp_path('favicon');
        $tempDirRel = Path::makeRelative($tempDir);

        /** @var Filesystem $disk */
        $disk = File::disk()->filesystem()->getDriver();
        $disk->deleteDir($tempDirRel); // delete temporary folder
        $disk->createDir($tempDirRel); // create temporary folder

        // add a warning to the folder
        $disk->put(
            $tempDirRel . '/000-DO-NOT-UPLOAD-FILES-IN-HERE.txt',
            'They will be deleted as new icons will be generated!'
        );

        // store response
        $this->storage->putJSON('current', $data);

        // download and extract zip file
        $this->downloadFile($data->favicon->package_url, $tempDir . '/package.zip');
        $this->unpackPackage($tempDir . '/package.zip');

        // download preview
        $this->downloadFile($data->preview_picture_url, $tempDir . '/preview.png');

        // copy temp files to asset container
        $targetDisk = Folder::disk('assets:' . $this->assetContainer->uuid())->filesystem()->getDriver();
        foreach ($disk->listContents($tempDirRel) as $file) {
            $path = $this->assetFolder . '/' . $file['basename'];

            // Move from the temporary location to the real container location
            $targetDisk->put($path, $disk->readStream($file['path']));
        }

        // store html code
        FileAPI::disk('theme')->put($this->getPartialPath(), $data->favicon->html_code);

        // clean up
        $disk->deleteDir($tempDirRel);
    }

    /**
     * @param string $url
     * @param string $filename
     */
    private function downloadFile($url, $filename)
    {
        // download file
        $client = new Client();
        $client->request('GET', $url, ['sink' => $filename]);
    }

    /**
     * @param string $path
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
        FileAPI::disk('theme')->put($this->getPartialPath(), '');

        // delete files
        $this->ensureFolder()->delete();
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
            // check for local domains
            preg_match('#^http(?:s)?://([^/]+\.)?(?:localhost|local|dev)(:\d+)?/#i', $url) ||
            // check for IPv4 addresses
            preg_match('#^http(?:s)?://(\d+)\.(\d+)\.(\d+)\.(\d+)(:\d+)?\/#i', $url);
    }

    /**
     * @return \Statamic\Contracts\Assets\AssetFolder
     */
    private function ensureFolder()
    {
        $folder = $this->assetContainer->assetFolder($this->assetFolder);
        if (!$this->assetContainer->folders()->contains($this->assetFolder)) {
            $folder->set('title', $this->getAddonName());
            $folder->save();
        }

        return $folder;
    }

    /**
     * @return string
     */
    private function getPartialPath()
    {
        return 'partials/' . $this->partial . '.html';
    }
}
