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
        Schema::table('email_logs', function (Blueprint $table): void {
            $table->index(['client_id', 'created_at'], 'email_logs_client_created_idx');
            $table->index(['email_account_id', 'status', 'created_at'], 'email_logs_account_status_created_idx');
            $table->index(['marketing_contact_id', 'created_at'], 'email_logs_contact_created_idx');
        });

        Schema::table('received_emails', function (Blueprint $table): void {
            $table->index(['client_id', 'mailbox_type', 'received_at'], 'received_client_mailbox_received_idx');
            $table->index(['email_account_id', 'mailbox_type', 'received_at'], 'received_account_mailbox_received_idx');
            $table->index(['client_id', 'opened_at'], 'received_client_opened_idx');
        });

        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->index(['client_id', 'created_at'], 'campaigns_client_created_idx');
        });

        Schema::table('marketing_campaign_recipients', function (Blueprint $table): void {
            $table->index(['email_log_id', 'status'], 'campaign_recipients_log_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaign_recipients', function (Blueprint $table): void {
            $table->dropIndex('campaign_recipients_log_status_idx');
        });

        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->dropIndex('campaigns_client_created_idx');
        });

        Schema::table('received_emails', function (Blueprint $table): void {
            $table->dropIndex('received_client_opened_idx');
            $table->dropIndex('received_account_mailbox_received_idx');
            $table->dropIndex('received_client_mailbox_received_idx');
        });

        Schema::table('email_logs', function (Blueprint $table): void {
            $table->dropIndex('email_logs_contact_created_idx');
            $table->dropIndex('email_logs_account_status_created_idx');
            $table->dropIndex('email_logs_client_created_idx');
        });
    }
};
