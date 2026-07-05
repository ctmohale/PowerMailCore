<?php

namespace App\Services;

/**
 * Parses raw pasted text from Google Maps / Google Search business listings
 * into a clean array of structured company records.
 *
 * Handles both:
 *  - Newline-separated pastes (proper clipboard paste from browser)
 *  - Concatenated pastes (no line breaks, e.g. from some mobile/copy sources)
 *
 * Does NOT call OpenAI. Does NOT fetch the internet. Pure text parsing only.
 */
class GoogleBusinessTextParserService
{
    // Lines that should always be silently discarded
    private const IGNORED_LINES = [
        'website',
        'directions',
        'search...',
        'home',
        'on-site services',
        'online appointments',
        'on-site services·online appointments',
        'in store shopping',
        'curbside pickup',
        'delivery',
        'takeout',
        'dine-in',
    ];

    // SA city/suburb names used to identify "location-only" address lines
    private const SA_LOCATIONS = [
        'johannesburg', 'sandton', 'pretoria', 'midrand', 'randburg', 'soweto',
        'kempton park', 'boksburg', 'germiston', 'roodepoort', 'centurion',
        'cape town', 'durban', 'port elizabeth', 'east london', 'bloemfontein',
        'polokwane', 'nelspruit', 'rustenburg', 'kimberley', 'pietermaritzburg',
        'benoni', 'krugersdorp', 'vereeniging', 'uitenhage', 'george',
        'fourways', 'rosebank', 'morningside', 'bryanston', 'rivonia', 'sunninghill',
    ];

    /**
     * Parse raw pasted Google business listing text into structured records.
     *
     * @return array<int, array{
     *   company_name: string,
     *   category: string,
    *   years_in_business: string,
     *   rating: float|null,
     *   review_count: int|null,
     *   address_or_location: string,
     *   phone: string,
     *   business_hours_text: string,
     *   description: string,
     *   is_sponsored: bool,
     *   raw_block: string,
     * }>
     */
    public function parseGoogleBusinessText(string $rawText): array
    {
        $text = $this->normalizeText($rawText);
        $lines = $this->cleanLines($text);
        $blocks = $this->splitIntoBlocks($lines);

        $companies = [];

        foreach ($blocks as $block) {
            $company = $this->parseBlock($block);

            if ($company['company_name'] !== '') {
                $companies[] = $company;
            }
        }

        return $companies;
    }

    // ────────────────────────────────────────────────────
    // Text normalization
    // ────────────────────────────────────────────────────

    private function normalizeText(string $text): string
    {
        // Decode HTML entities (&amp; → &, &quot; → ", etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize unicode non-breaking spaces and other whitespace chars
        $text = preg_replace('/\x{00A0}|\x{202F}|\x{2009}|\x{200B}/u', ' ', $text) ?? $text;

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // ── Insert newlines before/after structural markers ──
        // This handles the "concatenated" paste format with no line breaks.
        $insertBefore = [
            'Sponsored',
            'Website',
            'Directions',
        ];
        foreach ($insertBefore as $marker) {
            // Only insert newline if NOT already preceded by a newline
            $text = preg_replace('/(?<!\n)(' . preg_quote($marker, '/') . ')/u', "\n$1", $text) ?? $text;
        }

        // Insert newline after "Website", "Directions", and "Sponsored"
        $text = preg_replace('/(Website|Directions|Sponsored)(?!\n)/u', "$1\n", $text) ?? $text;

        // Insert newline before rating patterns: "4,9(623)" or "5,0(28)"
        // These are always on their own line in a proper paste
        $text = preg_replace('/(?<!\n)(\d+[,\.]\d+\s*\(\d+\))/u', "\n$1", $text) ?? $text;

        // Insert newline before SA phone numbers (starts with 0 or +27, 10 digits)
        // Handle the special case where phone is followed by 24/7: "577524/7" → insert before phone
        $text = preg_replace('/(?<!\n)((?:\+27|0)\d{2}[\s]?\d{3}[\s]?\d{4})(\d{0,2}\/7)/u', "\n$1\n$2", $text) ?? $text;
        $text = preg_replace('/(?<!\n)((?:\+27|0)\d{2}[\s]?\d{3}[\s]?\d{4})(?!\d)/u', "\n$1", $text) ?? $text;

        // Insert newline before "Closed ·" and "Open " hour markers
        $text = preg_replace('/(?<!\n)(Closed\s*·|Open\s+24|Open\s*·)/u', "\n$1", $text) ?? $text;

        // Insert newline before known business type words concatenated without separator
        // e.g. "AttorneyLawyer" or "AttorneyLawyer1062" → "Attorney\nLawyer\n1062"
        $types = ['Civil law attorney', 'Lawyer', 'Law firm', 'Legal services', 'Attorney'];
        foreach ($types as $type) {
            $text = preg_replace('/(?<=[a-z])(' . preg_quote($type, '/') . ')/u', "\n$1", $text) ?? $text;
            // Insert newline after the type word if directly followed by a digit (street number)
            $text = preg_replace('/(' . preg_quote($type, '/') . ')(?=\d)/u', "$1\n", $text) ?? $text;
            // Insert newline after the type word if directly followed by a capital letter (city name)
            $text = preg_replace('/(' . preg_quote($type, '/') . ')(?=[A-Z][a-z])/u', "$1\n", $text) ?? $text;
        }

        // Insert newline before known SA city names that appear after "·" in "years in business" lines
        // e.g. "10+ years in business · Sandton" → "10+ years in business · \nSandton"
        foreach (self::SA_LOCATIONS as $city) {
            $cityPat = preg_quote(ucfirst($city), '/');
            $text = preg_replace('/·\s*(' . $cityPat . ')(?=[^a-zA-Z]|$)/um', "·\n$1", $text) ?? $text;
        }

        return $text;
    }

