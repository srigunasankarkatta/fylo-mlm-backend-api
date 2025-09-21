<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserAuthController extends Controller
{
    use ApiResponse;

    /**
     * Register new user (public).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:30|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'referral_code' => 'nullable|string|max:20',
        ]);

        // Use DB transaction for safety
        DB::beginTransaction();
        try {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => $payload['name'],
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'password' => Hash::make($payload['password']),
                'referral_code' => strtoupper(uniqid('U')),
                'referred_by' => null, // set below if referral_code provided
                'status' => 'active',
            ]);

            // If referral_code provided, link referred_by (will check existence)
            if (!empty($payload['referral_code'])) {
                $sponsor = User::where('referral_code', $payload['referral_code'])->first();
                if ($sponsor) {
                    $user->referred_by = $sponsor->id;
                    $user->save();
                }
            }

            // Ensure 'user' role exists and assign
            $role = Role::firstOrCreate(['name' => 'user']);
            $user->assignRole($role);

            DB::commit();

            // Optionally auto-login after registration: generate token
            $token = JWTAuth::fromUser($user);

            return $this->success([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
            ], 'Registration successful', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Login a user and return JWT token
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'password' => 'required|string|min:6'
        ]);

        // Accept email OR phone for login
        $identifier = null;
        if (!empty($credentials['email'])) {
            $identifier = ['email' => $credentials['email']];
        } elseif (!empty($credentials['phone'])) {
            $identifier = ['phone' => $credentials['phone']];
        } else {
            return $this->validationError(['login' => ['Either email or phone is required']]);
        }

        $attempt = array_merge($identifier, ['password' => $credentials['password']]);

        if (!$token = JWTAuth::attempt($attempt)) {
            return $this->unauthorized('Invalid credentials');
        }

        $user = JWTAuth::user();

        // Protect: ensure not admin (if you want non-admin only)
        if ($user->hasRole('admin')) {
            // logout to invalidate token immediately
            JWTAuth::invalidate($token);
            return $this->forbidden('Unauthorized: admin should use admin login');
        }

        // Optional: record session / jti in jwt_revocations table for revocation support
        $this->storeActiveSession($token, $request, $user);

        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => $user
        ], 'Login successful');
    }

    /**
     * Logout: revoke current token
     */
    public function logout(Request $request)
    {
        try {
            // Store jti to revocation table (so token is considered revoked)
            $this->revokeCurrentJwt($request->bearerToken());

            JWTAuth::invalidate($request->bearerToken());

            // Optionally remove session entry (if using sessions table)
            $this->removeActiveSession($request->bearerToken());

            return $this->success(null, 'Logged out successfully');
        } catch (\Throwable $e) {
            return $this->error('Logout failed: ' . $e->getMessage());
        }
    }

    /**
     * Return logged in user
     */
    public function me(Request $request)
    {
        return $this->success(JWTAuth::user(), 'User profile');
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        try {
            $newToken = JWTAuth::refresh($request->bearerToken());

            // Replace session jti mapping if you're storing sessions
            $this->replaceActiveSession($request->bearerToken(), $newToken, $request);

            return $this->success([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
            ], 'Token refreshed');
        } catch (\Throwable $e) {
            return $this->unauthorized('Token refresh failed');
        }
    }

    // --------- Helper methods for session/jwt handling ----------

    /**
     * If you want to track sessions/devices, store JWT jti + user + device info.
     * This implementation reads jti from the token (if present) and stores in jwt_revocations table with revoked_at = null (active).
     */
    protected function storeActiveSession(string $token, Request $request, User $user)
    {
        try {
            // Decode token to get 'jti'
            $decoded = JWTAuth::setToken($token)->getPayload();
            $jti = $decoded->get('jti') ?? null;

            if ($jti) {
                DB::table('jwt_revocations')->insert([
                    'user_id' => $user->id,
                    'jti' => $jti,
                    'device_fingerprint' => $request->header('X-Device-Fingerprint') ?? null,
                    'revoked_at' => null,
                    'expires_at' => now()->addSeconds(JWTAuth::factory()->getTTL() * 60),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        } catch (\Throwable $e) {
            // swallow - session tracking is optional
        }
    }

    protected function revokeCurrentJwt(?string $bearerToken)
    {
        if (!$bearerToken) return;

        try {
            $payload = JWTAuth::setToken($bearerToken)->getPayload();
            $jti = $payload->get('jti') ?? null;
            if ($jti) {
                DB::table('jwt_revocations')
                    ->where('jti', $jti)
                    ->update(['revoked_at' => now()]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function removeActiveSession(?string $bearerToken)
    {
        if (!$bearerToken) return;
        try {
            $payload = JWTAuth::setToken($bearerToken)->getPayload();
            $jti = $payload->get('jti') ?? null;
            if ($jti) {
                DB::table('jwt_revocations')->where('jti', $jti)->delete();
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * When refreshing, replace session record jti (best-effort).
     */
    protected function replaceActiveSession(?string $oldToken, string $newToken, Request $request)
    {
        try {
            if (!$oldToken) return;
            $old = JWTAuth::setToken($oldToken)->getPayload();
            $oldJti = $old->get('jti') ?? null;

            $newPayload = JWTAuth::setToken($newToken)->getPayload();
            $newJti = $newPayload->get('jti') ?? null;

            if ($oldJti && $newJti) {
                DB::table('jwt_revocations')->where('jti', $oldJti)->update(['jti' => $newJti, 'updated_at' => now(), 'expires_at' => now()->addSeconds(JWTAuth::factory()->getTTL() * 60)]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
