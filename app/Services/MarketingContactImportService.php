<?php

namespace App\Services;

use App\Models\MarketingContact;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

class MarketingContactImportService
{
    private const EMAIL_HEADERS = [
        'email',
        'emailaddress',
        'emailaddresses',
        'contactemail',
        'contactemailaddress',
        'clientemail',
        'clientemailaddress',
        'customeremail',
        'customeremailaddress',
        'recipient',
        'recipientemail',
        'recipientemailaddress',
        'to',
        'toemail',
        'mail',
        'useremail',
        'billingemail',
        'accountemail',
    ];

    private const NAME_HEADERS = ['name', 'fullname', 'displayname', 'customername', 'recipientname', 'targetperson', 'contactperson', 'decisionmaker'];

    private const FIRST_NAME_HEADERS = ['firstname', 'first'];

    private const LAST_NAME_HEADERS = ['lastname', 'surname', 'last'];

    private const COMPANY_HEADERS = ['company', 'business', 'organisation', 'organization'];

    private const PHONE_HEADERS = ['phone', 'phonecell', 'mobile', 'telephone', 'cell', 'cellno', 'cellnumber', 'phonenumber', 'contactnumber'];

    private const TAG_HEADERS = ['tag', 'tags', 'list', 'segment'];

    /**
     * @return array{rows:int,created:int,updated:int,skipped:int,errors:array<int, string>}
     */
    public function import(int $clientId, UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->extension() ?: 'csv');

        if (! in_array($extension, ['csv', 'txt', 'tsv', 'xlsx'], true)) {
            return [
                'rows' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['Upload a CSV, TXT, TSV, or XLSX contacts file.'],
            ];
        }

