<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

        $role = $data['role'] ?? 'user';
        $user->assignRole($role);

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function login(array $credentials): ?array
    {
        if (! $token = JWTAuth::attempt($credentials)) {
            return null;
        }

        $user = JWTAuth::user();

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
        return Auth::user();
    }

    public function meRolesAndPermissions(): User
    {
        return Auth::user()->load(['roles', 'permissions']);
    }

    public function updateProfile(array $data): User
    {
        $user = Auth::user();
        $user->update($data);

        return $user;
    }

    public function forgotPassword(array $data): void
    {
        $platform = $data['platform'] ?? 'web';

        Password::broker()->sendResetLink(
            ['email' => $data['email']],
            function ($user, $token) use ($platform) {
                $user->notify(new ResetPasswordNotification($token, $platform));
            }
        );
    }

    public function resetPassword(array $data): string
    {
        return Password::broker()->reset($data, function ($user, $password) {
            $user->update(['password' => $password]);
        });
    }

    public function changePassword(array $data): array
    {
        $user = Auth::user();

        $user->update(['password' => $data['password']]);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        JWTAuth::invalidate(JWTAuth::getToken());

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
