<?php

namespace App\Http\Middleware;

use Closure;
use App\Traits\ApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

class JwtRevocationCheck
{
    use \App\Traits\ApiResponse;

    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        if ($token) {
            try {
                $payload = JWTAuth::setToken($token)->getPayload();
                $jti = $payload->get('jti');
                $rev = DB::table('jwt_revocations')->where('jti', $jti)->first();
                if ($rev && $rev->revoked_at) {
                    return response()->json(['status' => 'error', 'message' => 'Token revoked'], 401);
                }
            } catch (\Throwable $e) {
                // ignore; auth middleware will handle invalid tokens
            }
        }
        return $next($request);
    }
}
