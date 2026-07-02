<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_emails', function (Blueprint $table): void {
            if (! Schema::hasColumn('received_emails', 'email_log_id')) {
                $table->foreignId('email_log_id')
                    ->nullable()
                    ->after('email_account_id')
                    ->constrained('email_logs')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('received_emails', 'source')) {
                $table->string('source', 30)->default('imap')->after('mailbox_type');
            }

            $table->unique('email_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('received_emails', function (Blueprint $table): void {
            $table->dropUnique(['email_log_id']);

            if (Schema::hasColumn('received_emails', 'email_log_id')) {
                $table->dropConstrainedForeignId('email_log_id');
            }

            if (Schema::hasColumn('received_emails', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
