<?php

namespace App\Services;

use App\Models\MarketingLeadGenerationRun;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Enriches a single parsed company by:
 *   1. Generating candidate domain names from the company name.
 *   2. Parallel-fetching all candidates (curl_multi, short timeout).
 *   3. Scoring each by: phone match, company name in title/content, domain similarity.
 *   4. Crawling the winner's contact pages to extract emails.
 *
 * Does NOT depend on any search engine — avoids all Cloudflare/rate-limit blocks.
 * Designed for sequential, one-company-at-a-time use.
 */
class CompanyEnrichmentService
{
    private const FETCH_TIMEOUT     = 6;   // seconds per HTTP request
    private const MAX_CONTACT_PAGES = 2;   // homepage + 2 contact pages
    private const SCORE_THRESHOLD   = 25;  // minimum score to accept a website

    /** Domains that are never an official company website. */
    private const DIRECTORY_DOMAINS = [
        'facebook.com', 'linkedin.com', 'instagram.com', 'twitter.com', 'x.com',
        'google.com', 'youtube.com', 'tiktok.com',
        'yelp.com', 'yellowpages.com', 'tripadvisor.com', 'foursquare.com',
        'gumtree.co.za', 'justdial.com', 'wikipedia.org', 'wikidata.org',
        'bizportal.co.za', 'browseafrica.com', 'cylex.us', 'hotfrog.com', 'snupit.co.za',
        'brabys.co.za', 'attorneys.co.za', 'africabizinfo.com', 'cybo.com',
        'findlaw.com', 'lawdepot.com', 'manta.com', 'chamberofcommerce.com',
    ];

    /** Words stripped when building a domain slug from a company name. */
    private const STRIP_WORDS = [
        'attorneys', 'attorney', 'notaries', 'notary', 'conveyancers', 'conveyancer',
        'incorporated', 'inc', 'pty', 'ltd', 'limited', 'legal', 'law', 'lawyers',
        'associates', 'partner', 'partners', 'group', 'consultants',
        'consultancy', 'africa', 'south', 'johannesburg', 'sandton', 'pretoria',
        'cape', 'town', 'durban', 'midrand', 'randburg', 'roodepoort', 'centurion',
        'franchise', 'corporate', 'services', 'international', 'the',
    ];

    /** Contact-related URL path segments to probe even when not linked from homepage. */
    private const CONTACT_PATHS = [
        '/contact', '/contact-us', '/contact_us',
        '/about', '/about-us',
        '/team', '/our-team', '/people',
        '/professionals', '/attorneys',
        '/offices', '/locations',
        '/get-in-touch',
    ];

    /** Anchor-text / href keywords that indicate a contact-type link. */
    private const CONTACT_KEYWORDS = [
        'contact', 'get in touch', 'office', 'offices', 'team',
        'people', 'professionals', 'attorneys', 'locations', 'about',
    ];

    /** Placeholder email patterns to reject. */
    private const REJECT_EMAIL_PATTERNS = [
        'example@', 'test@', 'noreply@', 'no-reply@', 'donotreply@',
        'yourname@', 'name@', 'email@', 'user@', '@domain.com',
        'support@support', 'admin@admin',
    ];

