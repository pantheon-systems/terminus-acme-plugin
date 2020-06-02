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

  /**
   * Triggers acceptance of the ACME dns-01 challenge returned by alpha:https:challenge:dns
   * and prints whether ownership of the domain was successfully proven.
   *
   * @authorize
   *
   * @command alpha:https:challenge:dns-txt:verify
   * @aliases acme-txt-verify
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   * @param string $domain The domain name to verify.
   * @usage <site>.<env> <domain> Verifies your ownership of <domain> by querying for a DNS TXT record.
   */
  public function verifyDnsChallenge($site_env, $domain)
  {
    $this->verifyChallenge($site_env, $domain, 'dns-01');
  }

  protected function verifyChallenge($site_env, $domain, $challenge_type) {
    list(, $env) = $this->getSiteEnv($site_env);

    $domains = $env->getDomains();

    // Check if it's already been verified, and note current challenges.
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

    $current_challenge = '';
    if (!empty($data->acme_preauthorization_challenges)) {
      $current_challenge = $data->acme_preauthorization_challenges->$challenge_type->verification_value;
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
          $this->handleVerificationFailed($site_env, $domain, $current_challenge, $data, $challenge_type);
          return;
        case 'success':
          $this->log()->notice('Ownership verification is complete!');
          $this->log()->notice('Your HTTPS certificate will be deployed to Pantheon\'s Global CDN shortly.');
          return;
      }
    }

    $this->handleVerificationFailed($site_env, $domain, $current_challenge, $data, $challenge_type);
  }

  protected function handleVerificationFailed($site_env, $domain, $current_challenge, $data, $challenge_type) {
    // Display rich error information if we have any.
    $preprovision_result = $data->{'ownership_status'};
    $preprovision_result = $preprovision_result->{'preprovision_result'};
    $pantheon_docs = 'https://pantheon.io/docs/guides/launch/domains';
    $support_ref = '';
    if (!empty($preprovision_result->last_preprovision_problem)) {
      $problem = $preprovision_result->last_preprovision_problem;
      if (!empty($problem->PantheonDocsLink)) {
        $pantheon_docs = $problem->PantheonDocsLink;
      }
      if (!empty($problem->SupportReference)) {
        $support_ref = " with reference \"" . $problem->SupportReference . '"';
      }
      if (!empty($problem->PantheonTitle)) {
        $this->log()->notice($problem->PantheonTitle);
      }
      if (!empty($problem->PantheonDetail)) {
        $this->log()->notice($problem->PantheonDetail);
      }
      if (!empty($problem->PantheonActionItem)) {
        $this->log()->notice($problem->PantheonActionItem);
      }

      if (!empty($problem->Detail) || !empty($problem->ProblemType)) {
        $this->log()->notice('');
        $detail = '';
        if (!empty($problem->ProblemType)) {
          $detail = $detail . "\n" . $problem->ProblemType;
        }
        if (!empty($problem->Detail)) {
          $detail = $detail . "\n" . $problem->Detail;
        }
        $this->log()->notice("Raw verification result:$detail");
      }
    } else {
      $this->log()->notice('Double-check that your challenge is being served correctly.');
    }

    $this->log()->notice('See {link} for assistance', ['link' => $pantheon_docs]);
    $this->log()->notice("or contact Pantheon Support$support_ref.");

    // Warn if ownership verification has become unavailable.
    // (Typically user has attempted more times than LE allows per hour)
    if ($data->ownership_status->status == 'unavailable' && !empty($data->ownership_status->message)) {
      $this->log()->warning($data->ownership_status->message);
    }

    // Warn if the challenge had to be changed.
    if (!empty($data->acme_preauthorization_challenges)) {
      $new_challenge = $data->acme_preauthorization_challenges->$challenge_type->verification_value;
      if ($new_challenge != $current_challenge) {
        $this->log()->warning('The old challenge cannot be tried again.');
        if ($challenge_type == 'dns-01') {
          $struct = ChallengeCommand::formatChallengeDNStxt($domain, $data->acme_preauthorization_challenges->{'dns-01'});
          $txt_record = $struct[0];
          $this->log()->warning("Please update your DNS to serve the new challenge below:\n$txt_record");
        }
        if ($challenge_type == 'http-01') {
          $this->log()->warning('Please run {command} again to obtain a new challenge file.',
            ['command' => "terminus alpha:https:challenge:file $site_env $domain"]
          );
        }
      }
    }

    throw new TerminusException('Ownership verification was not successful.');
  }
}