<?php

namespace App\Services;

/**
 * Multi-signal classifier that decides whether a synced email is automated
 * junk (newsletters, DMARC reports, OTPs, bulk mailers, etc.) rather than
 * a genuine person-to-person message.
 *
 * Scoring is additive: each signal contributes points.  A total >= THRESHOLD
 * means the message is classified as junk.
 */
class EmailSpamClassifier
{
    private const THRESHOLD = 3;

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $message  Raw message array from ImapMailboxClient
     */
    public function isJunk(array $message): bool
    {
        return $this->score($message) >= self::THRESHOLD;
    }

    // -----------------------------------------------------------------------
    // Scoring
    // -----------------------------------------------------------------------

    /** @param  array<string, mixed>  $message */
    private function score(array $message): int
    {
        $score = 0;

        $score += $this->scoreHeaders($message['raw_headers'] ?? '');
        $score += $this->scoreFromAddress((string) ($message['from_email'] ?? ''));
        $score += $this->scoreFromName((string) ($message['from_name'] ?? ''));
        $score += $this->scoreSubject((string) ($message['subject'] ?? ''));
        $score += $this->scoreBody(
            (string) ($message['body_text'] ?? ''),
            (string) ($message['body_html'] ?? ''),
        );

        return $score;
    }

    // -----------------------------------------------------------------------
    // Signal: raw headers
    // -----------------------------------------------------------------------

    private function scoreHeaders(string $raw): int
    {
        if ($raw === '') {
            return 0;
        }

        $score = 0;
        $lower = strtolower($raw);

        // Mailing-list / bulk headers are definitive
        if (str_contains($lower, 'list-unsubscribe:')) {
            $score += 4; // alone is enough to classify
        }
        if (str_contains($lower, 'list-id:')) {
            $score += 3;
        }

        // Precedence header
        if (preg_match('/^precedence:\s*(bulk|list|junk)/m', $lower)) {
            $score += 3;
        }

        // Bulk/marketing X-headers
        foreach ([
            'x-campaign-id:', 'x-mailchimp-', 'x-mailer: mailchimp',
            'x-mailer: constant contact', 'x-mailer: sendgrid',
            'x-mailer: mailgun', 'x-mailer: brevo', 'x-mailer: sendinblue',
            'x-mailer: hubspot', 'x-mailer: salesforce',
            'x-bulk-mail:', 'x-newsletter:', 'x-feedback-id:',
            'x-sg-eid:', // SendGrid
            'x-mandrill-', // Mailchimp Transactional
        ] as $header) {
            if (str_contains($lower, $header)) {
                $score += 3;
                break;
            }
        }

        // Automated notification mailers
        if (preg_match('/x-mailer:\s*(auto|robot|notif|daemon|system|cron|scheduled|report)/i', $raw)) {
            $score += 2;
        }

        // DMARC / DKIM / SPF aggregate report headers
        if (preg_match('/content-type:[^\n]*application\/(zip|gzip|xml)/i', $raw)) {
            $score += 3; // DMARC XML attachment
        }
        if (str_contains($lower, 'report-type=disposition-notification')) {
            $score += 2;
        }

        // Auto-submitted header (RFC 3834)
        if (preg_match('/^auto-submitted:\s*(?!no)/m', $lower)) {
            $score += 4;
        }

        return $score;
    }

    // -----------------------------------------------------------------------
    // Signal: from address
    // -----------------------------------------------------------------------

