<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MarketingLeadGenerationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class MarketingPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_page_renders_for_authenticated_admin(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->get(route('marketing.index'));

        $response->assertOk();
        $response->assertSee('Marketing');
        $response->assertDontSee('ParseError');
    }

    public function test_marketing_page_paginates_lead_generation_runs(): void
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
            'status' => User::STATUS_ACTIVE,
        ]);

        foreach (range(1, 7) as $index) {
            MarketingLeadGenerationRun::create([
                'client_id' => $client->id,
                'user_id' => $user->id,
                'prompt' => "Find leads {$index}",
                'industry' => 'Law Firms',
                'location' => 'South Africa',
                'target_count' => 10,
                'status' => MarketingLeadGenerationRun::STATUS_COMPLETED,
                'discovered_count' => 1,
                'leads' => [['email' => "lead{$index}@example.com"]],
            ]);
        }

        $response = $this->actingAs($user)->get(route('marketing.index', ['tab' => 'lead-generation']));

        $response->assertOk();
        $response->assertViewHas('leadGenerationRuns', function (LengthAwarePaginator $paginator): bool {
            return $paginator->perPage() === 6
                && $paginator->total() === 7
                && $paginator->count() === 6;
        });
    }

    public function test_generated_leads_modal_shows_pagination_for_more_than_six_leads(): void
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
            'status' => User::STATUS_ACTIVE,
        ]);

        $run = MarketingLeadGenerationRun::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'prompt' => 'Find law firms in South Africa',
            'industry' => 'Law Firms',
            'location' => 'South Africa',
            'target_count' => 10,
            'status' => MarketingLeadGenerationRun::STATUS_COMPLETED,
            'discovered_count' => 8,
            'leads' => collect(range(1, 8))->map(fn (int $index) => [
                'company' => "Company {$index}",
                'email' => "lead{$index}@example.com",
            ])->all(),
        ]);

        $response = $this->actingAs($user)->get(route('marketing.index', ['tab' => 'lead-generation', '_dialog' => 'lead-run-'.$run->id]));

        $response->assertOk();
        $response->assertSee('Page 1 of 2');
        $response->assertSee('Next');
        $response->assertSee('lead-run-'.$run->id);
        $response->assertSee('lead_page_'.$run->id.'=2');
        $response->assertSee('_dialog=lead-run-'.$run->id);
        $response->assertSee('data-auto-open="true"', false);
    }
}
