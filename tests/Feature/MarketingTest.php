<?php

namespace Tests\Feature;

use App\Jobs\DispatchMarketingCampaignJob;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\MarketingAudience;
use App\Models\MarketingCampaign;
use App\Models\MarketingContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_dispatch_only_targets_contacts_in_attached_audiences(): void
    {
        Queue::fake();

        [$client, $account] = $this->clientAndAccount();
        $campaign = MarketingCampaign::create([
            'client_id' => $client->id,
            'email_account_id' => $account->id,
            'name' => 'Scoped Campaign',
            'subject' => 'Hello',
            'body' => 'Message',
            'status' => MarketingCampaign::STATUS_DRAFT,
        ]);

        $first = $this->contact($client, 'first@example.com');
        $overlap = $this->contact($client, 'overlap@example.com');
        $outside = $this->contact($client, 'outside@example.com');

        $primary = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'Primary Leads',
        ]);
        $secondary = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'Secondary Leads',
        ]);
        $unused = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'Unused Leads',
        ]);

        $primary->contacts()->attach([$first->id, $overlap->id]);
        $secondary->contacts()->attach([$overlap->id]);
        $unused->contacts()->attach([$outside->id]);
        $campaign->audiences()->attach([$primary->id, $secondary->id]);

        (new DispatchMarketingCampaignJob($campaign->id))->handle();

        $campaign->refresh();

        $this->assertSame(MarketingCampaign::STATUS_SENDING, $campaign->status);
        $this->assertSame(2, $campaign->total_recipients);
        $this->assertEqualsCanonicalizing(
            ['first@example.com', 'overlap@example.com'],
            $campaign->recipients()->pluck('email')->all(),
        );
    }

    public function test_campaign_creation_requires_and_saves_selected_audiences(): void
    {
        [$client, $account] = $this->clientAndAccount();
        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $contact = $this->contact($client, 'lead@example.com');
        $outside = $this->contact($client, 'outside@example.com');
        $audience = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'July Leads',
        ]);
        $otherAudience = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'Other Leads',
        ]);
        $audience->contacts()->attach($contact->id);
        $otherAudience->contacts()->attach($outside->id);

        $response = $this->actingAs($user)->post(route('marketing.campaigns.store'), [
            'client_id' => $client->id,
            'email_account_id' => $account->id,
            'name' => 'July Campaign',
            'subject' => 'Hello',
            'body' => 'Message',
            'audience_ids' => [$audience->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $campaign = MarketingCampaign::query()->where('name', 'July Campaign')->firstOrFail();

        $this->assertSame(1, $campaign->total_recipients);
        $this->assertTrue($campaign->audiences()->whereKey($audience->id)->exists());
        $this->assertFalse($campaign->audiences()->whereKey($otherAudience->id)->exists());
    }

    public function test_marketing_page_renders_audience_options_with_subscribed_counts(): void
    {
        [$client] = $this->clientAndAccount();
        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $contact = $this->contact($client, 'lead@example.com');
        $audience = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'July Leads',
        ]);
        $audience->contacts()->attach($contact->id);

        $this->actingAs($user)
            ->get(route('marketing.index', ['tab' => 'campaigns']))
            ->assertOk()
            ->assertSee('July Leads')
            ->assertSee('July Leads (1)');
    }

    public function test_existing_contact_can_be_added_to_audience_from_ui_endpoint(): void
    {
        [$client] = $this->clientAndAccount();
        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $contact = $this->contact($client, 'lead@example.com');

        $this->actingAs($user)
            ->post(route('marketing.audiences.store'), [
                'client_id' => $client->id,
                'name' => 'Follow Up Leads',
                'description' => 'Contacts for the next follow-up campaign.',
            ])
            ->assertRedirect(route('marketing.index', ['tab' => 'audiences']));

        $audience = MarketingAudience::query()->where('name', 'Follow Up Leads')->firstOrFail();

        $this->actingAs($user)
            ->post(route('marketing.contacts.audiences.attach', $contact), [
                'audience_ids' => [$audience->id],
                'new_audience_name' => 'VIP Leads',
            ])
            ->assertRedirect();

        $this->assertTrue($contact->audiences()->where('name', 'Follow Up Leads')->exists());
        $this->assertTrue($contact->audiences()->where('name', 'VIP Leads')->exists());
    }

    public function test_contacts_can_be_filtered_by_audience(): void
    {
        [$client] = $this->clientAndAccount();
        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $included = $this->contact($client, 'included@example.com');
        $excluded = $this->contact($client, 'excluded@example.com');
        $audience = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'Filtered Leads',
        ]);
        $audience->contacts()->attach($included->id);

        $this->actingAs($user)
            ->get(route('marketing.index', ['audience_id' => $audience->id]))
            ->assertOk()
            ->assertSee('included@example.com')
            ->assertDontSee('excluded@example.com');
    }

    public function test_selected_contacts_can_be_added_or_moved_between_audiences(): void
    {
        [$client] = $this->clientAndAccount();
        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $first = $this->contact($client, 'first@example.com');
        $second = $this->contact($client, 'second@example.com');
        $source = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'Source Leads',
        ]);
        $target = MarketingAudience::create([
            'client_id' => $client->id,
            'name' => 'Target Leads',
        ]);
        $source->contacts()->attach([$first->id, $second->id]);

        $this->actingAs($user)
            ->post(route('marketing.contacts.audiences.bulk'), [
                'contact_ids' => [$first->id, $second->id],
                'audience_ids' => [$target->id],
                'audience_action' => 'add',
            ])
            ->assertRedirect();

        $this->assertTrue($first->audiences()->whereKey($source->id)->exists());
        $this->assertTrue($first->audiences()->whereKey($target->id)->exists());

        $this->actingAs($user)
            ->post(route('marketing.contacts.audiences.bulk'), [
                'contact_ids' => [$first->id, $second->id],
                'audience_ids' => [$target->id],
                'audience_action' => 'replace',
            ])
            ->assertRedirect();

        $this->assertFalse($first->audiences()->whereKey($source->id)->exists());
        $this->assertTrue($first->audiences()->whereKey($target->id)->exists());
        $this->assertFalse($second->audiences()->whereKey($source->id)->exists());
        $this->assertTrue($second->audiences()->whereKey($target->id)->exists());
    }

    /**
     * @return array{0: Client, 1: EmailAccount}
     */
    private function clientAndAccount(): array
    {
        $client = Client::create([
            'name' => 'BeeStack',
            'slug' => 'beestack',
            'is_active' => true,
        ]);
        $domain = Domain::create([
            'client_id' => $client->id,
            'domain' => 'beestack.co.za',
            'status' => Domain::STATUS_ACTIVE,
        ]);
        $account = EmailAccount::create([
            'client_id' => $client->id,
            'domain_id' => $domain->id,
            'email' => 'info@beestack.co.za',
            'from_name' => 'BeeStack',
            'smtp_host' => 'mail.beestack.co.za',
            'smtp_port' => 587,
            'smtp_encryption' => EmailAccount::ENCRYPTION_STARTTLS,
            'smtp_username' => 'info@beestack.co.za',
            'smtp_password' => 'secret',
            'is_active' => true,
        ]);

        return [$client, $account];
    }

    private function contact(Client $client, string $email): MarketingContact
    {
        return MarketingContact::create([
            'client_id' => $client->id,
            'email' => $email,
            'status' => MarketingContact::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
        ]);
    }
}
