<?php

namespace Pantheon\Terminus\Commands\HTTPS;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Exceptions\TerminusNotFoundException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Util\GetACMEStatus;

/**
 * Class ChallengeCommand
 * @package Pantheon\Terminus\Commands\HTTPS
 */
class ChallengeCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * Writes a challenge file to the current directory and prints instructions
     * on how to serve it.
     *
     * @authorize
     *
     * @command alpha:https:challenge:file
     * @aliases acme-file
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     * @param string $domain The domain to produce a challenge for.
     *
     * @usage <site>.<env> <domain> Creates an ACME http-01 challenge file you can serve during migration to Pantheon
     */
    public function writeChallengeFile($site_env, $domain)
    {
        list($data, $acmeStatus) = $this->getACMEStatus($site_env, $domain);
        if (!$acmeStatus) {
            return;
        }

        // Sanity check: this should never happen, as getACMEStatus should throw
        // in any instance where there is no verification file data.
        if (empty($data->{'http-01'})) {
            throw new TerminusException('No challenge file information available for domain {domain}.', compact('status', 'domain'));
        }
        $data = $data->{'http-01'};
        $filename = $data->token;
        $contents = $data->verification_value;

        if (file_put_contents($filename, $contents)) {
          $this->log()->notice('Wrote ACME challenge to file {filename}', compact('filename'));
          $this->log()->notice('Please copy this file to your web server so that it will be served from the URL');
          $this->log()->notice('http://{domain}{path}', ['domain' => $domain, 'path' => $data->verification_key]);
          $this->log()->notice('After this is complete, run {command}', ['command' => "terminus acme-file-verify $site_env $domain"]);
        } else {
          throw new TerminusException('Failed writing to {filename}', compact('filename'));
        }
    }

    /**
     * Get a DNS-txt record challenge.
     *
     * @authorize
     *
     * @command alpha:https:challenge:dns-txt
     * @aliases acme-txt
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     * @param string $domain The domain to produce a challenge for.
     * @default-string-field challenge
     * @field-labels
     *     domain: Domain
     *     record-name: Name
     *     ttl: TTL
     *     class: Class
     *     record-type: Record Type
     *     text-data: Text Data
     * @return RowsOfFields
     *
     * @usage <site>.<env> Displays domains associated with <site>'s <env> environment.
     */
    public function getChallengeDNStxt($site_env, $domain, $options = ['format' => 'list'])
    {
      list($data, $acmeStatus) = $this->getACMEStatus($site_env, $domain);
      if (!$acmeStatus) {
        return;
      }

      // Sanity check: this should never happen, as getACMEStatus should throw
      // in any instance where there is no verification dns txt record.
      if (empty($data->{'dns-01'})) {
        throw new TerminusException('No DNS txt record challenge information available for domain {domain}.', compact('status', 'domain'));
      }
      $data = $data->{'dns-01'};
      $struct = ChallengeCommand::formatChallengeDNStxt($domain, $data);
      $txt_record = $struct[0];
      $txt_record_components = $struct[1];
      // Provide instructions in a log message when the format is 'list'
        // n.b. 'list' format prints out each line's key, which for this
        // command is the full dns txt record.
        if ($options['format'] == 'list') {
          $this->log()->notice("Create a DNS txt record containing:\n$txt_record\n");
          $this->log()->notice('After this is complete, run {command}', ['command' => "terminus acme-txt-verify $site_env $domain"]);
        } else {
          return new RowsOfFields([$txt_record => $txt_record_components]);
        }
    }

    public static function formatChallengeDNStxt($domain, $data) {
        $txt_record_components = [
            'domain' => $domain,
            'record-name' => $data->verification_key,
            'ttl' => '300',
            'class' => 'IN',
            'record-type' => 'TXT',
            'text-data' => $data->verification_value,
        ];

        $dns_txt_record_tmpl = 'record-name ttl class record-type "text-data"';
        $txt_record = str_replace(array_keys($txt_record_components), array_values($txt_record_components), $dns_txt_record_tmpl);

        return [$txt_record, $txt_record_components];
    }

    /**
     * Look up the HTTPS ACME verification status for a site & environment
     * that need verification.
     */
    protected function getACMEStatus($site_env, $domain)
    {
        list(, $env) = $this->getSiteEnv($site_env);

        $domains = $env->getDomains();
        //$data = $domains->getACMEStatus($domainToVerify->id);
        try {
          $data = GetACMEStatus::get($domains, $domain);
        } catch (TerminusNotFoundException $e) {
          $command = "terminus domain:add $site_env $domain";
          $this->log()->notice('The domain {domain} has not been added to this site and environment. Use the command {command} to add it.', compact('domain', 'command'));
          throw new TerminusException('Cannot create challenge for missing domain.');
        }

        $ownership = $data->ownership_status;
        $status = $ownership->status;

        if ($status == 'completed') {
            $this->log()->notice('Domain verification for {domain} has been completed.', compact('domain'));
            return [null, false];
        }

        if ($status == 'not_required') {
            $this->log()->notice('Domain verification for {domain} is not necessary; https has not been configured for this domain in its current location.', compact('domain'));
            return [null, false];
        }

        if ($status == 'unavailable' && !empty($ownership->message)) {
          throw new TerminusException($ownership->message);
        }

        if ($status != 'required') {
            throw new TerminusException('Unimplemented status {status} for domain {domain}.', compact('status', 'domain'));
        }

        if (empty($data->acme_preauthorization_challenges)) {
          throw new TerminusException('No challenge information currently available for domain {domain}.', compact('domain'));
        }
        return [$data->acme_preauthorization_challenges, true];
    }
}
