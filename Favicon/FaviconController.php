<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

use Illuminate\Http\Request;
use Statamic\API\Asset as AssetAPI;
use Statamic\Contracts\Assets\Asset;
use Statamic\Extend\Controller;

/**
 * Class FaviconController
 * @package Statamic\Addons\Favicon
 */
class FaviconController extends Controller
{
    /**
     * @param FaviconAPI $api
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function index(FaviconAPI $api)
    {
        $this->authorize('cp:access');

        // check if configured
        if (!$api->isConfigured()) {
            return redirect(route('addon.settings', mb_strtolower($this->getAddonName())))
                ->withErrors($this->trans('default.error_config'));
        }

        $faviconData = $this->storage->getJSON('current');

        $data = [
            'title' => $this->trans('default.favicon'),
            'assetContainer' => $api->assetContainerSlug,
            'trans' => $api->_trans,
            'hasFavicon' => !empty($faviconData),
        ];

        if ($data['hasFavicon']) {
            /** @var \Statamic\Assets\Asset $asset */
            $asset = $api->assetContainer->assets()->filter(function (Asset $file) {
                return $file->basename() === 'preview.png';
            })->first();

            $data['preview'] = $asset->absoluteUrl();
            $data['htmlCode'] = $faviconData['favicon']['html_code'];
            $data['partialTag'] = '{<!-- x -->{ partial:' . $api->partial . ' }<!-- x -->}'; // The comment is needed to prevent parsing
        }

        return $this->view('index', $data);
    }

    /**
     * @param FaviconAPI $api
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View|FaviconController
     */
    public function generate(FaviconAPI $api, Request $request)
    {
        $this->authorize('cp:access');

        $assetId = $request->input('icon');
        $asset = AssetAPI::find($assetId);

        $endpoint = $api->invokeService($asset);

        if (is_string($endpoint)) {
            return redirect($endpoint);
        } elseif (is_array($endpoint)) {
            return $this->view('redirect', [
                'title' => $this->trans('default.redirect'),
                'trans' => $api->_trans,
                'target' => $endpoint,
            ]);
        } else {
            return redirect()->back()->withErrors($this->trans('default.api_error'));
        }
    }

    /**
     * Callback invoked from the favicon generator service
     *
     * @param FaviconAPI $api
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|FaviconController
     */
    public function callback(FaviconAPI $api, Request $request)
    {
        $this->authorize('cp:access');

        $data = json_decode($request->input('json_result'))->favicon_generation_result;

        if ($data->result->status == 'error') {
            return redirect(route('favicon'))->withErrors($data->result->error_message);
        }

        $api->processResponse($data);

        return redirect(route('favicon'))->with('success', $this->trans('default.favicon_updated'));
    }

    /**
     * @param FaviconAPI $api
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function remove(FaviconAPI $api)
    {
        $this->authorize('cp:access');

        $api->removeFavicon();

        return redirect(route('favicon'));
    }
}
