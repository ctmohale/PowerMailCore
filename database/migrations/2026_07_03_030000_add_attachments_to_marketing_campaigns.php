<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->json('attachments')->nullable()->after('template_data');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table): void {
            $table->dropColumn('attachments');
        });
    }
};
