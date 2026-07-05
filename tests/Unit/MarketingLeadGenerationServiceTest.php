<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\MarketingLeadGenerationRun;
use App\Models\User;
use App\Services\MarketingLeadGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketingLeadGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_retries_with_additional_search_rounds_until_it_finds_a_valid_lead(): void
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
            'target_count' => 1,
            'keywords' => ['attorneys', 'legal'],
            'source_urls' => [],
            'use_openai' => false,
            'status' => MarketingLeadGenerationRun::STATUS_PENDING,
        ]);

        Http::fake([
            'https://duckduckgo.com/html/' => Http::sequence()
                ->push('<html><body><a class="result__a" href="https://example.com/first">First</a></body></html>', 200)
                ->push('<html><body><a class="result__a" href="https://example.com/second">Second</a></body></html>', 200),
            'https://www.bing.com/search*' => Http::response('<html></html>', 200),
            'https://example.com/first' => Http::response('<html><body><h1>Law Firm</h1><a href="/contact">Contact</a></body></html>', 200),
            'https://example.com/first/contact' => Http::response('<html><body><p>Contact us</p></body></html>', 200),
            'https://example.com/second' => Http::response('<html><body><h1>Acme Legal</h1><a href="/contact">Contact</a></body></html>', 200),
            'https://example.com/second/contact' => Http::response('<html><body><p>Contact us</p><a href="mailto:hello@acmelegal.co.za">hello@acmelegal.co.za</a><p>+27 11 555 0000</p></body></html>', 200),
        ]);

        $service = app(MarketingLeadGenerationService::class);
        $service->run($run);

        $run->refresh();

        $this->assertSame(1, $run->discovered_count);
        $this->assertNotEmpty($run->leads);
        $this->assertSame('hello@acmelegal.co.za', $run->leads[0]['email']);
        $this->assertSame('Acme Legal', $run->leads[0]['company']);
    }
}
