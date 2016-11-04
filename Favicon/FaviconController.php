<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

use Statamic\API\Asset;
use Statamic\Extend\Controller;

/**
 * Class FaviconController
 * @package Statamic\Addons\Favicon
 */
class FaviconController extends Controller
{
    /** @var FaviconAPI */
    protected $api;

    protected function init()
    {
        $this->api = $this->api();
    }

    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->checkAuth();

        $data = [
            'title' => $this->trans('default.favicon'),
            'assetContainer' => $this->api->assetContainerSlug,
            'trans' => $this->api->_trans,
        ];
        return $this->view('index', $data);
    }

    public function generate()
    {
        $this->checkAuth();

        $assetId = $this->request->input('icon');
        $asset = Asset::find($assetId);

        $response = $this->api->invokeService($asset);
        dd($response);
    }

    /**
     * Callback invoked from the favicon generator service
     */
    public function getCallback()
    {
        $key = $this->request->input('key');
        if (!$this->api->validateSecretKey($key)) {
            $this->pageNotFound();
        }

        dd($this->request->all());
    }

    /**
     * Show 404 if the user is not authenticated
     */
    private function checkAuth()
    {
        if (!\Auth::check()) {
            $this->pageNotFound();
        }
    }
}
