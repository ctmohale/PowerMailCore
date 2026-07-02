<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingContact;
use App\Models\User;
use App\Services\SmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_users_need_marketing_permission_to_access_marketing(): void
    {
        $client = Client::create([
            'name' => 'BeeStack',
            'slug' => 'beestack',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
                User::PERMISSION_MANAGE_MARKETING => false,
            ]),
        ]);

        $this->actingAs($user)
            ->get('/marketing')
            ->assertForbidden();

        $user->forceFill([
            'permissions' => array_merge($user->permissions, [
                User::PERMISSION_MANAGE_MARKETING => true,
            ]),
        ])->save();

        $this->actingAs($user->fresh())
            ->get('/marketing')
            ->assertOk();
    }

    public function test_admin_can_import_marketing_contacts_from_csv(): void
    {
        [$user, $client] = $this->createMarketingFixture();
        $file = UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
            'Email,Name,Company,Tags',
            'ALICE@example.com,Alice Example,BeeStack,"customers, july"',
            'bob@example.com,Bob Example,Acme,leads',
        ]));

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import complete: 2 added, 0 updated, 0 skipped.');

        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'alice@example.com',
            'name' => 'Alice Example',
            'company' => 'BeeStack',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
        ]);
        $this->assertSame(['customers', 'july'], MarketingContact::where('email', 'alice@example.com')->first()->tags);
    }

    public function test_admin_can_import_marketing_contacts_from_xlsx(): void
    {
        [$user, $client] = $this->createMarketingFixture();
        $file = $this->fakeXlsxUpload([
            ['Email', 'Name', 'Company', 'Tags'],
            ['CARA@example.com', 'Cara Example', 'Northwind', 'customers; vip'],
            ['drew@example.com', 'Drew Example', 'Globex', 'leads'],
        ]);

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import complete: 2 added, 0 updated, 0 skipped.');

        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'cara@example.com',
            'name' => 'Cara Example',
            'company' => 'Northwind',
            'source' => 'xlsx_import',
        ]);
        $this->assertSame(['customers', 'vip'], MarketingContact::where('email', 'cara@example.com')->first()->tags);
    }

    public function test_xlsx_import_maps_lead_pack_name_and_phone_columns(): void
    {
        [$user, $client] = $this->createMarketingFixture();
        $file = $this->fakeXlsxUpload([
            ['#', 'Priority', 'Company', 'Industry', 'City/Area', 'Target person', 'Role', 'Public email', 'Phone/Cell', 'Personalized BeeStack angle'],
            ['1', 'A', 'Ncube Incorporated Attorneys', 'Law firm', 'Illovo/Sandton', 'Bafana Ncube', 'Founder / Managing Director', 'reception@ncubeinc.co.za', '(011) 880 4204', 'AI legal admin assistant + client onboarding workflow'],
        ]);

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import complete: 1 added, 0 updated, 0 skipped.');

        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'reception@ncubeinc.co.za',
            'name' => 'Bafana Ncube',
            'company' => 'Ncube Incorporated Attorneys',
            'phone' => '(011) 880 4204',
        ]);

        $this->actingAs($user)
            ->get('/marketing')
            ->assertOk()
            ->assertSee('Decision Maker')
            ->assertSee('Sector')
            ->assertSee('Focus')
            ->assertSee('Bafana Ncube')
            ->assertSee('Founder / Managing Director')
            ->assertSee('Law firm')
            ->assertSee('AI legal admin assistant + client onboarding workflow');
    }

    public function test_marketing_contacts_table_uses_metadata_fallbacks_for_existing_imports(): void
    {
        [$user, $client] = $this->createMarketingFixture();

        MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'reception@ncubeinc.co.za',
            'company' => 'Ncube Incorporated Attorneys',
            'metadata' => [
                'targetperson' => 'Bafana Ncube',
                'role' => 'Founder / Managing Director',
                'phonecell' => '(011) 880 4204',
                'industry' => 'Law firm',
                'personalizedbeestackangle' => 'AI legal admin assistant + client onboarding workflow',
            ],
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/marketing')
            ->assertOk()
            ->assertSee('Bafana Ncube')
            ->assertSee('(011) 880 4204')
            ->assertSee('Founder / Managing Director')
            ->assertSee('Law firm')
            ->assertSee('AI legal admin assistant + client onboarding workflow');
    }

    public function test_import_skips_rows_when_company_already_exists_for_client(): void
    {
        [$user, $client] = $this->createMarketingFixture();

        MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'existing@ncubeinc.co.za',
            'name' => 'Existing Contact',
            'company' => 'Ncube Incorporated Attorneys',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
            'Email,Name,Company',
            'new@ncubeinc.co.za,New Contact,Ncube Incorporated Attorneys',
            'fresh@example.com,Fresh Contact,Fresh Company',
        ]));

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import complete: 1 added, 0 updated, 1 skipped.');

        $this->assertDatabaseMissing('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'new@ncubeinc.co.za',
        ]);
        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'fresh@example.com',
            'company' => 'Fresh Company',
        ]);
    }

    public function test_import_all_duplicate_companies_returns_successful_skip(): void
    {
        [$user, $client] = $this->createMarketingFixture();

        MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'existing@ncubeinc.co.za',
            'name' => 'Existing Contact',
            'company' => 'Ncube Incorporated Attorneys',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
            'Email,Name,Company',
            'new@ncubeinc.co.za,New Contact,Ncube Incorporated Attorneys',
        ]));

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import complete: 0 added, 0 updated, 1 skipped.');

        $this->assertDatabaseMissing('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'new@ncubeinc.co.za',
        ]);
    }

    public function test_xlsx_import_skips_empty_sheets_and_rows_but_imports_rows_with_data(): void
    {
        [$user, $client] = $this->createMarketingFixture();
        $file = $this->fakeXlsxWorkbookUpload([
            'Empty Export' => [
                ['Contacts export'],
                ['', '', ''],
            ],
            'Contacts' => [
                ['Email', 'Name', 'Company'],
                ['', '', ''],
                ['erin@example.com', 'Erin Example', 'Initech'],
                ['', 'Missing Email', 'No Import'],
                ['frank@example.com', 'Frank Example', 'Umbrella'],
            ],
        ]);

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import complete: 2 added, 0 updated, 2 skipped.');

        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'erin@example.com',
            'name' => 'Erin Example',
            'company' => 'Initech',
        ]);
        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'frank@example.com',
            'name' => 'Frank Example',
            'company' => 'Umbrella',
        ]);
        $this->assertDatabaseMissing('marketing_contacts', [
            'name' => 'Missing Email',
        ]);
    }


    public function test_import_accepts_common_email_headers_and_embedded_email_text(): void
    {
        [$user, $client] = $this->createMarketingFixture();
        $file = UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
            'Contact Email Address,Full Name,Business',
            '"Alice Example <ALICE@example.com>",Alice Example,BeeStack',
            'bob@example.com,Bob Example,Acme',
        ]));

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import complete: 2 added, 0 updated, 0 skipped.');

        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'alice@example.com',
            'name' => 'Alice Example',
            'company' => 'BeeStack',
        ]);
        $this->assertDatabaseHas('marketing_contacts', [
            'client_id' => $client->id,
            'email' => 'bob@example.com',
        ]);
    }

    public function test_import_without_email_addresses_shows_a_clear_error(): void
    {
        [$user, $client] = $this->createMarketingFixture();
        $file = UploadedFile::fake()->createWithContent('report.csv', implode("\n", [
            'No results for the given date range',
            'This report does not include contact emails',
        ]));

        $this->actingAs($user)
            ->post('/marketing/contacts/import', [
                'client_id' => $client->id,
                'contacts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'contacts_file' => 'No valid email addresses were found. Upload a contacts file with an Email, Email Address, Contact Email, or Customer Email column.',
            ]);

        $this->assertDatabaseCount('marketing_contacts', 0);
    }

    public function test_admin_can_send_email_to_individual_marketing_contact_and_track_log(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null): ?string
            {
                $this->sent[] = compact('account', 'to', 'subject', 'html', 'text');

                return 'individual-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [$user, $client,, $account] = $this->createMarketingFixture();
        $contact = MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'owner@example.com',
            'name' => 'Owner Example',
            'company' => 'Owner Co',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('marketing.contacts.send-email', $contact), [
                'email_account_id' => $account->id,
                'subject' => 'Hello {{ company }}',
                'message_body' => 'Hi {{ name }}, quick note for {{ company }}.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Email sent to owner@example.com.');

        $this->assertCount(1, $fakeMailer->sent);
        $this->assertSame('owner@example.com', $fakeMailer->sent[0]['to']);
        $this->assertSame('Hello Owner Co', $fakeMailer->sent[0]['subject']);
        $this->assertSame('Hi Owner Example, quick note for Owner Co.', $fakeMailer->sent[0]['text']);
        $this->assertDatabaseHas('email_logs', [
            'client_id' => $client->id,
            'email_account_id' => $account->id,
            'marketing_contact_id' => $contact->id,
            'to_email' => 'owner@example.com',
            'subject' => 'Hello Owner Co',
            'status' => EmailLog::STATUS_SENT,
            'provider_message_id' => 'individual-message-id',
        ]);

        $this->actingAs($user)
            ->get(route('email-logs.index', ['contact_id' => $contact->id]))
            ->assertOk()
            ->assertSee('Owner Co')
            ->assertSee('owner@example.com');
    }

    public function test_marketing_sent_count_uses_contact_email_logs_and_senders_can_view_history(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null): ?string
            {
                return 'tracked-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [, $client,, $account] = $this->createMarketingFixture();
        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_CLIENT_USER,
            'permissions' => array_merge(User::defaultClientPermissions(), [
                User::PERMISSION_SEND_EMAILS => true,
                User::PERMISSION_VIEW_LOGS => false,
                User::PERMISSION_MANAGE_MARKETING => true,
            ]),
        ]);
        $user->emailAccounts()->sync([$account->id]);
        $contact = MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'tracked@example.com',
            'name' => 'Tracked Contact',
            'company' => 'Tracked Co',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('marketing.contacts.send-email', $contact), [
                'email_account_id' => $account->id,
                'subject' => 'Tracked hello',
                'message_body' => 'Checking tracking.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('marketing.index'))
            ->assertOk()
            ->assertSee('Sent')
            ->assertSee('1');

        $this->actingAs($user)
            ->get(route('email-logs.index', ['contact_id' => $contact->id]))
            ->assertOk()
            ->assertSee('Sent Email History')
            ->assertSee('Tracked Co')
            ->assertSee('Not opened');
    }

    public function test_sent_email_history_filters_by_search_and_open_status(): void
    {
        [$user, $client,, $account] = $this->createMarketingFixture();
        $openedContact = MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'opened@example.com',
            'name' => 'Opened Contact',
            'company' => 'Opened Co',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);
        $closedContact = MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'closed@example.com',
            'name' => 'Closed Contact',
            'company' => 'Closed Co',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);

        EmailLog::create([
            'client_id' => $client->id,
            'email_account_id' => $account->id,
            'marketing_contact_id' => $openedContact->id,
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'opened@example.com',
            'subject' => 'Opened proposal',
            'status' => EmailLog::STATUS_OPENED,
            'sent_at' => now(),
            'opened_at' => now(),
        ]);
        EmailLog::create([
            'client_id' => $client->id,
            'email_account_id' => $account->id,
            'marketing_contact_id' => $closedContact->id,
            'from_email' => 'info@beestack.co.za',
            'to_email' => 'closed@example.com',
            'subject' => 'Closed proposal',
            'status' => EmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('email-logs.index', ['q' => 'Opened Co', 'opened' => 'opened']))
            ->assertOk()
            ->assertSee('Opened Co')
            ->assertSee('Opened')
            ->assertDontSee('Closed Co');
    }

    public function test_campaign_send_delivers_to_subscribed_contacts_only(): void
    {
        $fakeMailer = new class extends SmtpMailer
        {
            public array $sent = [];

            public function send(EmailAccount $account, string $to, string $subject, string $html, ?string $text = null): ?string
            {
                $this->sent[] = compact('account', 'to', 'subject', 'html', 'text');

                return 'marketing-message-id';
            }
        };

        $this->app->instance(SmtpMailer::class, $fakeMailer);

        [$user, $client,, $account] = $this->createMarketingFixture();

        MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'alice@example.com',
            'name' => 'Alice Example',
            'first_name' => 'Alice',
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);
        MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'unsubscribed@example.com',
            'name' => 'No Send',
            'status' => MarketingContact::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/marketing/campaigns', [
                'client_id' => $client->id,
                'email_account_id' => $account->id,
                'name' => 'July Update',
                'subject' => 'Hello {{ first_name }}',
                'body' => 'Hi {{ name }}, this is the July update.',
                'send_now' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Campaign sent: 1 sent, 0 failed.');

        $this->assertCount(1, $fakeMailer->sent);
        $this->assertSame('alice@example.com', $fakeMailer->sent[0]['to']);
        $this->assertSame('Hello Alice', $fakeMailer->sent[0]['subject']);
        $this->assertSame('Hi Alice Example, this is the July update.', $fakeMailer->sent[0]['text']);
        $this->assertDatabaseHas('marketing_campaigns', [
            'client_id' => $client->id,
            'name' => 'July Update',
            'status' => MarketingCampaign::STATUS_SENT,
            'total_recipients' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
        ]);
        $this->assertDatabaseHas('marketing_campaign_recipients', [
            'email' => 'alice@example.com',
            'status' => MarketingCampaignRecipient::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'marketing_contact_id' => MarketingContact::where('email', 'alice@example.com')->value('id'),
            'to_email' => 'alice@example.com',
            'status' => EmailLog::STATUS_SENT,
            'provider_message_id' => 'marketing-message-id',
        ]);
    }

    public function test_marketing_analytics_tab_shows_campaign_metrics(): void
    {
        [$user, $client,, $account] = $this->createMarketingFixture();

        MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'alice@example.com',
            'name' => 'Alice',
            'tags' => ['customers', 'july'],
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);
        MarketingContact::create([
            'client_id' => $client->id,
            'email' => 'bob@example.com',
            'name' => 'Bob',
            'tags' => ['leads'],
            'status' => MarketingContact::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        MarketingCampaign::create([
            'client_id' => $client->id,
            'email_account_id' => $account->id,
            'name' => 'July Update',
            'subject' => 'July news',
            'status' => MarketingCampaign::STATUS_PARTIAL,
            'total_recipients' => 12,
            'sent_count' => 9,
            'failed_count' => 3,
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/marketing?tab=analytics')
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Send Volume')
            ->assertSee('Delivery Rate')
            ->assertSee('75%')
            ->assertSee('Audience Health')
            ->assertSee('Top Tags')
            ->assertSee('July Update');
    }

    private function createMarketingFixture(): array
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'BeeStack',
            'slug' => 'beestack',
            'is_active' => true,
        ]);
        $domain = Domain::create([
            'client_id' => $client->id,
            'domain' => 'beestack.co.za',
            'status' => 'active',
        ]);
        $account = EmailAccount::create([
            'client_id' => $client->id,
            'domain_id' => $domain->id,
            'email' => 'info@beestack.co.za',
            'from_name' => 'BeeStack',
            'smtp_host' => 'cp75.domains.co.za',
            'smtp_port' => 465,
            'smtp_encryption' => EmailAccount::ENCRYPTION_SSL,
            'smtp_username' => 'info@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
        ]);
        $template = EmailTemplate::create([
            'client_id' => $client->id,
            'key' => 'marketing',
            'name' => 'Marketing Template',
            'subject' => 'Hello {{ name }}',
            'body_html' => '<p>Hello {{ name }}</p>',
            'body_text' => 'Hello {{ name }}',
            'is_active' => true,
        ]);

        return [$user, $client, $domain, $account, $template];
    }

    private function fakeXlsxUpload(array $rows): UploadedFile
    {
        return $this->fakeXlsxWorkbookUpload(['Contacts' => $rows]);
    }

    private function fakeXlsxWorkbookUpload(array $worksheets): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'contacts_xlsx_');
        $path = $temp.'.xlsx';
        rename($temp, $path);

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $contentTypeSheets = collect($worksheets)
            ->keys()
            ->map(fn ($name, int $index): string => '    <Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>')
            ->implode("\n");
        $zip->addFromString('[Content_Types].xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
{$contentTypeSheets}
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $workbookSheets = collect($worksheets)
            ->keys()
            ->map(function (string $name, int $index): string {
                $escaped = htmlspecialchars($name, ENT_XML1);
                $sheetId = $index + 1;

                return '        <sheet name="'.$escaped.'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';
            })
            ->implode("\n");
        $zip->addFromString('xl/workbook.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
{$workbookSheets}
    </sheets>
</workbook>
XML);
        $relationships = collect($worksheets)
            ->keys()
            ->map(fn ($name, int $index): string => '    <Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>')
            ->implode("\n");
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
{$relationships}
</Relationships>
XML);

        foreach (array_values($worksheets) as $index => $rows) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->xlsxSheetXml($rows));
        }

        $zip->close();

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile(
            $path,
            'contacts.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function xlsxSheetXml(array $rows): string
    {
        $sheetRows = collect($rows)
            ->map(function (array $row, int $rowIndex): string {
                $cells = collect($row)
                    ->map(function (?string $value, int $columnIndex) use ($rowIndex): string {
                        $cell = $this->xlsxColumnName($columnIndex).($rowIndex + 1);
                        $escaped = htmlspecialchars((string) $value, ENT_XML1);

                        return '<c r="'.$cell.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
                    })
                    ->implode('');

                return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
            })
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $index = intdiv($index - $remainder - 1, 26);
        }

        return $name;
    }
}
