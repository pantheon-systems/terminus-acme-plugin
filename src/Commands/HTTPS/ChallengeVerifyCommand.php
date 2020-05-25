<?php

namespace Pantheon\Terminus\Commands\HTTPS;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Util\GetACMEStatus;

/**
 * Class ChallengeVerifyCommand
 * @package Pantheon\Terminus\Commands\HTTPS
 */
class ChallengeVerifyCommand extends TerminusCommand implements SiteAwareInterface {
  use SiteAwareTrait;

  /**
   * Triggers acceptance of the ACME http-01 challenge returned by alpha:https:challenge:file
   * and prints whether ownership of the domain was successfully proven.
   *
   * @authorize
   *
   * @command alpha:https:challenge:file:verify
   * @aliases acme-file-verify
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   * @param string $domain The domain name to verify.
   * @usage <site>.<env> <domain> Verifies your ownership of <domain> by attempting to fetch a file over http.
   */
  public function verifyHttpChallenge($site_env, $domain)
  {
    $this->verifyChallenge($site_env, $domain, 'http-01');
  }

  protected function verifyChallenge($site_env, $domain, $challenge_type) {
    list(, $env) = $this->getSiteEnv($site_env);

    $domains = $env->getDomains();

    // Check if it's already been verified.
    $data = GetACMEStatus::get($domains, $domain);
    // This happens if the domain status object could not be built by the deadline.
    // In this case, launch ownership verification, but we may not be able to poll.
    if (empty($data->{'ownership_status'})) {
      $status = "failed";
    } else {
      $preprovision_result = $data->{'ownership_status'};
      $preprovision_result = $preprovision_result->{'preprovision_result'};
      $status = $preprovision_result->status;
    }

    switch ($status) {
      case "success":
        $this->log()->notice("Ownership verification for {domain} is complete!", ['domain' => $domain]);
        return;
      case "failed":
        try {
          GetACMEStatus::startVerification($domains, $domain, $challenge_type);
        } catch (TerminusNotFoundException $e) {
          $command = "terminus domain:add $site_env $domain";
          $this->log()->notice('The domain {domain} has not been added to this site and environment. Use the command {command} to add it.', compact('domain', 'command'));
          throw new TerminusException('Cannot verify challenge for missing domain.');
        }

        $this->log()->notice('The challenge for {domain} is being verified...', compact('domain'));
        break;
      case "in_progress":
        // The third possibility, we'll just start polling in this case.
    }

    $pollFailures = 0;
    for ($polls = 0; $polls < 15; $polls++) {
      sleep(10);
      try {
        $data = GetACMEStatus::get($domains, $domain);
      } catch (\Exception $e) {
        $pollFailures++;
        if ($pollFailures > 3) {
          throw $e;
        }
        continue;
      }

      if (empty($data->{'ownership_status'})) {
        $pollFailures++;
        if ($pollFailures > 10) {
          throw new TerminusException("Due to an error, we are temporarily unable to verify domain ownership.");
        }
        continue;
      }

      $preprovision_result = $data->{'ownership_status'};
      $preprovision_result = $preprovision_result->{'preprovision_result'};
      $status = $preprovision_result->status;
      switch ($status) {
        case 'failed':
          $this->handleVerificationFailed();
        case 'success':
          $this->log()->notice('Ownership verification is complete!');
          $this->log()->notice('Your HTTPS certificate will be deployed to Pantheon\'s Global CDN shortly.');
          return;
      }
    }

    $this->handleVerificationFailed();
  }

  protected function handleVerificationFailed() {
    $this->log()->notice('Double-check that your challenge is being served correctly.');
    $this->log()->notice('See {link} for assistance', ['link' => 'https://pantheon.io/docs/guides/launch/domains']);
    $this->log()->notice('or contact Pantheon Support. You may try again up to 5 times per hour.');
    throw new TerminusException('Ownership verification was not successful.');
  }
}