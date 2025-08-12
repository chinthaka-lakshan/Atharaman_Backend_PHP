<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        // Use the correct guard for API (Sanctum)
        $user = Auth::guard('sanctum')->user() ?? Auth::user();
        if (!$user || !in_array($user->role, $roles)) {
            abort(403, 'Unauthorized action.');
        }
        return $next($request);
    }
}
