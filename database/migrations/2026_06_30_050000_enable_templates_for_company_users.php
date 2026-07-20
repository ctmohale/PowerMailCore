<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'client_user')
            ->orderBy('id')
            ->get(['id', 'permissions'])
            ->each(function (object $user): void {
                $permissions = json_decode((string) $user->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];
                $permissions['manage_templates'] = true;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['permissions' => json_encode($permissions)]);
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'client_user')
            ->orderBy('id')
            ->get(['id', 'permissions'])
            ->each(function (object $user): void {
                $permissions = json_decode((string) $user->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];
                $permissions['manage_templates'] = false;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['permissions' => json_encode($permissions)]);
            });
    }
};
