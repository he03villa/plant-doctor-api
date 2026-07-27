<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function list(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function listPermissions(): Collection
    {
        return Permission::all();
    }

    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'api',
        ]);

        if (! empty($data['permissions'])) {
            $permissions = array_map(
                fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']),
                $data['permissions']
            );
            $role->syncPermissions($permissions);
        }

        return $role->load('permissions');
    }

    public function getById(int $id): Role
    {
        return Role::with('permissions')->findOrFail($id);
    }

    public function update(int $id, array $data): Role
    {
        $role = Role::findOrFail($id);

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data)) {
            $permissions = array_map(
                fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']),
                $data['permissions'] ?? []
            );
            $role->syncPermissions($permissions);
        }

        return $role->load('permissions');
    }

    public function delete(int $id): bool
    {
        $role = Role::findOrFail($id);

        return $role->delete();
    }

    public function assignToUser(int $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);
        $user->assignRole($roleName);

        return $user->load('roles');
    }

    public function removeFromUser(int $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);
        $user->removeRole($roleName);

        return $user->load('roles');
    }
}