    /**
     * Split text into trimmed non-empty lines.
     * @return array<int, string>
     */
    private function cleanLines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn (string $line): bool => $line !== '',
        ));
    }

    // ────────────────────────────────────────────────────
    // Block splitting
    // ────────────────────────────────────────────────────

    /**
     * Split lines into business blocks. Each block ends at a "Directions" line.
     * @param  array<int, string>  $lines
     * @return array<int, array<int, string>>
     */
    private function splitIntoBlocks(array $lines): array
    {
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            if (strtolower(trim($line)) === 'directions') {
                if ($current !== []) {
                    $blocks[] = $current;
                    $current = [];
                }
                continue;
            }
            $current[] = $line;
        }

        // Catch any trailing block without "Directions"
        if ($current !== []) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    // ────────────────────────────────────────────────────
    // Block parsing
    // ────────────────────────────────────────────────────

    /**
     * Parse a single business block (array of lines) into a structured record.
     *
     * @param  array<int, string>  $lines
     * @return array{
     *   company_name: string,
     *   category: string,
    *   years_in_business: string,
     *   rating: float|null,
     *   review_count: int|null,
     *   address_or_location: string,
     *   phone: string,
     *   business_hours_text: string,
     *   description: string,
     *   is_sponsored: bool,
     *   raw_block: string,
     * }
     */
    private function parseBlock(array $lines): array
    {
        $record = [
            'company_name' => '',
            'category' => '',
            'years_in_business' => '',
            'rating' => null,
            'review_count' => null,
            'address_or_location' => '',
            'phone' => '',
            'business_hours_text' => '',
            'description' => '',
            'is_sponsored' => false,
            'raw_block' => implode("\n", $lines),
        ];

        $companyFound = false;
        $ratingFound = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $lower = strtolower($trimmed);

            // ── Ignored lines ──
            if ($this->isIgnoredLine($trimmed)) {
                continue;
            }

            // ── Sponsored marker ──
            if ($lower === 'sponsored') {
                $record['is_sponsored'] = true;
                continue;
            }

            // ── Phone number ──
            $phone = $this->extractPhone($trimmed);
            if ($phone !== null) {
                if ($record['phone'] === '') {
                    $record['phone'] = $phone;
                }
                // After extracting phone, check if remainder of line is business hours
                $remainder = trim(str_replace($phone, '', $trimmed));
                if ($remainder !== '' && $this->isBusinessHoursLine($remainder)) {
                    $record['business_hours_text'] = $this->cleanBusinessHours($remainder);
                }
                continue;
            }

            // ── Business hours (without phone on same line) ──
            if ($this->isBusinessHoursLine($trimmed)) {
                if ($record['business_hours_text'] === '') {
                    $record['business_hours_text'] = $this->cleanBusinessHours($trimmed);
                }
                continue;
            }

            // ── Rating line: "4,9(623) · Law firm" ──
            if ($this->isRatingLine($trimmed)) {
                $parsed = $this->parseRatingLine($trimmed);
                $record['rating'] = $parsed['rating'];
                $record['review_count'] = $parsed['review_count'];
                if ($parsed['category'] !== '' && $record['category'] === '') {
                    $record['category'] = $parsed['category'];
                }
                $ratingFound = true;
                continue;
            }

            // ── "X+ years in business" line (may also include location) ──
            if (preg_match('/^\d+\+?\s+years?\s+in\s+business/i', $trimmed)) {
                if ($record['years_in_business'] === '') {
                    if (preg_match('/^(\d+\+?\s+years?\s+in\s+business)/i', $trimmed, $m)) {
                        $record['years_in_business'] = trim($m[1]);
                    }
                }

                if ($record['address_or_location'] === '' && preg_match('/·\s*([A-Za-z][A-Za-z\s\-]{2,})$/u', $trimmed, $m)) {
                    $candidateLocation = trim($m[1]);
                    if ($this->looksLikeAddressOrLocation($candidateLocation)) {
                        $record['address_or_location'] = $candidateLocation;
                    }
                }

                continue;
            }

            // ── Company name (first meaningful non-marker line) ──
            if (! $companyFound && $this->looksLikeCompanyName($trimmed)) {
                $record['company_name'] = $trimmed;
                $companyFound = true;
                continue;
            }

            // ── Address / location (comes after rating, before hours) ──
            if ($companyFound && $record['address_or_location'] === '' && $this->looksLikeAddressOrLocation($trimmed)) {
                $record['address_or_location'] = $trimmed;
                continue;
            }

            // ── Description: anything remaining that has meaningful length ──
            if ($companyFound && mb_strlen($trimmed) > 5) {
                if ($record['description'] === '') {
                    $record['description'] = $this->stripOuterQuotes($trimmed);
                }
            }
        }

        return $record;
    }

    // ────────────────────────────────────────────────────
    // Helper predicates
    // ────────────────────────────────────────────────────

    public function isIgnoredLine(string $line): bool
    {
        $lower = strtolower(trim($line));

        foreach (self::IGNORED_LINES as $ignored) {
            if ($lower === $ignored) {
                return true;
            }
        }

        // Lines that are only punctuation / dots
        if (preg_match('/^[\s·•\-–—|,\.]+$/', $line)) {
            return true;
        }

        return false;
    }

    public function isRatingLine(string $line): bool
    {
        // Matches: "4,9(623) · Law firm" or "5,0(28) · Legal services" or just "4,9(623)"
        return (bool) preg_match('/^\d+[,\.]\d+\s*\(\d+\)/u', trim($line));
    }

    /**
     * @return array{rating: float|null, review_count: int|null, category: string}
     */
    public function parseRatingLine(string $line): array
    {
        $result = ['rating' => null, 'review_count' => null, 'category' => ''];

        // Extract rating and review count
        if (preg_match('/^(\d+)[,\.](\d+)\s*\((\d+)\)/u', trim($line), $m)) {
            $result['rating'] = (float) ($m[1] . '.' . $m[2]);
            $result['review_count'] = (int) $m[3];
        }

        // Extract category (text after "·")
        if (preg_match('/·\s*(.+?)(?:\s*\d+\+?\s+years?|$)/ui', $line, $m)) {
            $cat = trim($m[1]);
            // Remove trailing junk like "10+ years in business"
            $cat = preg_replace('/\s*\d+\+?\s+years?\s+in\s+business.*/i', '', $cat) ?? $cat;
            $result['category'] = trim($cat);
        }

        return $result;
    }

    public function extractPhone(string $line): ?string
    {
        // SA phones: 076 377 9147, 010 140 5775, +27 11 302 0800, 0823991234
        // Pattern: +27 or 0, followed by 9 more digits (with optional spaces/hyphens)
        if (preg_match('/(?<!\d)((?:\+27|0)\d{2}[\s\-]?\d{3}[\s\-]?\d{4})(?!\d)/u', $line, $m)) {
            $raw = $m[1];
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            if (strlen($digits) >= 9 && strlen($digits) <= 12) {
                return trim(preg_replace('/\s+/', ' ', $raw));
            }
        }

        return null;
    }

    public function isBusinessHoursLine(string $line): bool
    {
        $lower = strtolower($line);

        return str_contains($lower, 'closed')
            || str_contains($lower, 'open 24')
            || str_contains($lower, 'opens')
            || str_contains($lower, 'closes')
            || preg_match('/\d+\s*(?:am|pm)/i', $line) === 1;
    }

    private function cleanBusinessHours(string $line): string
    {
        // Strip the phone number portion if present (e.g. "Closed · Opens 8 am Mon · 076 377 9147")
        $line = preg_replace('/((?:\+27|0)(?:\d[ ]?){9}(?:\d))/u', '', $line) ?? $line;
        $line = trim($line, " \t\n\r\0\x0B·");

        return trim($line);
    }

    public function looksLikeCompanyName(string $line): bool
    {
        $lower = strtolower($line);

        // Must not be an ignored line
        if ($this->isIgnoredLine($line)) return false;

        // Must not be a rating line
        if ($this->isRatingLine($line)) return false;

        // Must not be only a business category label
        $categoryWords = ['lawyer', 'law firm', 'legal services', 'attorney', 'civil law attorney',
            'notary', 'conveyancer', 'accountant', 'consultant'];
        if (in_array($lower, $categoryWords, true)) return false;

        // Must not be a phone number
        if ($this->extractPhone($line) !== null && mb_strlen($line) < 20) return false;

        // Must not be business hours
        if ($this->isBusinessHoursLine($line)) return false;

        // Must not be purely numeric or a pure street number
        if (preg_match('/^\d+$/', $line)) return false;

        // Must not be "X+ years in business"
        if (preg_match('/^\d+\+?\s+years?\s+in\s+business/i', $line)) return false;

        // Must not start with a rating digit pattern
        if (preg_match('/^\d+[,\.]\d+/', $line)) return false;

        // Must not be only a known city name (address-only line)
        if (in_array($lower, self::SA_LOCATIONS, true)) return false;

        // Must have at least 2 characters and contain at least one letter
        if (mb_strlen($line) < 2) return false;
        if (! preg_match('/[a-zA-Z]/u', $line)) return false;

        // Must not be only "·" separators
        if (preg_match('/^[\s·•\-–|]+$/', $line)) return false;

        return true;
    }

    public function looksLikeAddressOrLocation(string $line): bool
    {
        $lower = strtolower(trim($line));

        // Discard lines that are just "Direction" (truncated paste artefact)
        if (in_array($lower, ['direction', 'directions', 'website'], true)) return false;

        // Strip leading business-type labels that may still be prepended
        $clean = preg_replace('/^(?:Lawyer|Law firm|Legal services?|Attorney|Civil law attorney)\s*/iu', '', $line) ?? $line;
        $clean = trim($clean);

        // Known SA city/suburb
        if (in_array($lower, self::SA_LOCATIONS, true)) return true;

        // Street address: starts with a number
        if (preg_match('/^\d+\s+[A-Za-z]/u', $clean)) return true;

        // Contains a street type keyword
        if (preg_match('/\b(?:Street|St|Avenue|Ave|Road|Rd|Drive|Dr|Lane|Ln|Boulevard|Blvd|Way|Close|Crescent|Circle|Square|Place|Park|Building|Tower|Floor)\b/iu', $clean)) return true;

        // Short geographical-looking line (city/area name) — capitalized, no digits, not hours
        if (mb_strlen($clean) >= 3 && mb_strlen($clean) <= 40
            && preg_match('/^[A-Z][a-zA-Z\s]+$/u', $clean)
            && ! $this->isBusinessHoursLine($clean)
            && ! in_array(strtolower($clean), ['lawyer', 'law firm', 'legal services', 'attorney'], true)) {
            return true;
        }

        return false;
    }

    private function stripOuterQuotes(string $line): string
    {
        // Remove surrounding curly or straight quotes: "text" → text
        return trim($line, "\"\u{201C}\u{201D}\u{201E}\u{2018}\u{2019}");
    }
}