    private function scoreFromAddress(string $email): int
    {
        if ($email === '') {
            return 0;
        }

        $lower = strtolower($email);
        [$local, $domain] = array_pad(explode('@', $lower, 2), 2, '');

        // Definitive no-reply local-parts
        $definitive = [
            'noreply', 'no-reply', 'donotreply', 'do-not-reply',
            'mailer-daemon', 'postmaster', 'bounce', 'bounces',
            'daemon', 'auto-confirm', 'auto-notify', 'automated',
        ];
        foreach ($definitive as $pattern) {
            if ($local === $pattern || str_starts_with($local, $pattern.'+') || str_starts_with($local, $pattern.'.')) {
                return 5;
            }
        }

        // Strong automated-sender local-part prefixes
        $strongPrefixes = [
            'notification', 'notifications', 'newsletter', 'alert', 'alerts',
            'update', 'updates', 'info+', 'no.reply', 'noreply.',
            'system', 'robot', 'automail', 'autorespond',
        ];
        foreach ($strongPrefixes as $prefix) {
            if (str_starts_with($local, $prefix)) {
                return 3;
            }
        }

        // Known automated sender domains
        $automatedDomains = [
            'tm.openai.com',           // OpenAI OTP / transactional
            'ads-noreply.google.com',  // Google Ads
            'workspace.google.com',    // Google Workspace team
            'dmarcreport.microsoft.com',
            'mimecastreport.com',
            'za-1.mimecastreport.com',
            'google.com' => ['workspace', 'accounts', 'mail-noreply'],
        ];

        foreach ($automatedDomains as $key => $value) {
            if (is_array($value)) {
                // domain => [local-part substrings]
                if ($domain === $key) {
                    foreach ($value as $localFragment) {
                        if (str_contains($local, $localFragment)) {
                            return 4;
                        }
                    }
                }
            } else {
                // plain domain string
                if ($domain === $value || str_ends_with($domain, '.'.$value)) {
                    return 4;
                }
            }
        }

        return 0;
    }

    // -----------------------------------------------------------------------
    // Signal: display name
    // -----------------------------------------------------------------------

    private function scoreFromName(string $name): int
    {
        $lower = strtolower($name);

        $patterns = [
            'no.reply', 'noreply', 'do not reply', 'automated', 'notification',
            'newsletter', 'mailer daemon', 'mail daemon', 'postmaster',
            'dmarc', 'abuse report', 'system alert', 'auto-generated',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return 2;
            }
        }

        return 0;
    }

    // -----------------------------------------------------------------------
    // Signal: subject line
    // -----------------------------------------------------------------------

    private function scoreSubject(string $subject): int
    {
        $lower = strtolower($subject);

        // DMARC / mail system reports
        if (preg_match('/dmarc|aggregate report|report domain|ruf report|dkim|spf report/i', $subject)) {
            return 5;
        }

        // OTP / verification codes
        if (preg_match('/\b(otp|one.time.password|verification code|login code|confirm.+code|security code|temporary.+code|passcode)\b/i', $subject)) {
            return 4;
        }

        // Newsletter / marketing
        if (preg_match('/\b(newsletter|unsubscribe|special offer|limited time|% off|discount|deal of the|flash sale|promotion)\b/i', $subject)) {
            return 4;
        }

        // Automated billing / invoice / receipt
        if (preg_match('/\b(your (invoice|receipt|order|subscription|payment|statement)|invoice #|order confirmation|payment (received|confirmed|due))\b/i', $subject)) {
            return 2; // legitimate sometimes, just lightly penalise
        }

        // Google / SaaS automated subjects
        if (preg_match('/\[action required\]|\bset up billing\b|your (account|workspace|plan) (is|has|will)/i', $subject)) {
            return 2;
        }

        return 0;
    }

    // -----------------------------------------------------------------------
    // Signal: body content
    // -----------------------------------------------------------------------

    private function scoreBody(string $text, string $html): int
    {
        $score = 0;
        $combined = strtolower($text.' '.$html);

        // Unsubscribe link / text is the clearest bulk-mail signal
        if (preg_match('/\bunsubscribe\b/i', $combined)) {
            $score += 3;
        }

        // Automated report body patterns
        if (preg_match('/<\?xml|<feedback>|<report_metadata>|<dmarc/i', $html)) {
            $score += 5; // raw DMARC XML in body
        }

        // Typical bulk HTML: pixel trackers, view-in-browser links
        if (preg_match('/view (this email|in browser|online)|view-online-link|email-tracker|tracking\.pixel/i', $combined)) {
            $score += 2;
        }

        // Marketing footer keywords
        if (preg_match('/you (are|were) subscribed|you.re receiving this (email|because)|manage (your )?(preferences|subscriptions)/i', $combined)) {
            $score += 2;
        }

        return $score;
    }
}
