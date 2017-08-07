<?php
/**
 * @author Rémy M. Böhler
 */

namespace Statamic\Addons\Favicon;

use Statamic\Testing\TestCase;

/**
 * Class FaviconTest
 * @package Statamic\Addons\Favicon
 */
class FaviconTest extends TestCase
{
    /** @var FaviconAPI */
    private $api;

    public function setUp()
    {
        parent::setUp();
        $this->api = $this->app->make(FaviconAPI::class);
    }

    /**
     * @return array
     */
    public function urlProvider()
    {
        return [
            ['http://statamic.dev/file.png', true],
            ['http://statamic.dev:3000/file.png', true],
            ['http://localhost/file.png', true],
            ['http://site.localhost/file.png', true],
            ['http://statamic.local/file.png', true],
            ['http://statamic.com/file.png', false],
            ['http://statamic.com:3000/file.png', false],
            ['http://statamic.pizza/file.png', false],
            ['http://127.0.0.1/file.png', true],
            ['http://127.0.0.1:3100/file.png', true],
            ['http://192.168.0.1:3100/file.png', true],
            ['http://10.70.0.1/file.png', true],
        ];
    }

    /**
     * @dataProvider urlProvider
     * @param string $url
     * @param boolean $expected
     */
    public function testLocalUrls($url, $expected)
    {
        $this->assertEquals($expected, $this->api->isLocalUrl($url));
    }
}
