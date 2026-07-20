<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_emails', function (Blueprint $table): void {
            $table->dropUnique(['email_account_id', 'uid']);
            $table->string('mailbox')->default('INBOX')->after('email_account_id');
            $table->string('mailbox_type', 30)->default('inbox')->after('mailbox');
            $table->unique(['email_account_id', 'mailbox', 'uid']);
            $table->index(['email_account_id', 'mailbox_type']);
        });
    }

    public function down(): void
    {
        Schema::table('received_emails', function (Blueprint $table): void {
            $table->dropIndex(['email_account_id', 'mailbox_type']);
            $table->dropUnique(['email_account_id', 'mailbox', 'uid']);
            $table->dropColumn(['mailbox', 'mailbox_type']);
            $table->unique(['email_account_id', 'uid']);
        });
    }
};
