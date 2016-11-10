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

        // check if configured
        if (!$this->api->isConfigured()) {
            return redirect(route('addon.settings', mb_strtolower($this->getAddonName())))
                ->withErrors($this->trans('default.error_config'));
        }

        $faviconData = $this->storage->getJSON('current');

        $data = [
            'title' => $this->trans('default.favicon'),
            'assetContainer' => $this->api->assetContainerSlug,
            'trans' => $this->api->_trans,
            'hasFavicon' => !empty($faviconData),
        ];

        if ($data['hasFavicon']) {
            $data['preview'] = '/assets/favicon/preview.png'; // FIXME path
            $data['htmlCode'] = $faviconData['favicon']['html_code'];
            $data['partialTag'] = '{<!-- x -->{ partial:favicon }<!-- x -->}'; // The comment is needed to prevent parsing
        }

        return $this->view('index', $data);
    }

    public function generate()
    {
        $this->checkAuth();

        $assetId = $this->request->input('icon');
        $asset = Asset::find($assetId);

        $endpoint = $this->api->invokeService($asset);

        if (is_string($endpoint)) {
            return redirect($endpoint);
        } elseif (is_array($endpoint)) {
            return $this->view('redirect', [
                'title' => $this->trans('default.redirect'),
                'trans' => $this->api->_trans,
                'target' => $endpoint,
            ]);
        } else {
            return redirect()->back()->withErrors($this->trans('default.api_error'));
        }
    }

    /**
     * Callback invoked from the favicon generator service
     */
    public function callback()
    {
        $this->checkAuth();

        $data = json_decode($this->request->input('json_result'))->favicon_generation_result;

        if ($data->result->status == 'error') {
            return redirect(route('favicon'))->withErrors($data->result->error_message);
        }

        $this->api->processResponse($data);

        return redirect(route('favicon'))->with('success', $this->trans('default.favicon_updated'));
    }

    public function remove()
    {
        $this->checkAuth();

        $this->api->removeFavicon();

        return redirect(route('favicon'));
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
