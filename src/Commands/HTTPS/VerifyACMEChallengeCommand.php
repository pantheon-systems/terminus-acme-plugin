<?php

namespace Pantheon\Terminus\Commands\HTTPS;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Util\GetACMEStatus;

/**
 * Class VerifyACMEChallengeCommand
 * @package Pantheon\Terminus\Commands\HTTPS
 */
class VerifyACMEChallengeCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * Attempts to verify the ACME challenge for a domain by checking the
     * https validation file or confirming the DNS txt record containing
     * the ACME challenge.
     *
     * @authorize
     *
     * @command alpha:https:verify
     * @aliases acme
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     * @param string $domain Optional.
     * @return array
     * @format yaml
     *
     * @usage <site>.<env> <domain> Verifies the ACME challenge for <domain> in <site>'s <env> environment.
     */
    public function verifyACMEChallenge($site_env, $domain = '')
    {
        list(, $env) = $this->getSiteEnv($site_env);

        $domains = $env->getDomains()->fetchWithRecommendations();
        $domainsToVerify = $this->determineValidationDomains($domains, $domain);

        if (empty($domainsToVerify)) {
            throw new TerminusException(
                'There are no domains that require verification.'
            );
        }

        foreach ($domainsToVerify as $domainToVerify) {
            // $data = $domains->getACMEStatus($domainToVerify->id);
            $data = GetACMEStatus::get($domains, $domainToVerify->id);

            $msg = $this->domainHttpsStatusMessage($data->ownership_status->status);

            $this->log()->notice($msg, ['domain' => $domainToVerify->id, 'status' => $data->ownership_status->status]);
        }
    }

    protected function domainHttpsStatusMessage($status)
    {
        if ($status == 'required') {
            return 'The domain {domain} has not completed its pre-authentication checks yet. Please confirm that a dns-txt record (dns-01) or an http verification fie (http-01) has been correctly set up. Running verification status checks on this domain again. Run "terminus https:info" in five or ten minutes to determine result.';
        }
        if ($status == 'completed') {
            return 'Verification checks for {domain} have been completed. Run "terminus domain:dns" to see the recommended DNS changes that need to be made for this domain.';
        }
        return 'Unknown https verification status "{status}" for {domain}.';
    }

    protected function determineValidationDomains($domains, $domain)
    {
        if (!empty($domain)) {
            if (!$domains->has($domain)) {
                throw new TerminusException('The domain {domain} has not been added to this site and environment.', compact('domain'));
            }
            $domainToVerify = $domains->get($domain);
            if ($domainToVerify->get('status') != 'action_required') {
                throw new TerminusException('The domain {domain} does not require verification.', compact('domain'));
            }
            return [$domainToVerify];
        }

        $result = [];
        foreach ($domains->all() as $domain) {
            if ($domain->get('status') == 'action_required') {
                $result[] = $domain;
            }
        }
        return $result;
    }
}
