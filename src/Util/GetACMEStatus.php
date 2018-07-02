<?php
namespace Pantheon\Terminus\Util;

/**
 * Class GetACMEStatus
 *
 * Stand-in for method:
 *
 *    public function getACMEStatus($domain)
 *    {
 *        $url = $this->getUrl() . '/' . rawurlencode($domain);
 *        $data = $this->request->request($url, ['method' => 'get',]);
 *        return $data['data'];
 *    }
 *
 * @package Pantheon\Terminus\Collections
 */
class GetACMEStatus
{
    public static function get($domains, $domain)
    {
        $url = $domains->getUrl() . '/' . rawurlencode($domain);
        $data = $domains->request()->request($url, ['method' => 'get',]);
        return $data['data'];
    }
}
