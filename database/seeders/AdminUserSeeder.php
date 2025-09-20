<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create admin role if it doesn't exist
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Create super admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        // Create all permissions if they don't exist
        $permissions = [
            'manage users',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'manage permissions',
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',
            'manage mlm structure',
            'view mlm structure',
            'manage commissions',
            'view commissions',
            'manage reports',
            'view reports',
            'manage packages',
            'view packages',
            'create packages',
            'edit packages',
            'delete packages',
            'manage wallets',
            'view wallets',
            'manage payouts',
            'view payouts',
            'process payouts',
            'manage income configs',
            'view income configs',
            'create income configs',
            'edit income configs',
            'delete income configs',
            'manage audit logs',
            'view audit logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all permissions to super admin role
        $superAdminRole->syncPermissions(Permission::all());

        // Assign basic admin permissions to admin role
        $adminRole->syncPermissions([
            'view users',
            'create users',
            'edit users',
            'view roles',
            'view permissions',
            'view mlm structure',
            'view commissions',
            'view reports',
            'view packages',
            'view wallets',
            'view payouts',
            'view income configs',
            'view audit logs',
        ]);

        // Create Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'uuid' => \Illuminate\Support\Str::uuid(),
                'name' => 'Super Admin',
                'password' => Hash::make('password123'), // Change this in production
                'referral_code' => strtoupper(uniqid('ADM')),
                'status' => 'active',
                'role_hint' => 'super-admin',
                'metadata' => [
                    'is_super_admin' => true,
                    'created_by' => 'system',
                    'notes' => 'Initial super admin user created by seeder'
                ],
            ]
        );

        // Assign super admin role
        if (!$superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole($superAdminRole);
        }

        // Create regular Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin2@example.com'],
            [
                'uuid' => \Illuminate\Support\Str::uuid(),
                'name' => 'Admin User',
                'password' => Hash::make('password123'), // Change this in production
                'referral_code' => strtoupper(uniqid('ADM')),
                'status' => 'active',
                'role_hint' => 'admin',
                'metadata' => [
                    'is_admin' => true,
                    'created_by' => 'system',
                    'notes' => 'Regular admin user created by seeder'
                ],
            ]
        );

        // Assign admin role
        if (!$admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        $this->command->info('Admin users created successfully!');
        $this->command->info('Super Admin: admin@example.com / password123');
        $this->command->info('Admin: admin2@example.com / password123');
        $this->command->warn('Please change the default passwords in production!');
    }
}
