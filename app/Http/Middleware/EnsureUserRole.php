<?php

namespace App\Http\Middleware;

use Closure;
use App\Traits\ApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureUserRole
{
    use \App\Traits\ApiResponse;

    public function handle($request, Closure $next)
    {
        $user = JWTAuth::user();
        if (!$user) {
            return $this->unauthorized('Unauthenticated');
        }

        // require user role OR explicitly forbid admin
        if ($user->hasRole('admin')) {
            return $this->forbidden('Admin cannot access user routes');
        }

        // optionally enforce user role:
        // if (!$user->hasRole('user')) return $this->forbidden('Not a user');

        return $next($request);
    }
}
