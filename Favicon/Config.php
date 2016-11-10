<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

/**
 * Class Config
 * @package Statamic\Addons\Favicon
 */
class Config
{
    const API_URL = 'http://realfavicongenerator.net/api/favicon_generator';
    const ASSET_TARGET_FOLDER = 'favicon'; // Configurable
    const PARTIAL_NAME = "partials/favicon.html"; // configurable
}