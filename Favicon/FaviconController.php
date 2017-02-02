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
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('cp:access');

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
            /** @var \Statamic\Assets\Asset $asset */
            $asset = $this->api->assetContainer->assets()->filter(function ($file) {
                //dd($file->basename());
                return $file->basename() === 'preview.png';
            })->first();

            $data['preview'] = $asset->absoluteUrl();
            $data['htmlCode'] = $faviconData['favicon']['html_code'];
            $data['partialTag'] = '{<!-- x -->{ partial:' . $this->api->partial . ' }<!-- x -->}'; // The comment is needed to prevent parsing
        }

        return $this->view('index', $data);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View|FaviconController
     */
    public function generate()
    {
        $this->authorize('cp:access');

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
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|FaviconController
     */
    public function callback()
    {
        $this->authorize('cp:access');

        $data = json_decode($this->request->input('json_result'))->favicon_generation_result;

        if ($data->result->status == 'error') {
            return redirect(route('favicon'))->withErrors($data->result->error_message);
        }

        $this->api->processResponse($data);

        return redirect(route('favicon'))->with('success', $this->trans('default.favicon_updated'));
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function remove()
    {
        $this->authorize('cp:access');

        $this->api->removeFavicon();

        return redirect(route('favicon'));
    }

    /**
     * Very basic tests
     */
    public function tests()
    {
        $this->authorize('cp:access');

        header('Content-type: text/plain; charset=utf-8');

        $urls = [
            'http://statamic.dev/file.png' => true,
            'http://statamic.dev:3000/file.png' => true,
            'http://localhost/file.png' => true,
            'http://site.localhost/file.png' => true,
            'http://statamic.local/file.png' => true,
            'http://statamic.com/file.png' => false,
            'http://statamic.com:3000/file.png' => false,
            'http://statamic.pizza/file.png' => false,
            'http://127.0.0.1/file.png' => true,
            'http://127.0.0.1:3100/file.png' => true,
            'http://192.168.0.1:3100/file.png' => true,
            'http://10.70.0.1/file.png' => true,
        ];

        $errors = 0;
        foreach ($urls as $url => $expected) {
            $result = $this->api->isLocalUrl($url);
            if ($result !== $expected) {
                printf("[ERROR] %s (%d should be %d)\n", $url, $expected, $result);
                ++$errors;
            }
        }

        if ($errors === 0) {
            echo "[OK] everything is fine";
        }

        die();
    }
}
