<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'api';

        // Create roles (idempotent)
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'expert', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'store_owner', 'guard_name' => $guard]);

        // Create permissions (idempotent)
        $permissions = [
            'plants.create', 'plants.view', 'plants.update', 'plants.delete',
            'diseases.view', 'diseases.create', 'diseases.update', 'diseases.delete',
            'diagnoses.create', 'diagnoses.view', 'diagnoses.review', 'diagnoses.verify',
            'users.view', 'users.manage',
            'orders.create', 'orders.view', 'orders.update', 'orders.delete',
            'stores.manage', 'stores.view',
            'products.create', 'products.view', 'products.update', 'products.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
        }

        // Assign permissions to roles (idempotent)
        $rolePermissions = [
            'super_admin' => $permissions,
            'expert' => ['plants.view', 'diseases.view', 'diagnoses.view', 'diagnoses.review', 'diagnoses.verify'],
            'user' => ['plants.create', 'plants.view', 'plants.update', 'plants.delete', 'diseases.view', 'diagnoses.create', 'diagnoses.view'],
            'store_owner' => ['orders.create', 'orders.view', 'orders.update', 'orders.delete', 'stores.manage', 'stores.view', 'products.create', 'products.view', 'products.update', 'products.delete', 'plants.view', 'diseases.view'],
        ];

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::where('name', $roleName)->first();
            $permissionIds = Permission::whereIn('name', $rolePerms)->pluck('id');

            foreach ($permissionIds as $permId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }
}
