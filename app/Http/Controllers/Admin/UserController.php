<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PlacementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends ApiController
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('referral_code', 'like', "%{$q}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->role($request->role);
        }

        // Filter by package
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Date range filtering
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        // Include relationships
        $query->with(['roles', 'permissions']);

        // Order by
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $users = $query->paginate($request->get('per_page', 20));

        return $this->paginated($users, 'Users retrieved successfully');
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::withTrashed()
            ->with(['roles', 'permissions'])
            ->find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        return $this->success($user, 'User retrieved successfully');
    }

    /**
     * Store a newly created user (Admin API with same logic as user registration).
     */
    public function store(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:30|unique:users,phone',
            'password' => 'required|string|min:6',
            'referral_code' => 'nullable|string|max:20',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'banned', 'deleted'])],
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        // Use DB transaction for safety (same as user registration)
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
                'status' => $payload['status'] ?? 'active',
            ]);

            // If referral_code provided, validate referrer and place in tree (same logic as registration)
            if (!empty($payload['referral_code'])) {
                $sponsor = User::where('referral_code', $payload['referral_code'])->first();
                if ($sponsor) {
                    // Business validation: Referrer must have an active package (skip for admin users)
                    $isAdmin = $sponsor->hasRole('admin');

                    if (!$isAdmin) {
                        $hasActivePackage = $sponsor->userPackages()
                            ->where('payment_status', 'completed')
                            ->exists();

                        if (!$hasActivePackage) {
                            DB::rollBack();
                            return $this->error('Referrer must have an active package before you can register under them.', 422);
                        }
                    }

                    $user->referred_by = $sponsor->id;
                    $user->save();

                    // Place user in tree under their referrer (same as registration)
                    app(PlacementService::class)->placeUserInTree($user, $sponsor);
                } else {
                    DB::rollBack();
                    return $this->error('Invalid referral code.', 422);
                }
            } else {
                // If no referral code, place as root (first user in system) - same as registration
                app(PlacementService::class)->placeUserInTree($user, $user);
            }

            // Initialize wallets for the new user (same as registration)
            $this->initializeUserWallets($user);

            // Assign roles if provided, otherwise assign 'user' role (same as registration)
            if ($request->filled('roles')) {
                $user->assignRole($request->roles);
            } else {
                // Ensure 'user' role exists and assign (same as registration)
                $role = Role::firstOrCreate(['name' => 'user']);
                $user->assignRole($role);
            }

            DB::commit();

            return $this->success([
                'user' => $user->load(['roles', 'permissions']),
                'message' => 'User created successfully with same logic as registration'
            ], 'User created successfully', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('User creation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'banned', 'deleted'])],
            'role_hint' => 'nullable|string|max:50',
            'package_id' => 'nullable|exists:packages,id',
            'parent_id' => 'nullable|exists:users,id',
            'position' => 'nullable|integer|min:1|max:4',
            'referred_by' => 'nullable|exists:users,id',
            'metadata' => 'nullable|array',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // Update roles if provided
        if ($request->filled('roles')) {
            $user->syncRoles($request->roles);
        }

        return $this->success($user->load(['roles', 'permissions']), 'User updated successfully');
    }

    /**
     * Soft delete the specified user.
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $user->delete();

        return $this->success(null, 'User soft-deleted successfully');
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore($id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        if (!$user->trashed()) {
            return $this->error('User is not deleted', 400);
        }

        $user->restore();

        return $this->success($user->load(['roles', 'permissions']), 'User restored successfully');
    }

    /**
     * Permanently delete the specified user.
     */
    public function forceDelete($id)
    {
        $user = User::withTrashed()->find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $user->forceDelete();

        return $this->success(null, 'User permanently deleted');
    }

    /**
     * Get user's MLM tree structure.
     */
    public function tree($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $treeData = [
            'user' => $user,
            'direct_referrals' => [], // TODO: Implement when tree relationships are added
            'tree_ancestors' => [], // TODO: Implement when tree relationships are added
            'tree_descendants' => [], // TODO: Implement when tree relationships are added
            'tree_stats' => $user->getClubMatrixStats(),
        ];

        return $this->success($treeData, 'User tree structure retrieved');
    }

    /**
     * Get user's financial summary.
     */
    public function financialSummary($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $financialData = [
            'user' => $user,
            'wallet_balances' => [
                'total_balance' => $user->getTotalWalletBalance(),
                'total_pending' => $user->getTotalPendingBalance(),
                'wallets' => $user->wallets,
            ],
            'payout_summary' => $user->getPayoutStats(),
            'income_summary' => $user->getIncomeStatsFromRecords(),
            'ledger_summary' => [
                'total_income' => $user->getTotalLedgerIncome(),
                'total_expenses' => $user->getTotalLedgerExpenses(),
                'net_balance' => $user->getNetLedgerBalance(),
            ],
        ];

        return $this->success($financialData, 'User financial summary retrieved');
    }

    /**
     * Get user's package history.
     */
    public function packageHistory($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->notFound('User not found');
        }

        $packageHistory = [
            'user' => $user,
            'current_package' => null, // TODO: Implement when package relationship is added
            'package_history' => $user->userPackages()->get(),
            'total_spent' => 0, // TODO: Implement when package spending calculation is added
        ];

        return $this->success($packageHistory, 'User package history retrieved');
    }

    /**
     * Initialize wallets for a new user (same as user registration).
     */
    protected function initializeUserWallets(User $user): void
    {
        $walletTypes = ['commission', 'fasttrack', 'autopool', 'club', 'main'];

        foreach ($walletTypes as $walletType) {
            Wallet::getOrCreate($user->id, $walletType, 'USD');
        }
    }
}
