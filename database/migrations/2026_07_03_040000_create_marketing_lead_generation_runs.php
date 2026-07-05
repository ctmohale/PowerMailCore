<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lead_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt');
            $table->string('industry')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('target_count')->default(25);
            $table->json('keywords')->nullable();
            $table->json('source_urls')->nullable();
            $table->boolean('use_openai')->default(true);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('discovered_count')->default(0);
            $table->unsignedSmallInteger('imported_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('raw_results')->nullable();
            $table->json('leads')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_lead_generation_runs');
    }
};
