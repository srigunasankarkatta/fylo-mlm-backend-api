<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User;

class AuthController extends ApiController
{

    /**
     * POST /api/admin/login
     * Admin login with JWT authentication
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->unauthorized('Invalid credentials');
            }

            $user = JWTAuth::user();
        } catch (JWTException $e) {
            return $this->error('Could not create token', 500);
        }

        // Check if user has admin role (either 'admin' or 'super-admin')
        if (!$user->hasRole(['admin', 'super-admin'])) {
            return $this->forbidden('Unauthorized: not an admin', [
                'user_roles' => $user->getRoleNames()->toArray()
            ]);
        }

        // Update last login information
        $user->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
        ]);

        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role_hint' => $user->role_hint,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'last_login_at' => $user->last_login_at,
            ]
        ], 'Login successful');
    }

    /**
     * POST /api/admin/logout
     * Admin logout
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->success(null, 'Logged out successfully');
        } catch (JWTException $e) {
            return $this->error('Could not invalidate token', 500);
        }
    }

    /**
     * GET /api/admin/me
     * Get current authenticated admin user
     */
    public function me()
    {
        $user = JWTAuth::user();

        return $this->success([
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role_hint' => $user->role_hint,
            'status' => $user->status,
            'metadata' => $user->metadata,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ], 'User profile retrieved');
    }

    /**
     * POST /api/admin/refresh
     * Refresh JWT token
     */
    public function refresh()
    {
        try {
            return $this->success([
                'access_token' => JWTAuth::refresh(JWTAuth::getToken()),
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
            ], 'Token refreshed successfully');
        } catch (JWTException $e) {
            return $this->error('Could not refresh token', 500);
        }
    }

    /**
     * GET /api/admin/verify
     * Verify JWT token validity
     */
    public function verify()
    {
        return $this->success([
            'user' => JWTAuth::user()
        ], 'Token is valid');
    }
}
