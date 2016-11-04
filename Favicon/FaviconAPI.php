<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

use Statamic\API\AssetContainer;
use Statamic\API\Str;
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

    public function invokeService(Asset $asset)
    {
        $folder = $this->ensureFolder();
        $targetPath = $this->assetContainer->url() . '/' . $folder->path();

        $url = $asset->absoluteUrl();
        $useBase64 = $this->isLocalUrl($url);

        // see https://realfavicongenerator.net/api/interactive_api
        $masterPicture = [
            'demo' => false,
        ];
        if ($useBase64) {
            $masterPicture['type'] = 'inline';
            $masterPicture['content'] = base64_encode(file_get_contents(root_path($asset->resolvedPath())));
        } else {
            $masterPicture['type'] = 'url';
            $masterPicture['url'] = $url;
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
                    'url' => url('/!/' . $this->getAddonName() . '/callback'),
                    'custom_parameter' => 'key=' . $this->getSecrectKey()
                ],
            ]
        ];

        return $data;
    }

    /**
     * Tests if a url is a local url not available from the internet
     * TODO unit tests #SFG-6
     *
     * @param string $url
     * @return bool
     */
    public
    function isLocalUrl($url)
    {
        return
            preg_match('#^http(?:s)?://([^/]+\.)?(?:localhost|local|dev)(:\d+)?/#i', $url) || // check for local domains
            preg_match('#^http(?:s)?://(\d+)\.(\d+)\.(\d+)\.(\d+)\/#i', $url) // check for IPv4 addresses
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

        return $folder;
    }

    /**
     * Used to prevent unauthorized calls to the callback url
     *
     * @return string
     */
    private function getSecrectKey()
    {
        return sha1($this->apiKey);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function validateSecretKey($key)
    {
        return $key == $this->getSecrectKey();
    }
}
