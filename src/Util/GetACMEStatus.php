<?php
namespace Pantheon\Terminus\Util;

use GuzzleHttp\Exception\ClientException;
use Pantheon\Terminus\Collections\Domains;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;

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
  /**
   * @param Domains $domains
   * @param string $domain
   * @throws TerminusNotFoundException
   */
    public static function get($domains, $domain)
    {
        $url = $domains->getUrl() . '/' . rawurlencode($domain);
        try {
          $data = $domains->request()->request($url, ['method' => 'get', 'query' => ['acme_version' => 2]]);
        } catch (ClientException $e) {
          // Detect if this is just because the input domain is not on the site-env
          if ($e->getCode() == 404) {
            throw new TerminusNotFoundException(
              "The domain {domain} has not been added to the site and environment.",
              ['domain' => $domain],
              $e->getCode()
            );
          }
          throw $e;
        }

        return $data['data'];
    }

  /**
   * Sends a request to trigger backend async verification of the challenge.
   *
   * @param Domains $domains
   * @param string $domain
   * @param string $challengeType
   * @throws TerminusNotFoundException
   */
    public static function startVerification($domains, $domain, $challengeType) {
      $url = $domains->getUrl() . '/' . rawurlencode($domain) . '/' . 'verify-ownership';
      $body = [
        'challenge_type' => $challengeType,
        'client' => 'terminus-plugin', // Only in case we want statistics
      ];
      try {
        $domains->request()->request($url, ['method' => 'POST', 'form_params' => $body]);
      } catch (ClientException $e) {
        // Detect if this is just because the input domain is not on the site-env
        if ($e->getCode() == 404) {
          throw new TerminusNotFoundException(
            "The domain {domain} has not been added to the site and environment.",
            ['domain' => $domain],
            $e->getCode()
          );
        }
        throw $e;
      }
    }
}
