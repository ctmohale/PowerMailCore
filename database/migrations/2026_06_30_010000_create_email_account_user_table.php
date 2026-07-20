<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'email_account_id']);
        });

        $now = now();
        $rows = [];

        $users = DB::table('users')
            ->select(['id', 'client_id'])
            ->where('role', 'client_user')
            ->whereNotNull('client_id')
            ->get();

        foreach ($users as $user) {
            $accountIds = DB::table('email_accounts')
                ->where('client_id', $user->client_id)
                ->pluck('id');

            foreach ($accountIds as $accountId) {
                $rows[] = [
                    'user_id' => $user->id,
                    'email_account_id' => $accountId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('email_account_user')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_account_user');
    }
};
