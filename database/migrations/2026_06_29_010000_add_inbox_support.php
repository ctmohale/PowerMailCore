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
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->boolean('inbox_enabled')->default(false)->after('is_active');
            $table->string('imap_host')->nullable()->after('inbox_enabled');
            $table->unsignedInteger('imap_port')->default(993)->after('imap_host');
            $table->string('imap_encryption', 20)->default('ssl')->after('imap_port');
            $table->string('imap_username')->nullable()->after('imap_encryption');
            $table->text('imap_password')->nullable()->after('imap_username');
            $table->unsignedBigInteger('last_inbound_uid')->nullable()->after('imap_password');
            $table->timestamp('inbox_last_synced_at')->nullable()->after('last_inbound_uid');
        });

        Schema::create('received_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('uid');
            $table->string('message_id')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('raw_headers')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->boolean('seen')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['email_account_id', 'uid']);
            $table->index(['client_id', 'received_at']);
            $table->index(['from_email', 'subject']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('received_emails');

        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'inbox_enabled',
                'imap_host',
                'imap_port',
                'imap_encryption',
                'imap_username',
                'imap_password',
                'last_inbound_uid',
                'inbox_last_synced_at',
            ]);
        });
    }
};
