<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_lead_generation_runs', function (Blueprint $table): void {
            $table->string('province', 120)->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_lead_generation_runs', function (Blueprint $table): void {
            $table->dropColumn('province');
        });
    }
};
