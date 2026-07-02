<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role', 30)->default('client_user')->after('email');
            $table->string('status', 30)->default('active')->after('role')->index();
            $table->json('permissions')->nullable()->after('status');
            $table->timestamp('last_access_at')->nullable()->after('permissions');
        });

        DB::table('users')->whereNull('client_id')->update([
            'role' => 'admin',
            'status' => 'active',
            'permissions' => json_encode([
                'send_emails' => true,
                'view_inbox' => true,
                'view_logs' => true,
                'manage_templates' => true,
                'manage_accounts' => true,
                'manage_marketing' => true,
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['role', 'status', 'permissions', 'last_access_at']);
        });
    }
};
