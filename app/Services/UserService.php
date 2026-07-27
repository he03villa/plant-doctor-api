<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function list(?string $search = null, ?string $role = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('roles')->search($search);

        if ($role) {
            $query->role($role);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getById(int $id): User
    {
        return User::with(['roles', 'permissions'])->findOrFail($id);
    }

    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        return $user->load('roles');
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user->load('roles');
    }

    public function delete(int $id): bool
    {
        $user = User::findOrFail($id);

        return $user->delete();
    }

    public function toggleActive(int $id): User
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);

        return $user->load('roles');
    }
}
