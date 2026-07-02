<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        abort_unless($user && collect($abilities)->contains(fn (string $ability): bool => $user->canAccess($ability)), 403);

        return $next($request);
    }
}
