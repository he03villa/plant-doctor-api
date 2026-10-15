<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuthPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $data = []): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::factory()->create($data);
    }

    public function test_forgot_password_sends_notification_and_returns_generic_message(): void
    {
        Notification::fake();

        $user = $this->createUser(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('message', 'If that email address is in our system, we have sent a password reset link');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_with_nonexistent_email_returns_same_generic_message(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'notfound@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('message', 'If that email address is in our system, we have sent a password reset link');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_accepts_platform_web_and_mobile(): void
    {
        Notification::fake();

        $user = $this->createUser(['email' => 'platform@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'platform@example.com',
            'platform' => 'mobile',
        ]);

        $response->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains($mail->viewData['url'], 'plantdoctor://reset-password')
                && $mail->viewData['showWebFallback'] === true
                && str_contains($mail->viewData['webUrl'], '/reset-password');
        });
    }

    public function test_reset_password_with_valid_token_changes_password_and_deletes_token(): void
    {
        $user = $this->createUser(['email' => 'reset@example.com', 'password' => Hash::make('oldpassword')]);

        $token = Password::broker()->createToken($user);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@example.com',
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertFalse(Hash::check('oldpassword', $user->password));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset@example.com',
        ]);
    }

    public function test_reset_password_with_invalid_token_returns_400(): void
    {
        $user = $this->createUser(['email' => 'badtoken@example.com', 'password' => Hash::make('oldpassword')]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'invalid-token-here',
            'email' => 'badtoken@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('oldpassword', $user->password));
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_with_wrong_current_password_returns_422(): void
    {
        $user = $this->createUser(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/auth/change-password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_success_returns_new_token_and_invalidates_old_one(): void
    {
        $user = $this->createUser(['email' => 'change@example.com', 'password' => Hash::make('oldpassword123')]);

        $oldToken = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'oldpassword123',
                'password' => 'newpassword456',
                'password_confirmation' => 'newpassword456',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                ],
            ]);

        $newToken = $response->json('data.token');
        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken, $newToken);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword456', $user->password));
        $this->assertFalse(Hash::check('oldpassword123', $user->password));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'change@example.com',
        ]);

        $this->app['auth']->forgetGuards();

        $responseWithOldToken = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->getJson('/api/auth/me');

        $responseWithOldToken->assertStatus(401);
    }
}
