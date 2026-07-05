<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_lead_generation_runs', function (Blueprint $table): void {
            $table->longText('source_data')->nullable()->after('source_urls');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_lead_generation_runs', function (Blueprint $table): void {
            $table->dropColumn('source_data');
        });
    }
};
