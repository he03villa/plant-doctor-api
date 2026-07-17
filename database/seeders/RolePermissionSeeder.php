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

        // Create roles
        Role::create(['name' => 'admin', 'guard_name' => $guard]);
        Role::create(['name' => 'expert', 'guard_name' => $guard]);
        Role::create(['name' => 'user', 'guard_name' => $guard]);

        // Create permissions
        $permissions = [
            'plants.create', 'plants.view', 'plants.update', 'plants.delete',
            'diseases.view', 'diseases.create', 'diseases.update', 'diseases.delete',
            'diagnoses.create', 'diagnoses.view', 'diagnoses.review', 'diagnoses.verify',
            'users.view', 'users.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => $guard]);
        }

        // Assign permissions to roles via raw DB inserts
        $rolePermissions = [
            'admin' => $permissions,
            'expert' => ['plants.view', 'diseases.view', 'diagnoses.view', 'diagnoses.review', 'diagnoses.verify'],
            'user' => ['plants.create', 'plants.view', 'plants.update', 'plants.delete', 'diseases.view', 'diagnoses.create', 'diagnoses.view'],
        ];

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::where('name', $roleName)->first();
            $permissionIds = Permission::whereIn('name', $rolePerms)->pluck('id', 'name');

            foreach ($permissionIds as $permId) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }
}
