<?php

namespace App\Providers;

use App\Models\ReceivedEmail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();
            $unopenedNotificationCount = 0;

            if ($user instanceof User && $user->canAccess(User::PERMISSION_VIEW_INBOX)) {
                $unopenedNotificationCount = Cache::remember(
                    'layout.unopened_count.'.$user->id,
                    now()->addSeconds(15),
                    function () use ($user): int {
                        $query = ReceivedEmail::query()->whereNull('opened_at');

                        if (! $user->isAdmin()) {
                            $assignedAccountIds = $user->emailAccounts()
                                ->pluck('email_accounts.id')
                                ->map(fn ($id): int => (int) $id)
                                ->all();

                            $query
                                ->where('client_id', $user->client_id)
                                ->whereIn('email_account_id', $assignedAccountIds);
                        }

                        return $query->count();
                    },
                );
            }

            $view->with('unopenedNotificationCount', $unopenedNotificationCount);
        });
    }
}
