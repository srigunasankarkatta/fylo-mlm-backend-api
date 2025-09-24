<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

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
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:30|unique:users,phone',
            'password' => 'required|string|min:6',
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'banned', 'deleted'])],
            'role_hint' => 'nullable|string|max:50',
            'package_id' => 'nullable|exists:packages,id',
            'parent_id' => 'nullable|exists:users,id',
            'position' => 'nullable|integer|min:1|max:4',
            'referred_by' => 'nullable|exists:users,id',
            'metadata' => 'nullable|array',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Assign roles if provided
        if ($request->filled('roles')) {
            $user->assignRole($request->roles);
        }

        return $this->success($user->load(['roles', 'permissions']), 'User created successfully', 201);
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
}
