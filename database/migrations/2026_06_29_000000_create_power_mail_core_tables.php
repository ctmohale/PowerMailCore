<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['client_id', 'domain']);
        });

        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->string('from_name')->nullable();
            $table->string('smtp_host');
            $table->unsignedInteger('smtp_port')->default(587);
            $table->string('smtp_encryption', 20)->default('starttls');
            $table->string('smtp_username');
            $table->text('smtp_password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'domain_id']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->longText('body_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'key']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 16)->index();
            $table->string('key_hash')->unique();
            $table->json('abilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_email');
            $table->string('to_email');
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_accounts');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('clients');
    }
};