        return $this->importRows($clientId, $this->rowsFromFile($file, $extension), "{$extension}_import");
    }

    /**
     * @param  array<int, array<string, mixed>>  $leads
     * @return array{rows:int,created:int,updated:int,skipped:int,errors:array<int, string>}
     */
    public function importStructuredLeads(int $clientId, array $leads, string $source = 'lead_generation'): array
    {
        $rows = [
            ['Email', 'Name', 'Company', 'Phone', 'Tags', 'Source URL', 'Notes'],
        ];

        foreach ($leads as $lead) {
            if (! is_array($lead)) {
                continue;
            }

            $tags = $lead['tags'] ?? [];
            $tags = is_array($tags) ? implode(', ', $tags) : (string) $tags;

            $rows[] = [
                (string) ($lead['email'] ?? ''),
                (string) ($lead['name'] ?? ''),
                (string) ($lead['company'] ?? ''),
                (string) ($lead['phone'] ?? ''),
                $tags,
                (string) ($lead['source_url'] ?? ''),
                (string) ($lead['notes'] ?? ''),
            ];
        }

        return $this->importRows($clientId, $rows, $source);
    }

    /**
     * @param  iterable<int, array<int, string|null>>  $rows
     * @return array{rows:int,created:int,updated:int,skipped:int,errors:array<int, string>}
     */
    private function importRows(int $clientId, iterable $rows, string $source): array
    {
        $stats = [
            'rows' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        $headers = null;
        $hasHeaderRow = false;
        $missingEmailRows = 0;
        $rowNumber = 0;

        foreach ($rows as $row) {
            $rowNumber++;
            $row = $this->cleanRow($row);

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($headers === null) {
                $normalized = array_map($this->normalizeHeader(...), $row);
                $hasHeaderRow = $this->looksLikeHeaderRow($normalized);

                if ($hasHeaderRow) {
                    $headers = $normalized;

                    continue;
                }

                if (! $this->firstEmailInRow($row)) {
                    $stats['rows']++;
                    $stats['skipped']++;
                    $missingEmailRows++;

                    continue;
                }

                $headers = array_map(fn ($index): string => 'column_'.$index, array_keys($row));
            }

            $stats['rows']++;
            $assoc = $this->associateRow($headers, $row);
            $email = $this->extractEmail($assoc, $row);

            if (! $email) {
                $stats['skipped']++;
                $missingEmailRows++;

                continue;
            }

            $contactData = $this->contactData($assoc, $row);
            $company = trim((string) ($contactData['company'] ?? ''));
            $contact = MarketingContact::query()
                ->where('client_id', $clientId)
                ->where('email', $email)
                ->first();

            if ($contact) {
                $contact->fill(array_filter($contactData, fn ($value) => $value !== null && $value !== []));
                $contact->forceFill([
                    'last_imported_at' => now(),
                ])->save();
                $stats['updated']++;

                continue;
            }

            if ($company !== '' && $this->companyExists($clientId, $company)) {
                $stats['skipped']++;

                continue;
            }

            MarketingContact::create(array_merge($contactData, [
                'client_id' => $clientId,
                'email' => $email,
                'status' => MarketingContact::STATUS_SUBSCRIBED,
                'source' => $source,
                'subscribed_at' => now(),
                'last_imported_at' => now(),
            ]));
            $stats['created']++;
        }

        if ($stats['rows'] === 0) {
            $stats['errors'][] = 'The uploaded file is empty. Upload a contacts file with an Email column and at least one contact row.';
        } elseif ($stats['created'] === 0 && $stats['updated'] === 0 && $missingEmailRows > 0) {
            array_unshift(
                $stats['errors'],
                'No valid email addresses were found. Upload a contacts file with an Email, Email Address, Contact Email, or Customer Email column.',
            );
        }

        return $stats;
    }

    private function companyExists(int $clientId, string $company): bool
    {
        $normalizedCompany = $this->normalizeCompany($company);

        if ($normalizedCompany === '') {
            return false;
        }

        return MarketingContact::query()
            ->where('client_id', $clientId)
            ->whereNotNull('company')
            ->get(['company'])
            ->contains(fn (MarketingContact $contact): bool => $this->normalizeCompany((string) $contact->company) === $normalizedCompany);
    }

    /**
     * @return iterable<int, array<int, string|null>>
     */
    private function rowsFromFile(UploadedFile $file, string $extension): iterable
    {
        if ($extension === 'xlsx') {
            return $this->xlsxRows($file);
        }

        return $this->delimitedRows($file, $extension);
    }

    /**
     * @return Generator<int, array<int, string|null>>
     */
    private function delimitedRows(UploadedFile $file, string $extension): Generator
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            yield [];

            return;
        }

        $delimiter = $extension === 'tsv' ? "\t" : $this->detectDelimiter($handle);

        while (($row = fgetcsv($handle, escape: '\\', separator: $delimiter)) !== false) {
            yield $row;
        }

        fclose($handle);
    }

    /**
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $sample = (string) fgets($handle, 4096);
        rewind($handle);

        $delimiters = [
            ',' => substr_count($sample, ','),
            "\t" => substr_count($sample, "\t"),
            ';' => substr_count($sample, ';'),
        ];

        arsort($delimiters);

        return array_key_first($delimiters) ?: ',';
    }

    /**
     * @return Generator<int, array<int, string|null>>
     */
    private function xlsxRows(UploadedFile $file): Generator
    {
        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            yield [];

            return;
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $worksheetPaths = $this->worksheetPaths($zip);

        if ($worksheetPaths === []) {
            $zip->close();
            yield [];

            return;
        }

        foreach ($worksheetPaths as $worksheetPath) {
            $worksheetXml = $zip->getFromName($worksheetPath);

            if ($worksheetXml === false) {
                continue;
            }

            $worksheet = $this->loadXml($worksheetXml);

            if ($worksheet === null) {
                continue;
            }

            $worksheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            foreach ($worksheet->xpath('//m:sheetData/m:row') ?: [] as $rowNode) {
                $rowNode->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $row = [];
                $maxColumn = -1;

                foreach ($rowNode->xpath('m:c') ?: [] as $cell) {
                    $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $column = $this->xlsxColumnIndex((string) ($cell['r'] ?? ''));

                    if ($column === null) {
                        $column = $maxColumn + 1;
                    }

                    $row[$column] = $this->xlsxCellValue($cell, $sharedStrings);
                    $maxColumn = max($maxColumn, $column);
                }

                if ($maxColumn < 0) {
                    yield [];

                    continue;
                }

                yield array_map(
                    fn ($index): string => (string) ($row[$index] ?? ''),
                    range(0, $maxColumn),
                );
            }
        }

        $zip->close();
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $sharedStrings = [];
        $document = $this->loadXml($xml);

        if ($document === null) {
            return [];
        }

        $document->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($document->xpath('//m:si') ?: [] as $item) {
            $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $sharedStrings[] = collect($item->xpath('.//m:t') ?: [])
                ->map(fn ($text): string => (string) $text)
                ->implode('');
        }

        return $sharedStrings;
    }

    /**
     * @return array<int, string>
     */
    private function worksheetPaths(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relationshipsXml === false) {
            return $this->fallbackWorksheetPaths($zip);
        }

        $workbook = $this->loadXml($workbookXml);
        $relationships = $this->loadXml($relationshipsXml);

        if ($workbook === null || $relationships === null) {
            return $this->fallbackWorksheetPaths($zip);
        }

        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = $workbook->xpath('//m:sheets/m:sheet') ?: [];
        $relationshipTargets = [];

        foreach ($relationships->Relationship as $relationship) {
            $target = ltrim((string) $relationship['Target'], '/');
            $relationshipTargets[(string) $relationship['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        $paths = [];

        foreach ($sheets as $sheet) {
            $relationshipId = (string) $sheet->attributes('r', true)['id'];

            if (isset($relationshipTargets[$relationshipId])) {
                $paths[] = $relationshipTargets[$relationshipId];
            }
        }

        return $paths ?: $this->fallbackWorksheetPaths($zip);
    }

    /**
     * @return array<int, string>
     */
    private function fallbackWorksheetPaths(ZipArchive $zip): array
    {
        $paths = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $paths[] = $name;
            }
        }

        sort($paths, SORT_NATURAL);

        return $paths;
    }

    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        $valueNode = $cell->xpath('m:v');
        $value = isset($valueNode[0]) ? (string) $valueNode[0] : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'inlineStr') {
            return collect($cell->xpath('.//m:t') ?: [])
                ->map(fn ($text): string => (string) $text)
                ->implode('');
        }

        if ($type === 'b') {
            return $value === '1' ? 'TRUE' : 'FALSE';
        }

        return $value;
    }

    private function xlsxColumnIndex(string $cellReference): ?int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            return null;
        }

        $index = 0;

        foreach (str_split(Str::upper($matches[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function loadXml(string $xml): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document instanceof SimpleXMLElement ? $document : null;
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<int, string>
     */
    private function cleanRow(array $row): array
    {
        return collect($row)
            ->map(fn ($value): string => trim((string) $value))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim($value) === '');
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function looksLikeHeaderRow(array $headers): bool
    {
        return collect($headers)
            ->contains(fn ($header): bool => $this->isKnownHeader($header));
    }

    private function isKnownHeader(string $header): bool
    {
        return str_contains($header, 'email')
            || in_array($header, array_merge(
                self::EMAIL_HEADERS,
                self::NAME_HEADERS,
                self::FIRST_NAME_HEADERS,
                self::LAST_NAME_HEADERS,
                self::COMPANY_HEADERS,
                self::PHONE_HEADERS,
                self::TAG_HEADERS,
            ), true);
    }

    private function normalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(ltrim($header, "\xEF\xBB\xBF"))) ?: '';
    }

    private function normalizeCompany(string $company): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower($company)) ?: '';
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function associateRow(array $headers, array $row): array
    {
        $assoc = [];

        foreach ($headers as $index => $header) {
            $assoc[$header] = trim((string) ($row[$index] ?? ''));
        }

        return array_filter($assoc, fn ($value): bool => $value !== '');
    }

    /**
     * @param  array<string, string>  $assoc
     * @param  array<int, string>  $row
     */
    private function extractEmail(array $assoc, array $row): ?string
    {
        $email = $this->emailFromValue($this->firstEmailMappedValue($assoc));

        if (! $email) {
            $email = $this->firstEmailInRow($row);
        }

        return $email;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function firstEmailInRow(array $row): ?string
    {
        return Arr::first(
            array_map($this->emailFromValue(...), $row),
            fn ($value): bool => $value !== null,
        );
    }

    private function emailFromValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (! preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
            return null;
        }

        $email = Str::lower(trim($matches[0], " \t\n\r\0\x0B<>;,"));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param  array<string, string>  $assoc
     * @param  array<int, string>  $row
     * @return array<string, mixed>
     */
    private function contactData(array $assoc, array $row): array
    {
        $firstName = $this->firstMappedValue($assoc, self::FIRST_NAME_HEADERS);
        $lastName = $this->firstMappedValue($assoc, self::LAST_NAME_HEADERS);
        $name = $this->firstMappedValue($assoc, self::NAME_HEADERS)
            ?: trim(implode(' ', array_filter([$firstName, $lastName])));
        $tags = $this->tagsFromValue($this->firstMappedValue($assoc, self::TAG_HEADERS));
        $knownHeaders = array_merge(
            self::EMAIL_HEADERS,
            self::NAME_HEADERS,
            self::FIRST_NAME_HEADERS,
            self::LAST_NAME_HEADERS,
            self::COMPANY_HEADERS,
            self::PHONE_HEADERS,
            self::TAG_HEADERS,
        );

        return [
            'name' => $name ?: null,
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'company' => $this->firstMappedValue($assoc, self::COMPANY_HEADERS),
            'phone' => $this->firstMappedValue($assoc, self::PHONE_HEADERS),
            'tags' => $tags,
            'metadata' => collect($assoc)
                ->reject(fn ($value, $key): bool => in_array($key, $knownHeaders, true))
                ->all(),
        ];
    }

    /**
     * @param  array<string, string>  $assoc
     * @param  array<int, string>  $headers
     */
    private function firstMappedValue(array $assoc, array $headers): ?string
    {
        foreach ($headers as $header) {
            $value = trim((string) ($assoc[$header] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $assoc
     */
    private function firstEmailMappedValue(array $assoc): ?string
    {
        foreach ($assoc as $header => $value) {
            if (in_array($header, self::EMAIL_HEADERS, true) || str_contains($header, 'email')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function tagsFromValue(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(preg_split('/[,;|]+/', $value) ?: [])
            ->map(fn ($tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