    /** Priority prefixes for picking the primary email (in order). */
    private const PRIMARY_EMAIL_PREFIXES = [
        'info@', 'enquiries@', 'reception@', 'admin@', 'contact@',
        'hello@', 'mail@', 'office@',
        'legal@', 'litigation@', 'conveyancing@', 'commercial@',
        'estates@', 'accounts@',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array{company: string, phone: string, address: string, website: string|null}  $company
     * @return array{website: string|null, contact_page: string|null, primary_email: string|null,
     *               all_emails: string[], phone: string|null, image_url: string,
     *               status: string, notes: string|null, pages_crawled: string[]}
     */
    public function findWebsiteAndContacts(array $company, ?MarketingLeadGenerationRun $run = null): array
    {
        $name = (string) ($company['company'] ?? '');

        $this->log($run, 'Enrichment starting', ['company' => $name, 'phone' => $company['phone'] ?? '']);

        // Short-circuit: paste already contained a real URL
        $presetUrl = ($company['website'] ?? null);
        if ($presetUrl !== null && $this->isHttpUrl($presetUrl)) {
            $this->log($run, 'Enrichment: using pre-existing URL', ['company' => $name, 'url' => $presetUrl]);
            $crawl  = $this->crawlWebsite($presetUrl, $company, $run);
            $status = ($crawl['primary_email'] ?? null) !== null ? 'verified_email_found' : 'website_found_no_email';
            return array_merge($crawl, ['website' => $presetUrl, 'status' => $status]);
        }

        // 1 — Generate candidate URLs from company name
        $candidates = $this->generateCandidateUrls($company);
        $this->log($run, 'Enrichment candidates generated', ['company' => $name, 'count' => count($candidates), 'candidates' => $candidates]);

        // 2 — Parallel-fetch all candidates and score them
        $website = $this->pickBestWebsite($company, $candidates, $run);

        if ($website === null) {
            $this->log($run, 'Enrichment: no website found', ['company' => $name]);
            return [
                'website'       => null,
                'contact_page'  => null,
                'primary_email' => null,
                'all_emails'    => [],
                'phone'         => $company['phone'] ?? null,
                'image_url'     => '',
                'status'        => 'no_website_found',
                'notes'         => 'Could not identify an official website.',
                'pages_crawled' => [],
            ];
        }

        $this->log($run, 'Enrichment: website selected', ['company' => $name, 'website' => $website]);

        // 3 — Crawl contact pages for emails
        $crawl  = $this->crawlWebsite($website, $company, $run);
        $status = ($crawl['primary_email'] ?? null) !== null ? 'verified_email_found' : 'website_found_no_email';

        $this->log($run, 'Enrichment complete', [
            'company'       => $name,
            'website'       => $website,
            'primary_email' => $crawl['primary_email'] ?? null,
            'email_count'   => count($crawl['all_emails']),
            'status'        => $status,
        ]);

        return array_merge($crawl, ['website' => $website, 'status' => $status]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Domain / URL generation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate 8-15 candidate website URLs from a company name.
     * No HTTP is done here — pure string manipulation.
     *
     * @return array<int, string>
     */
    private function generateCandidateUrls(array $company): array
    {
        $name = (string) ($company['company'] ?? '');

        // ── Build slug variations ─────────────────────────────────────────────
        // Strip bracketed hints like "(Johannesburg)"
        $clean = (string) preg_replace('/\([^)]+\)/', '', $name);
        $clean = strtolower((string) preg_replace('/[^a-zA-Z0-9\s]/', ' ', $clean));
        $allWords = array_values(array_filter(preg_split('/\s+/', trim($clean)) ?? [], fn (string $w) => $w !== ''));
        $sigWords = array_values(array_filter($allWords, fn (string $w) => strlen($w) > 1 && ! in_array($w, self::STRIP_WORDS, true)));

        // Full slug: all significant words joined (e.g. "leoninaude")
        $fullSlug  = implode('', $sigWords);
        // Full slug including ALL words (preserves "inc", "law" etc. — often part of actual domain)
        // e.g. "Leoni Naude Inc" → "leoninaudeinc"
        $allSlug   = implode('', $allWords);
        // Two-word slug
        $twoSlug   = count($sigWords) >= 2 ? $sigWords[0].$sigWords[1] : $fullSlug;
        // Abbreviation: first letter of each significant word
        $abbrev    = implode('', array_map(fn (string $w) => $w[0], $sigWords));
        // First word
        $firstWord = $sigWords[0] ?? $fullSlug;
        // First two words + abbrev combined
        $firstAbbrev = count($sigWords) >= 2 ? $sigWords[0].($sigWords[1][0] ?? '') : $firstWord;

        $slugs = array_unique(array_filter([$allSlug, $fullSlug, $twoSlug, $firstAbbrev, $abbrev, $firstWord], fn (string $s) => strlen($s) >= 2));

        // ── Also generate "slug + africa/law/legal" variants for SA firms ──────────
        // e.g. "ENS" → try 'ensafrica', 'enslaw', 'enslegal'
        $extraSuffixes = ['africa', 'law', 'legal', 'sa', 'attorneys'];
        $extraSlugs = [];
        foreach ([$fullSlug, $abbrev] as $baseSlug) {
            if (strlen($baseSlug) < 2) continue;
            foreach ($extraSuffixes as $suffix) {
                $extraSlugs[] = $baseSlug.$suffix;
            }
        }
        $slugs = array_unique(array_merge(array_values($slugs), $extraSlugs));

        // ── Build URLs ────────────────────────────────────────────────────────
        $tlds = ['.co.za', '.com', '.africa'];
        $urls = [];

        foreach ($slugs as $slug) {
            foreach ($tlds as $tld) {
                $urls[] = 'https://www.'.$slug.$tld;
                $urls[] = 'https://'.$slug.$tld;
            }
        }

        return array_values(array_unique($urls));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parallel fetch + scoring
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parallel-fetch all candidate URLs and return the best matching one,
     * or null if nothing scores above the threshold.
     */
    private function pickBestWebsite(array $company, array $candidates, ?MarketingLeadGenerationRun $run): ?string
    {
        if (empty($candidates)) {
            return null;
        }

        $pages = $this->parallelFetch($candidates);

        $nameLower    = Str::lower((string) ($company['company'] ?? ''));
        $phoneDigits  = preg_replace('/\D/', '', (string) ($company['phone'] ?? '')) ?? '';

        $bestScore = self::SCORE_THRESHOLD - 1;
        $bestUrl   = null;

        foreach ($pages as $url => $page) {
            if ($page === null) {
                continue;
            }

            $host  = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
            $score = $this->scoreMatch($company, $url, $host, $page['html'], $page['title'], $nameLower, $phoneDigits);

            $this->log($run, 'Enrichment score', [
                'company' => $company['company'] ?? '',
                'url'     => $url,
                'score'   => $score,
                'title'   => $page['title'],
            ]);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestUrl   = $url;
            }
        }

        return $bestUrl;
    }

    /**
     * Fetch multiple URLs in parallel using curl_multi.
     * Returns map of url => ['html'=>string,'title'=>string]|null.
     *
     * @param  array<int, string>  $urls
     * @return array<string, array{html: string, title: string, url: string}|null>
     */
    private function parallelFetch(array $urls): array
    {
        $mh      = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::FETCH_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en-US,en;q=0.9'],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }

        $active = null;
        do {
            curl_multi_exec($mh, $active);
            if ($active > 0) {
                curl_multi_select($mh, 0.1);
            }
        } while ($active > 0);

        foreach ($handles as $url => $ch) {
            $code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body      = (string) (curl_multi_getcontent($ch) ?? '');
            $effective = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($code < 200 || $code >= 400 || $body === '') {
                $results[$url] = null;
                continue;
            }

            $results[$url] = [
                'html'  => Str::limit($body, 150_000),
                'title' => $this->extractPageTitle($body),
                'url'   => $effective,
            ];
        }

        curl_multi_close($mh);

        return $results;
    }

    /**
     * Score a fetched page for how well it matches the company.
     */
    private function scoreMatch(
        array  $company,
        string $url,
        string $host,
        string $html,
        string $title,
        string $nameLower,
        string $phoneDigits,
    ): int {
        $score = 0;
        $domainExactShort = false;

        // ── Domain vs company name ─
        $domainBody  = (string) preg_replace('/\.[a-z]{2,6}(\.[a-z]{2,6})?$/', '', $host);
        $cleanDomain = (string) preg_replace('/[^a-z0-9]/', '', $domainBody);
        $cleanName   = (string) preg_replace('/[^a-z0-9]/', '', $nameLower);

        if ($cleanDomain !== '' && $cleanName !== '') {
            $domainLen = strlen($cleanDomain);
            $nameLen   = strlen($cleanName);
            $ratio     = $domainLen / max($nameLen, 1);

            if ($cleanDomain === $cleanName) {
                // For very short domains (e.g. 'ens') require phone OR title match too;
                // otherwise we'd accept 'ens.com' (an unrelated Russian company) just
                // because the domain is an exact 3-letter match.
                if ($domainLen >= 6) {
                    $score += 35;
                }
                // For short domains the domain-match bonus is awarded only after
                // phone / title signals confirm it's the right site.
                // We record a pending bonus via a flag.
                $domainExactShort = $domainLen < 6;
            } elseif ($ratio >= 0.6 && (str_contains($cleanDomain, $cleanName) || str_contains($cleanName, $cleanDomain))) {
                $score += 22;
            } elseif (
                $domainLen > 4 && $nameLen > 4
                && similar_text($cleanDomain, $cleanName) / max($domainLen, $nameLen) > 0.6
            ) {
                $score += 12;
            }
        }

        // ── Page title ────────────────────────────────────────────────────────
        if ($title !== '' && $nameLower !== '') {
            $titleLower = Str::lower($title);
            if (str_contains($titleLower, $nameLower)) {
                $score += 25;
            } else {
                $words   = array_filter(explode(' ', $nameLower), fn (string $w) => strlen($w) > 3 && ! in_array($w, self::STRIP_WORDS, true));
                $total   = count($words);
                $matched = $total > 0 ? count(array_filter($words, fn (string $w) => str_contains($titleLower, $w))) : 0;
                if ($total > 0 && $matched >= (int) ceil($total / 2)) {
                    $score += (int) round(20 * $matched / $total);
                }
            }
        }

        // ── Phone match ───────────────────────────────────────────────────────
        $phoneMatched = false;
        if ($phoneDigits !== '' && strlen($phoneDigits) >= 9 && $html !== '') {
            $bodyDigits = (string) preg_replace('/\D/', '', strip_tags($html));
            if (str_contains($bodyDigits, $phoneDigits)) {
                $score        += 30;
                $phoneMatched = true;
                // Also award the short-domain exact-match bonus we withheld above
                if (isset($domainExactShort) && $domainExactShort) {
                    $score += 35;
                }
            }
        }

        // ── SA domain bonus ───────────────────────────────────────────────────
        if (str_contains($host, '.co.za') || str_ends_with($host, '.africa')) {
            $score += 8;
        }

        // ── Has a contact link ────────────────────────────────────────────────
        if (str_contains(Str::lower($html), '/contact')) {
            $score += 5;
        }

        return $score;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Crawling
    // ─────────────────────────────────────────────────────────────────────────

    private function crawlWebsite(string $website, array $company, ?MarketingLeadGenerationRun $run): array
    {
        $homepage = $this->fetchSingle($website);

        if ($homepage === null) {
            return [
                'pages_crawled'    => [],
                'contact_page'     => null,
                'primary_email'    => null,
                'all_emails'       => [],
                'phone'            => $company['phone'] ?? null,
                'image_url'        => '',
                'decision_maker'   => null,
                'notes'            => 'Homepage could not be fetched.',
            ];
        }

        $pagesCrawled   = [$website];
        $allEmails      = $this->extractEmails($homepage['html']);
        $allPhones      = $this->extractPhones(strip_tags($homepage['html']));
        $imageUrl       = $this->extractLogoUrl($homepage['html'], $homepage['url'] ?? $website);
        $contactPage    = null;
        $allPageTexts   = [$homepage['html']];

        if (! $this->hasHighConfidenceEmail($allEmails, $website)) {
            $contactUrls  = array_slice($this->findContactPages($website, $homepage['html']), 0, self::MAX_CONTACT_PAGES);
            $contactPages = $this->parallelFetch($contactUrls);

            foreach ($contactPages as $contactUrl => $page) {
                if ($page === null) {
                    continue;
                }
                $pagesCrawled[] = $contactUrl;
                $allPageTexts[] = $page['html'];
                $pageEmails     = $this->extractEmails($page['html']);
                $pagePhones     = $this->extractPhones(strip_tags($page['html']));
                $allEmails      = array_merge($allEmails, $pageEmails);
                $allPhones      = array_merge($allPhones, $pagePhones);
                if ($pageEmails !== [] && $contactPage === null) {
                    $contactPage = $contactUrl;
                }
                if ($this->hasHighConfidenceEmail($allEmails, $website)) {
                    break;
                }
            }
        }

        $uniqueEmails  = array_values(array_unique(array_map('strtolower', $allEmails)));
        $primaryEmail  = $this->selectPrimaryEmail($uniqueEmails, $website);
        $decisionMaker = $this->extractDecisionMaker(implode(' ', array_map('strip_tags', $allPageTexts)));

        return [
            'pages_crawled'  => $pagesCrawled,
            'contact_page'   => $contactPage,
            'primary_email'  => $primaryEmail,
            'all_emails'     => $uniqueEmails,
            'phone'          => $allPhones[0] ?? ($company['phone'] ?? null),
            'image_url'      => $imageUrl,
            'decision_maker' => $decisionMaker,
            'notes'          => $primaryEmail === null ? 'No public email found on crawled pages.' : null,
        ];
    }

    /**
     * Extract the name of a decision-maker (director, partner, CEO, founder, etc.)
     * from plain text scraped from contact/about/team pages.
     *
     * Returns the best single name found, or null if nothing reliable was detected.
     */
    public function extractDecisionMaker(string $plainText): ?string
    {
        // Title words that indicate a decision-maker role.
        $titlePattern = '(?:Director|Managing\s+Director|MD|CEO|Chief\s+Executive|Founder|'
            . 'Co-Founder|Owner|Principal|Partner|Senior\s+Partner|Managing\s+Partner|'
            . 'Head\s+of|Attorney|Advocate|Advocate\s+&\s+Notary|Conveyancer|Solicitor|'
            . 'Legal\s+Advisor|Chairman|President)';

        // Pattern: "Title: Firstname Lastname" or "Firstname Lastname, Title" or "Firstname Lastname - Title"
        $patterns = [
            // "Director: John Smith" or "MD: Jane Doe"
            '/\b' . $titlePattern . '[:\-–—]\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/u',
            // "John Smith, Director" or "Jane Doe - CEO"
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)[,\-–—\s]+' . $titlePattern . '/u',
            // "John Smith (Director)" or "Jane Doe (Managing Partner)"
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)\s*\(' . $titlePattern . '\)/u',
        ];

        $candidates = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $plainText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    // The name group is always the last capture.
                    $name = trim(end($match));
                    // Ignore very short or obviously wrong matches (single word, all caps, etc.).
                    $words = explode(' ', $name);
                    if (count($words) < 2 || count($words) > 5) {
                        continue;
                    }
                    // Skip names that look like navigation items (all caps or all lower).
                    if ($name === strtoupper($name) || $name === strtolower($name)) {
                        continue;
                    }
                    $candidates[] = $name;
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Return the most frequently occurring name (most reliable signal).
        $frequency = array_count_values($candidates);
        arsort($frequency);

        return (string) array_key_first($frequency);
    }

    public function findContactPages(string $baseUrl, string $homepageHtml): array
    {
        $parsed     = parse_url($baseUrl);
        $baseOrigin = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
        $baseHost   = strtolower($parsed['host'] ?? '');

        $fromHomepage = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$homepageHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        foreach ((new DOMXPath($dom))->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $text = Str::lower(trim($link->textContent));
            $href = trim((string) $link->getAttribute('href'));

            if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, '#')) {
                continue;
            }

            $isContact = false;
            foreach (self::CONTACT_KEYWORDS as $kw) {
                if (str_contains($text, $kw) || str_contains(Str::lower($href), $kw)) {
                    $isContact = true;
                    break;
                }
            }
            if (! $isContact) {
                continue;
            }

            if (str_starts_with($href, '//')) {
                $href = 'https:'.$href;
            } elseif (str_starts_with($href, '/')) {
                $href = rtrim($baseOrigin, '/').$href;
            } elseif (! $this->isHttpUrl($href)) {
                continue;
            }

            if (strtolower(parse_url($href, PHP_URL_HOST) ?? '') === $baseHost && ! in_array($href, $fromHomepage, true)) {
                $fromHomepage[] = $href;
            }
        }

        $standardPaths = array_map(fn (string $p) => rtrim($baseOrigin, '/').$p, self::CONTACT_PATHS);

        return array_values(array_unique(array_merge($fromHomepage, $standardPaths)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Email extraction
    // ─────────────────────────────────────────────────────────────────────────

    public function extractEmails(string $html): array
    {
        $emails = [];

        // 1. mailto: links
        preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $html, $m1);
        foreach ($m1[1] ?? [] as $e) {
            $emails[] = strtolower(trim($e, '.,;: '));
        }

        // 2. JSON-LD
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $jld);
        foreach ($jld[1] ?? [] as $jsonLd) {
            preg_match_all('/["\']email["\']\s*:\s*["\']([^"\'@\s]+@[^"\'@\s]+)["\']/', $jsonLd, $je);
            foreach ($je[1] ?? [] as $e) {
                $emails[] = strtolower(trim($e));
            }
        }

        // 3. Visible text
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $m3);
        foreach ($m3[0] ?? [] as $e) {
            $emails[] = strtolower(trim($e, '.,;: '));
        }

        $emails = array_values(array_unique($emails));

        return array_values(array_filter($emails, function (string $email): bool {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
            foreach (self::REJECT_EMAIL_PATTERNS as $p) {
                if (str_contains($email, $p)) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function extractPhones(string $text): array
    {
        $phones = [];
        preg_match_all('/((?:\+27|0)\d{2}[\s\-]?\d{3}[\s\-]?\d{4})(?!\d)/u', $text, $m);
        foreach ($m[1] ?? [] as $p) {
            $p = trim((string) preg_replace('/\s+/', ' ', $p));
            if (strlen((string) preg_replace('/\D/', '', $p)) >= 9) {
                $phones[] = $p;
            }
        }
        return array_values(array_unique($phones));
    }

    private function selectPrimaryEmail(array $emails, string $website): ?string
    {
        if (empty($emails)) {
            return null;
        }

        $websiteDomain = (string) preg_replace('/^www\./', '', strtolower(parse_url($website, PHP_URL_HOST) ?? ''));

        foreach (self::PRIMARY_EMAIL_PREFIXES as $prefix) {
            foreach ($emails as $email) {
                if (str_starts_with($email, $prefix)) {
                    $emailDomain = strtolower(explode('@', $email)[1] ?? '');
                    if ($websiteDomain === '' || $emailDomain === $websiteDomain || str_ends_with($emailDomain, '.'.$websiteDomain)) {
                        return $email;
                    }
                }
            }
        }

        foreach ($emails as $email) {
            $emailDomain = strtolower(explode('@', $email)[1] ?? '');
            if ($websiteDomain !== '' && ($emailDomain === $websiteDomain || str_ends_with($emailDomain, '.'.$websiteDomain))) {
                return $email;
            }
        }

        foreach ($emails as $email) {
            if (str_ends_with(strtolower(explode('@', $email)[1] ?? ''), '.co.za')) {
                return $email;
            }
        }

        return $emails[0];
    }

    private function hasHighConfidenceEmail(array $emails, string $website): bool
    {
        if (empty($emails)) {
            return false;
        }
        $websiteDomain = (string) preg_replace('/^www\./', '', strtolower(parse_url($website, PHP_URL_HOST) ?? ''));
        $highConf      = ['info@', 'enquiries@', 'reception@', 'admin@', 'contact@', 'office@'];
        foreach ($emails as $email) {
            foreach ($highConf as $prefix) {
                if (str_starts_with($email, $prefix)) {
                    $emailDomain = strtolower(explode('@', $email)[1] ?? '');
                    if ($websiteDomain === '' || $emailDomain === $websiteDomain || str_ends_with($emailDomain, '.'.$websiteDomain)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function fetchSingle(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::FETCH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en-US,en;q=0.9'],
        ]);
        $body      = (string) (curl_exec($ch) ?? '');
        $code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effective = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
        curl_close($ch);

        if ($code < 200 || $code >= 400 || $body === '') {
            return null;
        }

        return ['url' => $effective, 'html' => Str::limit($body, 150_000), 'title' => $this->extractPageTitle($body)];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTML helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function extractPageTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }

    private function extractLogoUrl(string $html, string $baseUrl): string
    {
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            $url = trim($m[1]);
            if ($this->isHttpUrl($url)) {
                return $url;
            }
        }
        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            $url = trim($m[1]);
            if ($this->isHttpUrl($url)) {
                return $url;
            }
        }
        if (preg_match('/<link[^>]+rel=["\'][^"\']*apple-touch-icon[^"\']*["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            $url = trim($m[1]);
            if ($this->isHttpUrl($url)) {
                return $url;
            }
            if (str_starts_with($url, '/')) {
                $parsed = parse_url($baseUrl);
                return ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '').$url;
            }
        }
        $host = parse_url($baseUrl, PHP_URL_HOST) ?? '';
        return $host !== '' ? 'https://www.google.com/s2/favicons?domain='.$host.'&sz=64' : '';
    }

    public function scoreWebsiteMatch(array $company, string $url, string $host, string $html, string $title): int
    {
        $name  = Str::lower((string) ($company['company'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($company['phone'] ?? '')) ?? '';
        return $this->scoreMatch($company, $url, $host, $html, $title, $name, $phone);
    }

    private function isHttpUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function log(?MarketingLeadGenerationRun $run, string $message, array $context = []): void
    {
        Log::info('[CompanyEnrichment] '.$message, array_merge(
            $run !== null ? ['run_id' => $run->id] : [],
            $context,
        ));
    }
}