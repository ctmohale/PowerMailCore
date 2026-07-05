<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MarketingLeadGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingLeadGenerationLeadRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_a_single_lead_from_a_generation_run(): void
    {
        $client = Client::create([
            'name' => 'Acme Studio',
            'slug' => 'acme-studio',
            'contact_email' => 'hello@example.com',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $run = MarketingLeadGenerationRun::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'prompt' => 'Find law firms in Johannesburg',
            'industry' => 'Law Firms',
            'location' => 'South Africa',
            'target_count' => 10,
            'status' => MarketingLeadGenerationRun::STATUS_COMPLETED,
            'discovered_count' => 2,
            'leads' => [
                ['company' => 'Alpha Legal', 'email' => 'alpha@example.com'],
                ['company' => 'Beta Legal', 'email' => 'beta@example.com'],
            ],
        ]);

        $response = $this->actingAs($user)
            ->delete(route('marketing.lead-generation.leads.destroy', [$run, 'lead_index' => 0]));

        $response->assertRedirect(route('marketing.index', ['tab' => 'lead-generation']));
        $this->assertSame('lead-run-'.$run->id, $response->getSession()->getOldInput('_dialog'));

        $run->refresh();
        $this->assertCount(1, $run->leads);
        $this->assertSame('Beta Legal', $run->leads[0]['company']);
        $this->assertSame(1, $run->discovered_count);
    }

    public function test_admin_can_remove_multiple_selected_leads_from_a_generation_run(): void
    {
        $client = Client::create([
            'name' => 'Acme Studio',
            'slug' => 'acme-studio',
            'contact_email' => 'hello@example.com',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'client_id' => $client->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $run = MarketingLeadGenerationRun::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'prompt' => 'Find law firms in Johannesburg',
            'industry' => 'Law Firms',
            'location' => 'South Africa',
            'target_count' => 10,
            'status' => MarketingLeadGenerationRun::STATUS_COMPLETED,
            'discovered_count' => 3,
            'leads' => [
                ['company' => 'Alpha Legal', 'email' => 'alpha@example.com'],
                ['company' => 'Beta Legal', 'email' => 'beta@example.com'],
                ['company' => 'Gamma Legal', 'email' => 'gamma@example.com'],
            ],
        ]);

        $response = $this->actingAs($user)
            ->delete(route('marketing.lead-generation.leads.mass-destroy', $run), [
                'lead_indices' => [0, 2],
            ]);

        $response->assertRedirect(route('marketing.index', ['tab' => 'lead-generation']));
        $this->assertSame('lead-run-'.$run->id, $response->getSession()->getOldInput('_dialog'));

        $run->refresh();
        $this->assertCount(1, $run->leads);
        $this->assertSame('Beta Legal', $run->leads[0]['company']);
        $this->assertSame(1, $run->discovered_count);
    }
}