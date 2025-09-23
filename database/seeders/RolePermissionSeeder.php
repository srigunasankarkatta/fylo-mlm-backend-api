<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Permissions list - expand as needed
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Packages
            'packages.view',
            'packages.create',
            'packages.update',
            'packages.delete',

            // Income config
            'income_configs.view',
            'income_configs.create',
            'income_configs.update',
            'income_configs.delete',

            // Wallets & ledger
            'wallets.view',
            'ledger.view',
            'wallets.adjust',

            // Payouts
            'payouts.view',
            'payouts.process',

            // AutoPool & Club
            'autopool.manage',
            'club.manage',

            // System settings
            'system_settings.view',
            'system_settings.update',

            // Reports & dashboard
            'reports.view',

            // Admin utilities
            'audit_logs.view'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Admin gets everything
        $adminRole->syncPermissions(Permission::all());

        // User role gets limited permissions (expand as business requires)
        $userPerms = [
            'packages.view',
            'wallets.view',
            'ledger.view',
            'reports.view'
        ];
        $userRole->syncPermissions($userPerms);

        // Create a Super Admin user (if not exists)
        $adminEmail = 'admin@example.com';
        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Super Admin',
                'password' => Hash::make('password123'), // change immediately
                'referral_code' => strtoupper('ADM' . substr(Str::random(6), 0, 6)),
                'status' => 'active'
            ]
        );

        if (!$admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }
    }
}
