<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_audiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source', 80)->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'name']);
            $table->index(['client_id', 'source']);
        });

        Schema::create('marketing_audience_contact', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_audience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketing_audience_id', 'marketing_contact_id'], 'audience_contact_unique');
            $table->index('marketing_contact_id', 'audience_contact_contact_index');
        });

        Schema::create('marketing_audience_campaign', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_audience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketing_audience_id', 'marketing_campaign_id'], 'audience_campaign_unique');
            $table->index('marketing_campaign_id', 'audience_campaign_campaign_index');
        });

        $this->backfillLegacyCampaignAudiences();
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_audience_campaign');
        Schema::dropIfExists('marketing_audience_contact');
        Schema::dropIfExists('marketing_audiences');
    }

    private function backfillLegacyCampaignAudiences(): void
    {
        $now = now();

        DB::table('marketing_campaigns')
            ->select('client_id', 'recipient_tag')
            ->distinct()
            ->orderBy('client_id')
            ->get()
            ->each(function (object $campaignGroup) use ($now): void {
                $clientId = (int) $campaignGroup->client_id;
                $tag = filled($campaignGroup->recipient_tag) ? trim((string) $campaignGroup->recipient_tag) : null;
                $audienceName = $tag ? 'Legacy tag: '.$tag : 'Legacy all subscribed';

                $audienceId = DB::table('marketing_audiences')->insertGetId([
                    'client_id' => $clientId,
                    'name' => $this->uniqueAudienceName($clientId, $audienceName),
                    'description' => $tag
                        ? 'Backfilled from campaigns that previously sent to the '.$tag.' tag.'
                        : 'Backfilled from campaigns that previously sent to all subscribed contacts.',
                    'source' => 'legacy_campaign',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('marketing_campaigns')
                    ->where('client_id', $clientId)
                    ->when($tag, fn ($query) => $query->where('recipient_tag', $tag), fn ($query) => $query->whereNull('recipient_tag'))
                    ->pluck('id')
                    ->each(function (int $campaignId) use ($audienceId, $now): void {
                        DB::table('marketing_audience_campaign')->insertOrIgnore([
                            'marketing_audience_id' => $audienceId,
                            'marketing_campaign_id' => $campaignId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    });

                DB::table('marketing_contacts')
                    ->where('client_id', $clientId)
                    ->where('status', 'subscribed')
                    ->orderBy('id')
                    ->get(['id', 'tags'])
                    ->filter(function (object $contact) use ($tag): bool {
                        if ($tag === null) {
                            return true;
                        }

                        $tags = json_decode((string) $contact->tags, true);

                        return is_array($tags) && in_array($tag, array_map('strval', $tags), true);
                    })
                    ->each(function (object $contact) use ($audienceId, $now): void {
                        DB::table('marketing_audience_contact')->insertOrIgnore([
                            'marketing_audience_id' => $audienceId,
                            'marketing_contact_id' => (int) $contact->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    });
            });
    }

    private function uniqueAudienceName(int $clientId, string $name): string
    {
        $candidate = Str::limit($name, 240, '');
        $suffix = 2;

        while (DB::table('marketing_audiences')->where('client_id', $clientId)->where('name', $candidate)->exists()) {
            $candidate = Str::limit($name, 230, '').' '.$suffix;
            $suffix++;
        }

        return $candidate;
    }
};
