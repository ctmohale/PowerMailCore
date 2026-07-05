<?php

namespace App\Services;

use App\Models\MarketingLeadGenerationRun;
use App\Services\GoogleBusinessTextParserService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
class MarketingLeadGenerationService
{
    /**
     * @return array<int, string>
     */
    private const SEARCH_QUALIFIERS = [
        'contact email phone website',
        '"contact us" email',
        'official website email contact',
        'business profile contact',
        'company email phone address',
        'directory listing contact details',
        'business directory email',
        '"about us" contact email',
    ];

    /**
     * Directories and aggregators that list many businesses - great for bulk leads.
     * @return array<int, string>
     */
    private const DIRECTORY_SITES = [
        'site:yellowpages.com',
        'site:yelp.com',
        'site:hotfrog.com',
        'site:manta.com',
        'site:chamberofcommerce.com',
        'site:bizapedia.com',
        'site:cylex.us',
        'site:businessfinder.net',
        'site:brownbook.net',
        'site:foursquare.com',
    ];

    public function run(MarketingLeadGenerationRun $run): MarketingLeadGenerationRun
    {
        $timeLimitSeconds = $this->extendTimeLimit($run);

        $this->log($run, 'Lead generation run starting.', [
            'target_count' => $run->target_count,
            'industry' => $run->industry,
            'location' => $run->location,
            'province' => $run->province,
            'keywords' => $run->keywords ?? [],
            'openai_configured' => filled(config('services.openai.key')),
            'openai_model' => config('services.openai.model', 'gpt-4.1-mini'),
            'openai_base_url' => config('services.openai.base_url', 'https://api.openai.com/v1'),
            'time_limit_seconds' => $timeLimitSeconds,
        ]);

        $run->forceFill([
            'status' => MarketingLeadGenerationRun::STATUS_RUNNING,
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ])->save();

        try {
            $rawResults = $this->discoverCandidates($run);

            // For pasted-data runs, structureLocally is always used — it directly maps
            // the enriched url/emails/phones into leads without going through OpenAI.
            // OpenAI is only used for web-search runs where we need AI to extract
            // structured data from unstructured HTML snippets.
            $usedOpenAi = $run->use_openai && ! filled($run->source_data);
            $leads = $usedOpenAi
                ? $this->structureWithOpenAi($run, $rawResults)
                : $this->structureLocally($run, $rawResults);

            // When working from pasted data, never cap below the number of raw results
            $effectiveLimit = filled($run->source_data)
                ? max($run->target_count, count($rawResults))
                : $run->target_count;
            $leads = $this->uniqueLeads($leads, $effectiveLimit);
            $this->log($run, 'Lead generation normalized leads.', [
                'raw_result_count' => count($rawResults),
                'lead_count' => count($leads),
                'leads_with_email' => collect($leads)->filter(fn (array $lead): bool => ($lead['email'] ?? '') !== '')->count(),
                'leads_with_phone' => collect($leads)->filter(fn (array $lead): bool => ($lead['phone'] ?? '') !== '')->count(),
            ]);

            $run->forceFill([
                'status' => MarketingLeadGenerationRun::STATUS_COMPLETED,
                'raw_results' => $rawResults,
                'leads' => $leads,
                'discovered_count' => count($leads),
                'error_message' => $leads === [] ? $this->emptyLeadMessage($rawResults) : null,
                'finished_at' => now(),
            ])->save();

            $this->log($run, 'Lead generation run saved.', [
                'status' => MarketingLeadGenerationRun::STATUS_COMPLETED,
                'discovered_count' => count($leads),
                'has_error_message' => $leads === [],
                'database_saved' => $run->exists,
            ]);
        } catch (Throwable $exception) {
            $this->log($run, 'Lead generation run failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ], 'error');

            $run->forceFill([
                'status' => MarketingLeadGenerationRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $run->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function discoverCandidates(MarketingLeadGenerationRun $run): array
    {
        $rawResults = [];
        $targetLeadCount = max(1, $run->target_count);
        $rawTarget = max($targetLeadCount * 5, $targetLeadCount + 40);
        $scrapedUrls = [];

        // ── Phase 1: Process pasted source data (e.g. Google Maps results) ──
        if (filled($run->source_data)) {
            $this->log($run, 'Lead generation processing pasted source data.');

            $parser = new GoogleBusinessTextParserService();
            $parsedCompanies = $parser->parseGoogleBusinessText((string) $run->source_data);

            $this->log($run, 'Lead generation parsed source data entries.', [
                'entry_count' => count($parsedCompanies),
                'companies' => collect($parsedCompanies)->pluck('company_name')->take(10)->all(),
            ]);

            if ($parsedCompanies === []) {
                return $rawResults;
            }

            // Normalise into the format the rest of Phase 1 expects
            $parsed = array_map(fn (array $c): array => [
                'company' => $c['company_name'],
                'phone'   => $c['phone'],
                'address' => $c['address_or_location'],
                'category' => (string) ($c['category'] ?? ''),
                'years_in_business' => (string) ($c['years_in_business'] ?? ''),
                'website' => null,
            ], $parsedCompanies);

            if ($parsed === []) {
                return $rawResults;
            }

            // ── Step A: create a baseline lead for EVERY parsed entry immediately ──
            // This guarantees every company in the paste becomes a lead, even if website scraping fails.
            $baselineByCompany = []; // company => array index in $rawResults
            foreach ($parsed as $entry) {
                $company = $entry['company'];
                if ($company === '') continue;

                $idx = count($rawResults);
                $rawResults[] = [
                    'url' => '',
                    'title' => $company,
                    'description' => '',
                    'company_hint' => $this->cleanCompany($company),
                    'business_category' => (string) ($entry['category'] ?? ''),
                    'company_location' => (string) ($entry['address'] ?? ''),
                    'years_in_business' => (string) ($entry['years_in_business'] ?? ''),
                    'emails' => [],
                    'phones' => $entry['phone'] !== '' ? [$entry['phone']] : [],
                    'social_links' => [],
                    'snippet' => trim(implode(' ', array_filter([
                        $entry['address'],
                        $entry['phone'] !== '' ? 'Phone: '.$entry['phone'] : '',
                    ]))),
                    'site_verified' => false,
                    'source' => 'pasted_data_baseline',
                ];
                $baselineByCompany[$company] = $idx;
            }

            // ── Steps B-E: enrich each company — search web, score website, crawl, extract emails ──
            // Uses CompanyEnrichmentService which runs sequentially per company to avoid
            // search-engine rate limiting that killed the old parallel DDG approach.
            $enricher = new \App\Services\CompanyEnrichmentService();

            foreach ($parsed as $entry) {
                $company = $entry['company'];
                if (! isset($baselineByCompany[$company])) {
                    continue;
                }

                $this->log($run, 'Lead generation enriching company.', ['company' => $company]);

                // If the paste already contained a real URL, use it directly;
                // otherwise let the enricher search the web.
                $companyData = [
                    'company' => $company,
                    'phone'   => $entry['phone'],
                    'address' => $entry['address'] ?? '',
                    'website' => ($entry['website'] ?? null) !== null && $this->isHttpUrl($entry['website'] ?? '')
                        ? $entry['website']
                        : null,
                ];

                // If we already have a URL from the paste, we can skip the search phase
                // and go straight to crawling by passing it as the "website" hint.
                $enrichResult = $enricher->findWebsiteAndContacts($companyData, $run);

                $website = $enrichResult['website'] ?? null;

                if ($website === null) {
                    // No website found — keep baseline lead as-is (phone only)
                    $this->log($run, 'Lead generation no website found for company.', ['company' => $company]);
                    continue;
                }

                $scrapedUrls[] = $this->canonicalUrl($website);

                $emails  = $enrichResult['all_emails'] ?? [];
                $phone   = $entry['phone'] !== '' ? $entry['phone'] : ($enrichResult['phone'] ?? '');
                $logoUrl = (string) ($enrichResult['image_url'] ?? '');

                // Update the baseline lead in-place with enriched data
                $rawResults[$baselineByCompany[$company]] = [
                    'url'              => $website,
                    'title'            => $company,
                    'description'      => ($enrichResult['notes'] ?? ''),
                    'company_hint'     => $this->cleanCompany($company),
                    'business_category'=> (string) ($entry['category'] ?? ''),
                    'company_location' => (string) ($entry['address'] ?? ''),
                    'years_in_business'=> (string) ($entry['years_in_business'] ?? ''),
                    'decision_maker'   => (string) ($enrichResult['decision_maker'] ?? ''),
                    'emails'           => $emails,
                    'phones'           => $phone !== '' ? [$phone] : [],
                    'social_links'     => [],
                    'logo_url'         => $logoUrl,
                    'snippet'      => trim(implode(' ', array_filter([
                        $entry['address'] !== '' ? 'Address: '.$entry['address'] : '',
                        $enrichResult['notes'] ?? '',
                    ]))),
                    'site_verified' => true,
                    'source'        => 'pasted_data',
                ];

                $this->log($run, 'Lead generation enriched company.', [
                    'company'       => $company,
                    'website'       => $website,
                    'email_count'   => count($emails),
                    'primary_email' => $enrichResult['primary_email'] ?? null,
                    'status'        => $enrichResult['status'] ?? '',
                ]);
            }

            // Re-deduplicate. For pasted data we must preserve one row per parsed
            // business branch (company/phone/address), even when unresolved rows
            // share the same Google search URL path.
            $rawResults = collect($rawResults)
                ->unique(function (array $r): string {
                    $source = (string) ($r['source'] ?? '');

                    if (in_array($source, ['pasted_data_baseline', 'pasted_data'], true)) {
                        $company = Str::lower(trim((string) ($r['company_hint'] ?? $r['title'] ?? '')));
                        $phone = preg_replace('/\D/', '', (string) ($r['phones'][0] ?? '')) ?? '';
                        $snippet = Str::lower(trim((string) ($r['snippet'] ?? '')));

                        return 'pasted:'.$company.'|'.$phone.'|'.$snippet;
                    }

                    return 'url:'.$this->canonicalUrl((string) ($r['url'] ?? ''));
                })
                ->values()
                ->all();

            $this->log($run, 'Lead generation pasted data phase complete.', [
                'results_so_far' => count($rawResults),
                'with_email' => collect($rawResults)->filter(fn ($r) => ($r['emails'][0] ?? '') !== '')->count(),
                'with_phone' => collect($rawResults)->filter(fn ($r) => ($r['phones'][0] ?? '') !== '')->count(),
            ]);
        }

        // ── Phase 2: Web search rounds (skipped when source_data was the input) ──
        if (filled($run->source_data) && count($rawResults) > 0) {
            $this->log($run, 'Lead generation skipping web search — source data provided all candidates.');
            return $rawResults;
        }

        $maxRounds = max(3, min(6, (int) ceil($run->target_count / 15) + 2));

        for ($round = 1; $round <= $maxRounds; $round++) {
            $urls = collect($run->source_urls ?? [])
                ->merge($this->searchUrls($run, $round))
                ->filter(fn ($url): bool => $this->isHttpUrl((string) $url))
                ->unique(fn ($url): string => $this->canonicalUrl((string) $url))
                ->reject(fn ($url): bool => in_array($this->canonicalUrl((string) $url), $scrapedUrls, true))
                ->take(max(30, min(200, $targetLeadCount * 8 + ($round * 15))))
                ->values();

            if ($urls->isEmpty()) {
                continue;
            }

            $this->log($run, 'Lead generation URLs selected for scraping.', [
                'round' => $round,
                'url_count' => $urls->count(),
                'urls' => $urls->take(30)->values()->all(),
            ]);

            $roundResults = [];

            foreach ($urls as $url) {
                $scrapedUrls[] = $this->canonicalUrl((string) $url);
                $pages = $this->fetchCandidatePages((string) $url, $run);

                if ($pages === []) {
                    // Site unreachable — extract what we can from search snippet and infer the lead
                    $inferredResult = $this->inferResultFromUrl((string) $url, $run);
                    if ($inferredResult !== null) {
                        $roundResults[] = $inferredResult;
                    }
                    continue;
                }

                $primaryPage = $pages[0];
                $combinedHtml = collect($pages)->pluck('html')->implode(' ');
                $combinedText = collect($pages)
                    ->map(fn (array $page): string => $this->visibleText((string) $page['html']))
                    ->implode(' ');
                $contactSignals = $this->extractContactSignalsFromHtml($combinedHtml);
                $emails = array_values(array_unique(array_merge(
                    $this->extractEmails($combinedHtml.' '.$combinedText),
                    $contactSignals['emails']
                )));
                $phones = array_values(array_unique(array_merge(
                    $this->extractPhones($combinedText),
                    $contactSignals['phones']
                )));
                $socials = $this->extractSocialLinks($combinedHtml);
                $title = $this->pageTitle((string) $primaryPage['html']) ?: $this->companyFromUrl((string) $url);
                $description = $this->pageDescription((string) $primaryPage['html']);
                $logoUrl = $this->extractLogoUrl($combinedHtml, (string) $primaryPage['url']);

                $this->log($run, 'Lead generation extracted page signals.', [
                    'round' => $round,
                    'url' => $primaryPage['url'],
                    'fetched_page_count' => count($pages),
                    'title' => $title,
                    'email_count' => count($emails),
                    'emails' => $emails,
                    'phone_count' => count($phones),
                    'phones' => $phones,
                    'social_count' => count($socials),
                    'text_length' => strlen($combinedText),
                ]);

                // Accept the lead if we have ANY of: email, phone, or title
                // We'll let AI decide what is usable
                if ($emails === [] && $phones === [] && $title === '') {
                    continue;
                }

                $roundResults[] = [
                    'url' => $primaryPage['url'],
                    'title' => $title,
                    'description' => $description,
                    'company_hint' => $this->cleanCompany($title),
                    'emails' => $emails,
                    'phones' => $phones,
                    'social_links' => $socials,
                    'logo_url' => $logoUrl,
                    'snippet' => Str::limit($combinedText, 1200, ''),
                    'site_verified' => true,
                ];

                if (count($roundResults) >= $rawTarget) {
                    break;
                }
            }

            $rawResults = collect(array_merge($rawResults, $roundResults))
                ->filter(fn (array $result): bool => ($result['url'] ?? '') !== '')
                ->unique(fn (array $result): string => $this->canonicalUrl((string) $result['url']))
                ->values()
                ->all();

            $this->log($run, 'Lead generation discovery round complete.', [
                'round' => $round,
                'raw_result_count' => count($rawResults),
                'target_raw_count' => $rawTarget,
            ]);

            // Check if we have enough raw candidates to exceed the target
            $candidateLeads = $this->structureLocally($run, $rawResults);
            $uniqueCount = count($this->uniqueLeads($candidateLeads, $targetLeadCount * 10));

            if ($uniqueCount >= $targetLeadCount) {
                break;
            }
        }

        $this->log($run, 'Lead generation discovery complete.', [
            'raw_result_count' => count($rawResults),
        ]);

        return $rawResults;
    }

    /**
     * When a site cannot be reached, infer a minimal lead record from its URL and domain name.
     * @return array<string, mixed>|null
     */
    private function inferResultFromUrl(string $url, MarketingLeadGenerationRun $run): ?array
    {
        $company = $this->companyFromUrl($url);

        if ($company === '') {
            return null;
        }

        $this->log($run, 'Lead generation inferred result from unreachable URL.', [
            'url' => $url,
            'company_hint' => $company,
        ]);

        return [
            'url' => $url,
            'title' => $company,
            'description' => '',
            'company_hint' => $company,
            'emails' => [],
            'phones' => [],
            'social_links' => [],
            'snippet' => "Business: {$company}. Source: {$url}",
            'site_verified' => false,
        ];
    }

    /**
     * Parse plain-text pasted from Google Maps / Google Search results into structured entries.
     *
     * Handles the concatenated format:
     *   "SponsoredLawtons Africa4,3(37) · Law firm...011 286 6900WebsiteDirections"
     *
     * @return array<int, array{company:string, phone:string, address:string, website:string|null}>
     */
    private function parseSourceDataEntries(string $text): array
    {
        // Decode HTML entities first — pasted text often contains &amp; &quot; etc.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace / line endings — the paste is often one long run-on string
        $text = preg_replace('/\r\n|\r/', "\n", $text) ?? $text;

        // ── Step 1: check if there are markdown [Website](URL) links (from formatted pastes) ──
        $markdownUrls = []; // blockIndex => url
        preg_match_all('/\[Website\]\((https?:\/\/[^\s\)]+)\)/u', $text, $mw);
        foreach ($mw[1] ?? [] as $i => $url) {
            $url = rtrim($url, '.,;)');
            if ($this->isHttpUrl($url) && ! str_contains($url, 'google.com/aclk') && ! str_contains($url, 'google.com/maps')) {
                $markdownUrls[$i] = $url;
            }
        }

        // ── Step 2: remove markdown image noise and links, keeping text content ──
        $text = preg_replace('/!\[.*?\]\(data:[^)]+\)/u', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/u', '$1', $text) ?? $text;

        // ── Step 3: split on "WebsiteDirections" / "Website Directions" / "DirectionsWebsite" ──
        $rawBlocks = preg_split(
            '/Website\s*Directions|Directions\s*Website/ui',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $entries = [];

        foreach ($rawBlocks as $blockIndex => $block) {
            $block = trim($block);
            if (mb_strlen($block) < 4) {
                continue;
            }

            // Remove leading "Sponsored" label (may repeat)
            $block = preg_replace('/^(?:Sponsored\s*)+/iu', '', $block) ?? $block;
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            // ── Extract phone ──
            // SA phone: 0XX XXX XXXX (10 digits) or +27 XX XXX XXXX
            // No trailing \b — phone may be directly concatenated with text (e.g. "3586Charl")
            // Special case: phone may be followed by "24/7" text (e.g. "577524/7 Bail Help")
            $phone = '';
            $phonePattern = '/((?:\+27|0)\d{2}[\s\-]?\d{3}[\s\-]?\d{4})(?!\d)/u';
            $phoneSuffix247Pattern = '/((?:\+27|0)\d{2}[\s\-]?\d{3}[\s\-]?\d{4})\d{0,2}\/7/u';
            if (preg_match($phonePattern, $block, $pm)) {
                $phone = trim(preg_replace('/\s+/', ' ', $pm[1]));
            } elseif (preg_match($phoneSuffix247Pattern, $block, $pm)) {
                // Phone directly followed by "24/7" — capture only the phone part
                $phone = trim(preg_replace('/\s+/', ' ', $pm[1]));
            }
            if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone) ?? '') < 9) {
                $phone = '';
            }

            // ── Extract company name ──
            // It's the text at the start of the block, before:
            //   - a rating pattern like "4,9(623)" or "5,0(28)"
            //   - or a business-type marker like "· Law firm" or "· Lawyer"
            //   - or first "·" separator
            //   - or a known business type word concatenated with no separator

            // Remove phone from block so we don't confuse it with the name
            $nameBlock = $phone !== '' ? str_replace($phone, '', $block) : $block;

            // Strip quoted reviews — "...text..." using unicode curly quotes and straight quotes
            $nameBlock = preg_replace('/[\x{201C}\x{201D}\x{201E}""][^\x{201C}\x{201D}"]{0,300}[\x{201C}\x{201D}""]/us', '', $nameBlock) ?? $nameBlock;
            $nameBlock = trim($nameBlock);

            $company = '';
            if (preg_match('/^(.+?)(?=\s*\d+[,\.]\d+\s*[\(\[]\d)/u', $nameBlock, $nm)) {
                // Stop before rating like "4,9(623)" — most reliable
                $company = trim($nm[1]);
            } elseif (preg_match('/^(.+?)(?=\s*(?:Lawyer|Law firm|Legal services?|Attorneys?|Incorporated|Ltd\.?|Pty))/iu', $nameBlock, $nm)) {
                // Stop before known business type word concatenated with no separator
                $company = trim($nm[1]);
            } elseif (preg_match('/^(.+?)(?=\s*·)/u', $nameBlock, $nm)) {
                // Stop before middle-dot separator (last resort — can appear far into text)
                $company = trim($nm[1]);
            } else {
                // Fallback: take up to 80 chars
                $company = mb_substr($nameBlock, 0, 80);
            }

            // Clean up stray punctuation / HTML entities
            $company = html_entity_decode($company, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $company = trim($company, " \t\n\r\0\x0B·,");

            if ($company === '' || mb_strlen($company) < 2) {
                continue;
            }

            // ── Extract address ──
            $address = '';
            if (preg_match('/\b(\d+\s+[A-Za-z][A-Za-z\s]{3,50}(?:Street|St|Avenue|Ave|Road|Rd|Drive|Dr|Lane|Ln|Boulevard|Blvd|Way|Close|Crescent|Circle|Square|Place|Park|Building|Tower|Floor|Suite))\b/iu', $block, $am)) {
                $address = trim($am[1]);
            }

            // ── Website URL ──
            // Use markdown-extracted URL for this block index if available; otherwise null (will be searched)
            $website = $markdownUrls[$blockIndex] ?? null;

            // Also check for any bare https:// URL in the block (non-Google)
            if ($website === null && preg_match('/https?:\/\/(?!(?:www\.)?google\.com)[^\s\'"<>\)]+/u', $block, $wm)) {
                $candidate = rtrim($wm[0], '.,;)');
                if ($this->isHttpUrl($candidate)) {
                    $website = $candidate;
                }
            }

            $entries[] = [
                'company' => $company,
                'phone' => $phone,
                'address' => $address,
                'website' => $website,
            ];
        }

        return $entries;
    }

    /**
     * Find official websites for multiple companies in parallel using curl_multi.
     * Returns map of companyName => url|null.
     *
     * @param  array<int, string>  $companies
     * @return array<string, string|null>
     */
    private function findWebsitesForCompanies(array $companies, MarketingLeadGenerationRun $run): array
    {
        $location = trim((string) ($run->location ?? ''));
        $locationSuffix = $location !== '' ? ' "'.$location.'"' : '';
        $skip = ['facebook.com', 'twitter.com', 'x.com', 'linkedin.com', 'instagram.com',
            'google.com', 'yelp.com', 'yellowpages.com', 'tripadvisor.com', 'foursquare.com',
            'gumtree.co.za', 'justdial.com', 'wikipedia.org', 'wikidata.org', 'tiktok.com',
            'bizportal.co.za', 'browseafrica.com', 'cylex.us', 'hotfrog'];

        $regionMap = self::ddgRegionMap();
        $kl = $regionMap[$location] ?? '';

        // Build one curl handle per company (DDG HTML search)
        $mh = curl_multi_init();
        $handles = [];  // company => curl handle
        $results = [];

        foreach ($companies as $company) {
            $query = '"'.addcslashes($company, '"').'"'.$locationSuffix;
            $params = ['q' => $query];
            if ($kl !== '') {
                $params['kl'] = $kl;
            }
            $url = 'https://duckduckgo.com/html/?'.http_build_query($params);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => ['Accept-Language: en-US,en;q=0.9'],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            curl_multi_add_handle($mh, $ch);
            $handles[$company] = $ch;
        }

        // Execute all requests in parallel
        $active = null;
        do {
            curl_multi_exec($mh, $active);
            if ($active > 0) {
                curl_multi_select($mh, 0.1);
            }
        } while ($active > 0);

        // Parse each response
        foreach ($handles as $company => $ch) {
            $body = curl_multi_getcontent($ch) ?: '';
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($body === '') {
                $results[$company] = null;
                continue;
            }

            // Extract URLs from DDG HTML
            $dom = $this->loadHtml($body);
            $xpath = new DOMXPath($dom);
            $found = null;

            $ddgPatterns = [
                '//a[contains(@class,"result__a")]',
                '//a[contains(@class,"result__url")]',
                '//h2[@class="result__title"]/a',
                '//div[contains(@class,"result")]//a[@href and not(contains(@class,"result__icon"))]',
            ];

            foreach ($ddgPatterns as $pattern) {
                foreach ($xpath->query($pattern) ?: [] as $link) {
                    if (! $link instanceof DOMElement) {
                        continue;
                    }

                    $href = trim((string) $link->getAttribute('href'));
                    $url = $this->decodeSearchUrl($href);

                    if (! $this->isHttpUrl($url)) {
                        continue;
                    }

                    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
                    $blocked = false;
                    foreach ($skip as $s) {
                        if (str_contains($host, $s)) {
                            $blocked = true;
                            break;
                        }
                    }

                    if (! $blocked) {
                        $found = $url;
                        break;
                    }
                }
                if ($found !== null) break;
            }

            // Fallback: uddg= regex
            if ($found === null) {
                preg_match_all('/href=["\']([^"\']*uddg=[^"\']+)["\']/', $body, $m);
                foreach ($m[1] ?? [] as $href) {
                    $url = $this->decodeSearchUrl(html_entity_decode($href));
                    if (! $this->isHttpUrl($url)) continue;
                    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
                    $blocked = false;
                    foreach ($skip as $s) {
                        if (str_contains($host, $s)) { $blocked = true; break; }
                    }
                    if (! $blocked) { $found = $url; break; }
                }
            }

            $results[$company] = $found;
        }

        curl_multi_close($mh);

        $this->log($run, 'Lead generation parallel website search complete.', [
            'companies' => count($companies),
            'found' => count(array_filter($results)),
        ]);

        return $results;
    }

    /**
     * Search for a company's official website using DuckDuckGo/Bing.
     * Returns the first plausible non-directory business URL found.
     */
    private function findWebsiteForCompany(string $company, MarketingLeadGenerationRun $run): ?string
    {
        $map = $this->findWebsitesForCompanies([$company], $run);

        return $map[$company] ?? $this->findWebsiteViaBing($company, $run);
    }

    /**
     * Search Bing for a company's official website.
     * Used as fallback when DDG returns nothing.
     */
    private function findWebsiteViaBing(string $company, MarketingLeadGenerationRun $run): ?string
    {
        $location = trim((string) ($run->location ?? ''));
        $locationSuffix = $location !== '' ? ' '.$location : '';
        $skip = ['facebook.com', 'twitter.com', 'x.com', 'linkedin.com', 'instagram.com',
            'google.com', 'yelp.com', 'yellowpages.com', 'tripadvisor.com', 'foursquare.com',
            'gumtree.co.za', 'justdial.com', 'wikipedia.org', 'wikidata.org', 'tiktok.com',
            'bizportal.co.za', 'browseafrica.com', 'cylex.us', 'hotfrog', 'snupit',
            'brabys.co.za', 'attorneys.co.za'];

        // Try two Bing queries: quoted name + location, then unquoted + "contact"
        $queries = [
            '"'.$company.'"'.$locationSuffix.' official website',
            $company.$locationSuffix.' contact email attorney',
        ];

        foreach ($queries as $query) {
            $urls = $this->bingUrls($query, $run);
            foreach ($urls as $url) {
                $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
                $blocked = false;
                foreach ($skip as $s) {
                    if (str_contains($host, $s)) {
                        $blocked = true;
                        break;
                    }
                }
                if (! $blocked && $this->isHttpUrl($url)) {
                    $this->log($run, 'Lead generation Bing fallback found website.', [
                        'company' => $company,
                        'url' => $url,
                    ]);
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function searchUrls(MarketingLeadGenerationRun $run, int $round = 1): array
    {
        $queries = $this->queriesForRun($run, $round);
        $urls = [];

        $this->log($run, 'Lead generation search queries generated.', [
            'round' => $round,
            'queries' => $queries,
        ]);

        foreach ($queries as $query) {
            $duckDuckGoUrls = $this->duckDuckGoUrls($query, $run);
            $bingUrls = $this->bingUrls($query, $run);

            $urls = array_merge(
                $urls,
                $duckDuckGoUrls,
                $bingUrls,
            );

            $this->log($run, 'Lead generation search query finished.', [
                'round' => $round,
                'query' => $query,
                'duckduckgo_url_count' => count($duckDuckGoUrls),
                'bing_url_count' => count($bingUrls),
                'total_url_count' => count(array_unique($urls)),
            ]);

            if (count($urls) >= max(100, $run->target_count * 10)) {
                break;
            }
        }

        return $urls;
    }

    /**
     * Map country names to DDG kl region codes (partial — covers most common targets).
     * @return array<string, string>
     */
    private static function ddgRegionMap(): array
    {
        return [
            'South Korea' => 'kr-kr', 'Korea' => 'kr-kr',
            'United States' => 'us-en', 'USA' => 'us-en',
            'United Kingdom' => 'uk-en', 'UK' => 'uk-en',
            'Australia' => 'au-en', 'Canada' => 'ca-en',
            'South Africa' => 'za-en', 'Germany' => 'de-de',
            'France' => 'fr-fr', 'Spain' => 'es-es',
            'Italy' => 'it-it', 'Netherlands' => 'nl-nl',
            'Japan' => 'jp-jp', 'China' => 'cn-zh',
            'India' => 'in-en', 'Brazil' => 'br-pt',
            'Mexico' => 'mx-es', 'Argentina' => 'ar-es',
            'New Zealand' => 'nz-en', 'Singapore' => 'sg-en',
            'Malaysia' => 'my-en', 'Philippines' => 'ph-en',
            'Nigeria' => 'ng-en', 'Kenya' => 'ke-en',
            'Sweden' => 'se-sv', 'Norway' => 'no-no',
            'Denmark' => 'dk-da', 'Finland' => 'fi-fi',
            'Poland' => 'pl-pl', 'Turkey' => 'tr-tr',
            'Saudi Arabia' => 'xa-ar', 'UAE' => 'xa-ar',
            'United Arab Emirates' => 'xa-ar',
            'Pakistan' => 'pk-en', 'Bangladesh' => 'bd-en',
            'Sri Lanka' => 'lk-en', 'Ghana' => 'gh-en',
        ];
    }

    /**
     * Map country names to Bing setmkt market codes.
     * @return array<string, string>
     */
    private static function bingMarketMap(): array
    {
        return [
            'South Korea' => 'ko-KR', 'Korea' => 'ko-KR',
            'United States' => 'en-US', 'USA' => 'en-US',
            'United Kingdom' => 'en-GB', 'UK' => 'en-GB',
            'Australia' => 'en-AU', 'Canada' => 'en-CA',
            'South Africa' => 'en-ZA', 'Germany' => 'de-DE',
            'France' => 'fr-FR', 'Spain' => 'es-ES',
            'Italy' => 'it-IT', 'Netherlands' => 'nl-NL',
            'Japan' => 'ja-JP', 'China' => 'zh-CN',
            'India' => 'en-IN', 'Brazil' => 'pt-BR',
            'Mexico' => 'es-MX', 'New Zealand' => 'en-NZ',
            'Singapore' => 'en-SG', 'Malaysia' => 'en-MY',
            'Sweden' => 'sv-SE', 'Norway' => 'nb-NO',
            'Denmark' => 'da-DK', 'Finland' => 'fi-FI',
            'Poland' => 'pl-PL', 'Turkey' => 'tr-TR',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function queriesForRun(MarketingLeadGenerationRun $run, int $round = 1): array
    {
        $prompt = trim((string) $run->prompt);
        $province = trim((string) ($run->province ?? ''));
        $locationParts = array_filter([$run->location, $province]);
        $locationStr = implode(', ', $locationParts);
        // Quoted location for precise search-engine matching
        $quotedLocation = $run->location ? '"'.trim((string) $run->location).'"' : '';
        $quotedProvince = $province !== '' ? '"'.$province.'"' : '';
        $quotedLocationFull = trim(implode(' ', array_filter([$quotedLocation, $quotedProvince])));
        $base = trim(implode(' ', array_filter([
            $run->industry ?: $prompt,
            $quotedLocationFull,
        ])));
        $baseUnquoted = trim(implode(' ', array_filter([
            $run->industry ?: $prompt,
            $locationStr,
        ])));
        $keywords = collect($run->keywords ?? [])
            ->map(fn ($keyword): string => trim((string) $keyword))
            ->filter()
            ->values()
            ->implode(' ');
        $promptQuery = trim($prompt.' '.$keywords);
        $qualifiers = match ($round) {
            2 => [
                'company email phone website',
                'business listing email contact',
                'official site email',
                'business profile contact us',
                'company directory email phone',
            ],
            3 => [
                'about us contact email phone',
                'professional services website email',
                'business register contact',
                'trade directory email phone',
                'company website email phone address',
            ],
            4 => [
                'business blog website email',
                'small business contact details',
                'company website official',
                'linkedin company page',
                'chamber of commerce member email',
            ],
            5 => [
                'industry association member contact',
                'registered business email',
                'business owner contact phone email',
                'yellow pages listing email',
                'yelp business contact email',
            ],
            default => self::SEARCH_QUALIFIERS,
        };

        $baseWithProvince = $province !== '' ? trim($run->industry.' '.$quotedProvince.' '.$quotedLocation.' '.$keywords) : $base;

        return collect($qualifiers)
            ->map(fn ($qualifier): string => trim($base.' '.$keywords.' '.$qualifier))
            ->prepend($promptQuery)
            ->prepend(trim($base.' '.$keywords))
            ->prepend(trim($base.' '.$keywords.' contact email'))
            ->prepend(trim($base.' '.$keywords.' official website email phone'))
            ->prepend(trim($baseWithProvince.' '.$keywords.' company profile'))
            ->prepend(trim($run->industry.' '.$quotedLocationFull.' '.$keywords.' business website email'))
            ->prepend(trim($run->industry.' '.$quotedLocationFull.' '.$keywords.' email contact site'))
            ->when($province !== '', fn ($c) => $c->prepend(trim($run->industry.' '.$quotedProvince.' '.$quotedLocation.' email contact website')))
            // Unquoted fallback for broader scraping in later rounds
            ->when($round >= 3, fn ($c) => $c->push(trim($baseUnquoted.' '.$keywords.' contact')))
            ->filter()
            ->unique()
            ->take(14)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function duckDuckGoUrls(string $query, ?MarketingLeadGenerationRun $run = null): array
    {
        $location = (string) ($run?->location ?? '');
        $regionMap = self::ddgRegionMap();
        $kl = $regionMap[$location] ?? '';

        $params = ['q' => $query];
        if ($kl !== '') {
            $params['kl'] = $kl;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get('https://duckduckgo.com/html/', $params);
        } catch (Throwable $exception) {
            $this->log($run, 'Lead generation DuckDuckGo request failed.', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ], 'warning');

            return [];
        }

        if (! $response->ok()) {
            $this->log($run, 'Lead generation DuckDuckGo request returned non-OK response.', [
                'query' => $query,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500, ''),
            ], 'warning');

            return [];
        }

        $body = $response->body();
        $dom = $this->loadHtml($body);
        $xpath = new DOMXPath($dom);
        $urls = [];

        // Try multiple XPath patterns to handle DDG HTML variations
        $ddgPatterns = [
            '//a[contains(@class,"result__a")]',
            '//a[contains(@class,"result__url")]',
            '//h2[@class="result__title"]/a',
            '//a[@data-testid="result-title-a"]',
            '//div[contains(@class,"result")]//a[@href and not(contains(@class,"result__icon")) and not(contains(@class,"feedback"))]',
        ];

        foreach ($ddgPatterns as $pattern) {
            foreach ($xpath->query($pattern) ?: [] as $link) {
                if (! $link instanceof DOMElement) {
                    continue;
                }

                $href = trim((string) $link->getAttribute('href'));
                $url = $this->decodeSearchUrl($href);

                if ($this->isHttpUrl($url)) {
                    $urls[] = $url;
                }
            }

            if ($urls !== []) {
                break;
            }
        }

        // Last resort: extract all href values and look for uddg= params
        if ($urls === []) {
            preg_match_all('/href=["\']([^"\']*uddg=[^"\']+)["\']/', $body, $m);
            foreach ($m[1] ?? [] as $href) {
                $url = $this->decodeSearchUrl(html_entity_decode($href));
                if ($this->isHttpUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        $this->log($run, 'Lead generation DuckDuckGo parsed URLs.', [
            'query' => $query,
            'url_count' => count(array_unique($urls)),
            'kl' => $kl,
        ]);

        return array_values(array_unique($urls));
    }

    /**
     * @return array<int, string>
     */
    private function bingUrls(string $query, ?MarketingLeadGenerationRun $run = null): array
    {
        $location = (string) ($run?->location ?? '');
        $marketMap = self::bingMarketMap();
        $mkt = $marketMap[$location] ?? '';

        $params = ['q' => $query, 'count' => 50];
        if ($mkt !== '') {
            $params['setmkt'] = $mkt;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get('https://www.bing.com/search', $params);
        } catch (Throwable $exception) {
            $this->log($run, 'Lead generation Bing request failed.', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ], 'warning');

            return [];
        }

        if (! $response->ok()) {
            $this->log($run, 'Lead generation Bing request returned non-OK response.', [
                'query' => $query,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500, ''),
            ], 'warning');

            return [];
        }

        $dom = $this->loadHtml($response->body());
        $xpath = new DOMXPath($dom);
        $urls = [];

        // Try multiple Bing result XPath patterns
        $bingPatterns = [
            '//li[contains(@class,"b_algo")]//h2/a[@href]',
            '//li[contains(@class,"b_algo")]//a[@href]',
            '//div[@id="b_results"]//li/h2/a[@href]',
            '//main//h2/a[@href]',
            '//div[contains(@class,"b_title")]//a[@href]',
            '//cite',  // fallback: extract from cite tags
        ];

        foreach ($bingPatterns as $pattern) {
            foreach ($xpath->query($pattern) ?: [] as $node) {
                if ($node->nodeName === 'cite') {
                    // cite contains the displayed URL text — try to make it a real URL
                    $text = trim($node->textContent);
                    if ($text !== '' && ! str_starts_with($text, 'http')) {
                        $text = 'https://'.$text;
                    }
                    if ($this->isHttpUrl($text)) {
                        $urls[] = $text;
                    }
                    continue;
                }

                if (! $node instanceof DOMElement) {
                    continue;
                }

                $href = trim((string) $node->getAttribute('href'));
                $url = $this->decodeSearchUrl($href);

                if ($this->isHttpUrl($url) && ! str_contains($url, 'bing.com')) {
                    $urls[] = $url;
                }
            }

            if ($urls !== []) {
                break;
            }
        }

        $this->log($run, 'Lead generation Bing parsed URLs.', [
            'query' => $query,
            'url_count' => count(array_unique($urls)),
            'mkt' => $mkt,
        ]);

        return array_values(array_unique($urls));
    }

    private function decodeSearchUrl(string $href): string
    {
        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        $parts = parse_url($href);
        parse_str($parts['query'] ?? '', $query);

        if (isset($query['uddg'])) {
            return (string) $query['uddg'];
        }

        if (isset($query['u'])) {
            return $this->decodeBingUrlParam((string) $query['u']) ?: $href;
        }

        return $href;
    }

    private function decodeBingUrlParam(string $value): ?string
    {
        $encoded = str_starts_with($value, 'a1') ? substr($value, 2) : $value;
        $encoded = strtr($encoded, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);

        if (! is_string($decoded) || ! $this->isHttpUrl($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{url:string,html:string}|null
     */
    private function fetchPage(string $url, ?MarketingLeadGenerationRun $run = null): ?array
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; PowerMailCoreBot/1.0)'])
                ->get($url);
        } catch (Throwable $exception) {
            $this->log($run, 'Lead generation page fetch failed.', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ], 'warning');

            return null;
        }

        if (! $response->ok()) {
            $this->log($run, 'Lead generation page fetch returned non-OK response.', [
                'url' => $url,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500, ''),
            ], 'warning');

            return null;
        }

        $contentType = strtolower($response->header('content-type', ''));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            $this->log($run, 'Lead generation page fetch skipped non-HTML content.', [
                'url' => $url,
                'status' => $response->status(),
                'content_type' => $contentType,
            ]);

            return null;
        }

        $this->log($run, 'Lead generation page content fetched.', [
            'url' => $url,
            'effective_url' => (string) $response->effectiveUri(),
            'status' => $response->status(),
            'content_type' => $contentType,
            'bytes' => strlen($response->body()),
        ]);

        return [
            'url' => (string) $response->effectiveUri(),
            'html' => Str::limit($response->body(), 250000, ''),
        ];
    }

    /**
     * @return array<int, array{url:string,html:string}>
     */
    private function fetchCandidatePages(string $url, ?MarketingLeadGenerationRun $run = null): array
    {
        $homePage = $this->fetchPage($url, $run);

        if (! $homePage) {
            return [];
        }

        // Fetch homepage + up to 3 contact-type pages to maximise email capture.
        $contactUrls = array_slice($this->contactUrlsFromPage($homePage['html'], $homePage['url']), 0, 3);

        if ($contactUrls === []) {
            return [$homePage];
        }

        $contactPage = $this->fetchPage($contactUrls[0], $run);

        $pages = array_filter([$homePage, $contactPage]);

        return collect($pages)
            ->unique(fn (array $page): string => $this->canonicalUrl((string) $page['url']))
            ->values()
            ->all();
    }

    /**
     * Fetch multiple URLs in parallel using curl_multi.
     * Returns array of page data (url, html, original_url). Unreachable pages are omitted.
     *
     * @param  array<int, string>  $urls
     * @return array<int, array{url:string,html:string,original_url:string}>
     */
    private function fetchPagesParallel(array $urls, ?MarketingLeadGenerationRun $run = null): array
    {
        if ($urls === []) {
            return [];
        }

        $mh = curl_multi_init();
        $handles = []; // url => curl handle

        foreach (array_unique($urls) as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PowerMailCoreBot/1.0)',
                CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: en-US,en;q=0.9'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_ENCODING => '',  // accept gzip/br
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

        $pages = [];

        foreach ($handles as $originalUrl => $ch) {
            $info = curl_getinfo($ch);
            $body = curl_multi_getcontent($ch) ?: '';
            $errorCode = curl_errno($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($errorCode !== 0 || $body === '' || $info['http_code'] < 200 || $info['http_code'] >= 400) {
                $this->log($run, 'Lead generation parallel fetch failed.', [
                    'url' => $originalUrl,
                    'http_code' => $info['http_code'],
                    'curl_error' => $errorCode,
                ], 'warning');
                continue;
            }

            $effectiveUrl = $info['url'] ?? $originalUrl;

            $pages[] = [
                'url' => $effectiveUrl,
                'html' => Str::limit($body, 200000, ''),
                'original_url' => $originalUrl,
            ];
        }

        curl_multi_close($mh);

        $this->log($run, 'Lead generation parallel fetch complete.', [
            'requested' => count($urls),
            'succeeded' => count($pages),
        ]);

        return $pages;
    }

    /**
     * @return array<int, string>
     */
    private function contactUrlsFromPage(string $html, string $baseUrl): array
    {
        $dom = $this->loadHtml($html);
        $xpath = new DOMXPath($dom);
        $urls = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $text = Str::lower(trim($link->textContent));
            $href = trim((string) $link->getAttribute('href'));

            if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            if (! str_contains($text, 'contact') && ! str_contains($href, 'contact') && ! str_contains($href, 'about')) {
                continue;
            }

            $absoluteUrl = $this->absoluteUrl($href, $baseUrl);

            if ($absoluteUrl && $this->isHttpUrl($absoluteUrl)) {
                $urls[] = $absoluteUrl;
            }
        }

        return array_values(array_unique($urls));
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        if ($this->isHttpUrl($href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }

        $parts = parse_url($baseUrl);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $base = $parts['scheme'].'://'.$parts['host'];

        if (str_starts_with($href, '/')) {
            return $base.$href;
        }

        $path = $parts['path'] ?? '/';
        $directory = rtrim(str_ends_with($path, '/') ? $path : dirname($path), '/');

        return $base.($directory === '' ? '' : $directory).'/'.$href;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawResults
     * @return array<int, array<string, mixed>>
     */
    private function structureWithOpenAi(MarketingLeadGenerationRun $run, array $rawResults): array
    {
        if ($rawResults === []) {
            return [];
        }

        // Send up to 3x the target count to OpenAI so it has plenty to pick from
        $sourceResultsForOpenAi = array_slice($rawResults, 0, min(80, max(20, $run->target_count * 3)));

        $locationContext = trim(implode(', ', array_filter([$run->location, $run->province ?? ''])));

        $payload = [
            'model' => config('services.openai.model', 'gpt-4.1-mini'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => implode("\n", [
                        'You are a B2B lead research specialist. Structure public web research into clean marketing import rows.',
                        'CRITICAL RULES:',
                        '1. Only use facts present in the supplied source data — do NOT invent emails, phones, names, or companies.',
                        '2. You MUST return a lead for EVERY company in the source data — return as many leads as there are source entries.',
                        '3. Keep ALL leads — even if they only have a company name + phone number and no email or website.',
                        '4. A phone number alone is sufficient to include a lead. Do NOT discard leads that lack an email.',
                        '5. Extract names from page titles, meta descriptions, and snippets.',
                        '6. Deduplicate only by company name — keep the richer record.',
                        '7. For notes, summarise what the business does in one sentence.',
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'request' => $run->prompt,
                        'industry' => $run->industry,
                        'location' => $locationContext,
                        'province' => $run->province,
                        'target_count' => $run->target_count,
                        'output_columns' => ['Email', 'Name', 'Company', 'Phone', 'Image URL', 'Tags', 'Source URL', 'Notes'],
                        'lead_rules' => [
                            'One row per unique company.',
                            'Leave email blank when no public email is visible — do NOT omit the lead.',
                            'Include EVERY company from source_results — a phone number alone is enough.',
                            'For source pasted_data_baseline, ALWAYS include the lead using company name and phone.',
                            'Extract any social links into the notes field.',
                            'Return a lead for every entry in source_results — do not filter any out.',
                        ],
                        'source_results' => $sourceResultsForOpenAi,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'marketing_leads',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'leads' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => $this->leadSchemaProperties(),
                                    'required' => ['email', 'name', 'company', 'phone', 'image_url', 'tags', 'source_url', 'notes'],
                                ],
                            ],
                        ],
                        'required' => ['leads'],
                    ],
                ],
            ],
        ];

        $this->log($run, 'Lead generation OpenAI request prepared.', [
            'model' => $payload['model'],
            'source_result_count' => count($rawResults),
            'source_results_sent' => count($sourceResultsForOpenAi),
            'openai_configured' => filled(config('services.openai.key')),
        ]);

        $response = Http::timeout(45)
            ->withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', $payload);

        $this->log($run, 'Lead generation OpenAI response received.', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => Str::limit($response->body(), 2000, ''),
        ], $response->successful() ? 'info' : 'warning');

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI could not structure the leads: '.$response->body());
        }

        $decoded = $response->json();
        $text = $decoded['output_text'] ?? $this->firstOutputText($decoded);
        $structured = json_decode((string) $text, true);

        if (! is_array($structured)) {
            $this->log($run, 'Lead generation OpenAI structure unreadable.', [
                'output_text' => Str::limit((string) $text, 2000, ''),
            ], 'warning');

            throw new \RuntimeException('OpenAI returned an unreadable lead structure.');
        }

        $this->log($run, 'Lead generation OpenAI structured output parsed.', [
            'lead_count' => count($structured['leads'] ?? []),
            'structured' => $structured,
        ]);

        return $this->normalizeLeads($structured['leads'] ?? [], $run);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawResults
     */
    private function emptyLeadMessage(array $rawResults): string
    {
        if ($rawResults === []) {
            return 'No reachable public search results were found for this request.';
        }

        $pagesWithEmails = collect($rawResults)
            ->filter(fn (array $result): bool => ($result['emails'] ?? []) !== [])
            ->count();

        if ($pagesWithEmails === 0) {
            $pagesWithPhones = collect($rawResults)
                ->filter(fn (array $result): bool => ($result['phones'] ?? []) !== [])
                ->count();

            if ($pagesWithPhones === 0) {
                return 'Search found public pages, but none contained valid public email addresses or phone numbers to structure.';
            }

            return 'Search found public pages with phone numbers, but no leads passed validation. Check the lead generation logs for rejected rows.';
        }

        return 'OpenAI could not structure any valid marketing leads from the scraped public data.';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function leadSchemaProperties(): array
    {
        $string = ['type' => 'string'];

        return [
            'email' => $string,
            'name' => $string,
            'company' => $string,
            'phone' => $string,
            'image_url' => $string,
            'tags' => [
                'type' => 'array',
                'items' => $string,
            ],
            'source_url' => $string,
            'notes' => $string,
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function firstOutputText(array $decoded): ?string
    {
        foreach ($decoded['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    return $content['text'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawResults
     * @return array<int, array<string, mixed>>
     */
    private function structureLocally(MarketingLeadGenerationRun $run, array $rawResults): array
    {
        $leads = [];

        foreach ($rawResults as $result) {
            $allEmails = array_filter($result['emails'] ?? []);

            // Use primary_email if the enricher set it (pasted-data runs), otherwise
            // fall back to the first email in the list.
            $primaryEmail = (string) ($result['primary_email'] ?? $allEmails[0] ?? '');

            // Append any extra emails to notes so they aren't lost.
            $extraEmails = array_values(array_filter($allEmails, fn ($e) => $e !== $primaryEmail));

            $socialParts = [];
            foreach ($result['social_links'] ?? [] as $platform => $link) {
                $socialParts[] = "{$platform}: {$link}";
            }
            $socialStr = $socialParts !== [] ? ' Socials: '.implode(', ', $socialParts) : '';
            $description = trim((string) ($result['description'] ?? ''));
            $snippet = Str::limit((string) ($result['snippet'] ?? ''), 200, '');
            $extraEmailStr = $extraEmails !== [] ? ' Also: '.implode(', ', $extraEmails) : '';
            $notes = Str::limit(trim(($description !== '' ? $description : $snippet).$socialStr.$extraEmailStr), 300, '');

            $leads[] = [
                'email'             => $primaryEmail,
                'name'              => '',
                'company'           => $this->cleanCompany((string) ($result['company_hint'] ?? $result['title'] ?? '')),
                'business_category' => trim((string) ($result['business_category'] ?? '')),
                'location'          => trim((string) ($result['company_location'] ?? '')),
                'years_in_business' => trim((string) ($result['years_in_business'] ?? '')),
                'decision_maker'    => trim((string) ($result['decision_maker'] ?? '')),
                'phone'             => (string) (($result['phones'][0] ?? '') ?: ''),
                'image_url' => (string) ($result['logo_url'] ?? ''),
                'tags' => $this->tagsForRun($run),
                'source_url' => (string) ($result['url'] ?? ''),
                'notes' => $notes,
            ];
        }

        return $this->normalizeLeads($leads, $run);
    }

    /**
     * @param  array<int, mixed>  $leads
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLeads(array $leads, MarketingLeadGenerationRun $run): array
    {
        $normalized = collect($leads)
            ->filter(fn ($lead): bool => is_array($lead))
            ->map(function (array $lead) use ($run): array {
                $tags = $lead['tags'] ?? [];
                $tags = is_array($tags) ? $tags : explode(',', (string) $tags);
                $email = Str::lower(trim((string) ($lead['email'] ?? '')));

                return [
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '',
                    'rejected_email' => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false ? $email : '',
                    'name' => trim((string) ($lead['name'] ?? '')),
                    'company' => $this->cleanCompany((string) ($lead['company'] ?? '')),
                    'business_category' => trim((string) ($lead['business_category'] ?? $lead['category'] ?? '')),
                    'location'          => trim((string) ($lead['location'] ?? '')),
                    'years_in_business' => trim((string) ($lead['years_in_business'] ?? '')),
                    'decision_maker'    => trim((string) ($lead['decision_maker'] ?? '')),
                    'phone'             => trim((string) ($lead['phone'] ?? '')),
                    'image_url' => $this->normalizeLeadImageUrl(
                        trim((string) ($lead['image_url'] ?? $lead['logo_url'] ?? '')),
                        trim((string) ($lead['source_url'] ?? '')),
                    ),
                    'tags' => collect(array_merge($this->tagsForRun($run), $tags))
                        ->map(fn ($tag): string => Str::lower(trim((string) $tag)))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'source_url' => trim((string) ($lead['source_url'] ?? '')),
                    'notes' => trim((string) ($lead['notes'] ?? '')),
                ];
            })
            ->values();

        $rejectedInvalidEmail = $normalized
            ->filter(fn (array $lead): bool => ($lead['rejected_email'] ?? '') !== '')
            ->values();

        $accepted = $normalized
            ->map(fn (array $lead): array => collect($lead)->except('rejected_email')->all())
            ->filter(fn (array $lead): bool => $this->verifyLeadCandidate($run, $lead))
            ->values()
            ->all();

        $this->log($run, 'Lead generation validation complete.', [
            'input_count' => count($leads),
            'accepted_count' => count($accepted),
            'rejected_count' => count($leads) - count($accepted),
            'invalid_email_count' => $rejectedInvalidEmail->count(),
            'invalid_emails' => $rejectedInvalidEmail->pluck('rejected_email')->all(),
        ]);

        return $accepted;
    }

    private function verifyLeadCandidate(MarketingLeadGenerationRun $run, array $lead): bool
    {
        $company = $this->cleanCompany((string) ($lead['company'] ?? ''));
        $sourceUrl = trim((string) ($lead['source_url'] ?? ''));
        $email = trim((string) ($lead['email'] ?? ''));
        $phone = trim((string) ($lead['phone'] ?? ''));

        if ($company === '') {
            return false;
        }

        // For pasted-data runs, keep parsed leads even when an official website
        // was not confidently found yet.
        if ($sourceUrl === '' && filled($run->source_data)) {
            return $email !== '' || $phone !== '' || mb_strlen($company) > 2;
        }

        // For non-pasted runs we still require a source URL.
        if ($sourceUrl === '') {
            return false;
        }

        // The search queries are already location-specific, so we trust the source.
        // Only apply keyword relevance if explicit keywords were given AND no
        // email/phone found (prevents dropping good leads for vague reasons).

        if ($email !== '' || $phone !== '') {
            return true;
        }

        // No email/phone: need at least the industry to appear in company/notes
        $industryTerm = Str::lower(trim((string) ($run->industry ?? '')));
        if ($industryTerm === '') {
            return true;
        }

        $name = Str::lower(trim((string) ($lead['name'] ?? '')));
        $notes = Str::lower(trim((string) ($lead['notes'] ?? '')));
        $companyLower = Str::lower($company);
        $urlLower = Str::lower($sourceUrl);
        $combined = "$companyLower $name $notes $urlLower";

        // Accept if industry keyword or ANY user keyword appears in combined text
        $terms = collect([$industryTerm])
            ->merge(collect($run->keywords ?? [])->map(fn ($k) => Str::lower(trim((string) $k)))->filter())
            ->filter();

        foreach ($terms as $term) {
            if (str_contains($combined, $term)) {
                return true;
            }
        }

        // Last-resort: accept if we have a non-generic company name (len > 5)
        return mb_strlen($company) > 5;
    }

    /**
     * @param  array<int, array<string, mixed>>  $leads
     * @return array<int, array<string, mixed>>
     */
    private function uniqueLeads(array $leads, int $limit): array
    {
        return collect($leads)
            ->unique(function (array $lead): string {
                $email = Str::lower((string) ($lead['email'] ?? ''));

                if ($email !== '') {
                    return 'email:'.$email;
                }

                return 'partial:'.Str::lower(implode('|', [
                    (string) ($lead['company'] ?? ''),
                    (string) ($lead['source_url'] ?? ''),
                    (string) ($lead['phone'] ?? ''),
                    (string) ($lead['notes'] ?? ''),
                ]));
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function tagsForRun(MarketingLeadGenerationRun $run): array
    {
        return collect([
            'lead-generation',
            $run->industry,
            $run->location,
            $run->province ?? '',
        ])
            ->merge($run->keywords ?? [])
            ->map(fn ($tag): string => Str::lower(trim((string) $tag)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function visibleText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?: $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/', ' ', $text) ?: '';

        return trim($text);
    }

    private function pageTitle(string $html): string
    {
        $dom = $this->loadHtml($html);
        $xpath = new DOMXPath($dom);

        foreach ([
            '//meta[@property="og:site_name"]/@content',
            '//meta[@property="og:title"]/@content',
            '//title',
            '//h1',
        ] as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = $node ? trim($node->textContent) : '';

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function loadHtml(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return $dom;
    }

    private function pageDescription(string $html): string
    {
        $dom = $this->loadHtml($html);
        $xpath = new DOMXPath($dom);

        foreach ([
            '//meta[@name="description"]/@content',
            '//meta[@property="og:description"]/@content',
        ] as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = $node ? trim($node->textContent) : '';

            if ($value !== '') {
                return Str::limit($value, 300, '');
            }
        }

        return '';
    }

    /**
     * Extract social media profile links from a page.
     * @return array<string, string>
     */
    private function extractSocialLinks(string $html): array
    {
        $dom = $this->loadHtml($html);
        $xpath = new DOMXPath($dom);
        $socials = [];
        $socialPatterns = [
            'linkedin' => '/linkedin\.com\/(company|in)\//i',
            'facebook' => '/facebook\.com\//i',
            'twitter' => '/(?:twitter|x)\.com\//i',
            'instagram' => '/instagram\.com\//i',
        ];

        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $link->getAttribute('href'));

            if ($href === '' || ! $this->isHttpUrl($href)) {
                continue;
            }

            foreach ($socialPatterns as $platform => $pattern) {
                if (! isset($socials[$platform]) && preg_match($pattern, $href)) {
                    $socials[$platform] = $href;
                }
            }

            if (count($socials) >= count($socialPatterns)) {
                break;
            }
        }

        return $socials;
    }

    /**
     * Extract direct contact signals from HTML attributes (mailto:, tel:).
     *
     * @return array{emails: array<int, string>, phones: array<int, string>}
     */
    private function extractContactSignalsFromHtml(string $html): array
    {
        $dom = $this->loadHtml($html);
        $xpath = new DOMXPath($dom);

        $emails = [];
        $phones = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $link->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            if (str_starts_with(Str::lower($href), 'mailto:')) {
                $email = Str::lower(trim((string) preg_replace('/^mailto:/i', '', $href)));
                $email = trim((string) preg_replace('/\?.*$/', '', $email));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $emails[] = $email;
                }
            }

            if (str_starts_with(Str::lower($href), 'tel:')) {
                $phone = trim((string) preg_replace('/^tel:/i', '', $href));
                if ($phone !== '') {
                    $phones[] = trim((string) preg_replace('/\s+/', ' ', $phone));
                }
            }
        }

        return [
            'emails' => array_values(array_unique($emails)),
            'phones' => array_values(array_unique($phones)),
        ];
    }

    private function extractLogoUrl(string $html, string $baseUrl): string
    {
        $dom = $this->loadHtml($html);
        $xpath = new DOMXPath($dom);

        $candidates = [
            '//meta[@property="og:image"]/@content',
            '//meta[@name="twitter:image"]/@content',
            '//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "apple-touch-icon")]/@href',
            '//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "icon")]/@href',
        ];

        foreach ($candidates as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = $node ? trim((string) $node->textContent) : '';
            if ($value === '') {
                continue;
            }

            $absolute = $this->absoluteUrl($value, $baseUrl) ?? $value;
            if ($this->isHttpUrl($absolute)) {
                return $absolute;
            }
        }

        if ($this->isHttpUrl($baseUrl)) {
            $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
            if ($host !== '') {
                return 'https://www.google.com/s2/favicons?domain='.urlencode((string) $host).'&sz=128';
            }
        }

        return '';
    }

    private function normalizeLeadImageUrl(string $imageUrl, string $sourceUrl): string
    {
        if ($imageUrl !== '' && $this->isHttpUrl($imageUrl)) {
            return $imageUrl;
        }

        if (! $this->isHttpUrl($sourceUrl)) {
            return '';
        }

        $host = parse_url($sourceUrl, PHP_URL_HOST) ?: '';
        if ($host === '') {
            return '';
        }

        $hostLower = Str::lower((string) $host);
        if (str_contains($hostLower, 'google.com') || str_contains($hostLower, 'google.co')) {
            return '';
        }

        return 'https://www.google.com/s2/favicons?domain='.urlencode((string) $host).'&sz=128';
    }

    /**
     * @return array<int, string>
     */
    private function extractEmails(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($email): string => Str::lower(trim((string) $email)))
            ->reject(fn ($email): bool => str_contains($email, '.png') || str_contains($email, '.jpg') || str_contains($email, '.jpeg'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractPhones(string $text): array
    {
        preg_match_all('/(?:\+\d{1,3}[\s().-]?)?(?:\(?\d{2,4}\)?[\s().-]?){2,5}\d{2,4}/', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($phone): string => trim(preg_replace('/\s+/', ' ', (string) $phone) ?: ''))
            ->filter(function ($phone): bool {
                $digits = strlen(preg_replace('/\D+/', '', (string) $phone) ?: '');

                return $digits >= 7 && $digits <= 15;
            })
            ->unique()
            ->take(3)
            ->values()
            ->all();
    }

    private function cleanCompany(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        $value = preg_replace('/\s*[-|:]\s*(home|contact us|about us|law firm|attorneys).*$/i', '', $value) ?: $value;

        return trim(Str::limit($value, 120, ''));
    }

    private function companyFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./', '', $host) ?: $host;
        $name = explode('.', $host)[0] ?? '';

        return Str::headline(str_replace(['-', '_'], ' ', $name));
    }

    private function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function canonicalUrl(string $url): string
    {
        $parts = parse_url($url);

        return Str::lower(($parts['host'] ?? '').($parts['path'] ?? ''));
    }

    private function extendTimeLimit(MarketingLeadGenerationRun $run): int
    {
        // Scale time: 15s per lead, minimum 300s, maximum 1800s (30 min)
        $seconds = max(300, min(1800, $run->target_count * 15));

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        return $seconds;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(?MarketingLeadGenerationRun $run, string $message, array $context = [], string $level = 'info'): void
    {
        Log::log($level, $message, array_merge([
            'lead_generation_run_id' => $run?->id,
        ], $context));
    }
}
