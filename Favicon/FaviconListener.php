<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

use Statamic\API\Nav;
use Statamic\CP\Navigation\Nav as CPNav;
use Statamic\Extend\Listener;

/**
 * Class FaviconListener
 * @package Statamic\Addons\Favicon
 */
class FaviconListener extends Listener
{
    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        'cp.nav.created' => 'addNavItems',
    ];

    /**
     * @param CPNav $nav
     */
    public function addNavItems(CPNav $nav)
    {
        if ($nav->has('tools')) {
            $main = Nav::item($this->trans('default.favicon'))->route('favicon')->icon('rainbow');
            $nav->addTo('tools', $main);
        } else {
            /**
             * Check the statamic issues for further details why this can happen
             *
             * https://github.com/statamic/v2-hub/issues/1540
             * https://github.com/subpixel-ch/statamic-favicon/issues/1
             */
            \Log::error("Could not add Favicon to Navigation because user has no access to the tools menu");
        }
    }
}
