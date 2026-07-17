<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole('user');

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    public function login(array $credentials): ?array
    {
        if (!$token = JWTAuth::attempt($credentials)) {
            return null;
        }

        $user = JWTAuth::user()->load('roles');

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(): void
    {
        JWTAuth::invalidate(JWTAuth::getToken());
    }

    public function refresh(): array
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());

        return [
            'token' => $token,
        ];
    }

    public function me(): User
    {
        return JWTAuth::user()->load('roles');
    }

    public function updateProfile(array $data): User
    {
        $user = JWTAuth::user();
        $user->update($data);
        return $user->load('roles');
    }
}
