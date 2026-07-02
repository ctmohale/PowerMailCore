<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_logs', 'marketing_contact_id')) {
                $table->foreignId('marketing_contact_id')
                    ->nullable()
                    ->after('email_template_id')
                    ->constrained('marketing_contacts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('email_logs', 'marketing_contact_id')) {
                $table->dropConstrainedForeignId('marketing_contact_id');
            }
        });
    }
};
