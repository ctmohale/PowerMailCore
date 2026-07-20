<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_emails', function (Blueprint $table): void {
            $table->dateTime('opened_at')->nullable()->default(null)->after('seen');
            $table->index(['email_account_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::table('received_emails', function (Blueprint $table): void {
            $table->dropIndex(['email_account_id', 'opened_at']);
            $table->dropColumn('opened_at');
        });
    }
};
