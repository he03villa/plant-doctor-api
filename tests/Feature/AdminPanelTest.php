<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createPlan(array $attributes = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'price_monthly' => 9.99,
            'price_yearly' => 99.99,
            'features' => ['max_products' => -1],
            'is_active' => true,
            'display_order' => 1,
        ], $attributes));
    }

    private function createSubscription(Plan $plan, Store $store, string $status = 'active'): Subscription
    {
        return Subscription::create([
            'store_id' => $store->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_plain_user_cannot_access_admin_stores(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user, 'api')->getJson('/api/admin/stores')->assertForbidden();
    }

    public function test_expert_cannot_access_admin_stores(): void
    {
        $expert = $this->userWithRole('expert');

        $this->actingAs($expert, 'api')->getJson('/api/admin/stores')->assertForbidden();
    }

    public function test_expert_can_access_admin_diagnoses_review_queue(): void
    {
        $expert = $this->userWithRole('expert');

        $this->actingAs($expert, 'api')->getJson('/api/admin/diagnoses')->assertOk();
    }

    public function test_plain_user_cannot_access_admin_diagnoses_queue(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user, 'api')->getJson('/api/admin/diagnoses')->assertForbidden();
    }

    public function test_super_admin_can_list_stores(): void
    {
        Store::factory()->count(2)->create();
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->getJson('/api/admin/stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_active_false_filter_returns_only_inactive_stores(): void
    {
        Store::factory()->create(['is_active' => true]);
        Store::factory()->create(['is_active' => false]);
        $admin = $this->userWithRole('super_admin');

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/stores?active=false');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertFalse($response->json('data.0.is_active'));
    }

    public function test_store_search_combined_with_active_filter_does_not_leak_stores(): void
    {
        Store::factory()->create(['name' => 'Vivero Central Match', 'is_active' => false]);
        Store::factory()->create(['name' => 'Vivero Central Other', 'is_active' => true]);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->getJson('/api/admin/stores?search=Match&active=true')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_active_false_filter_returns_only_inactive_products(): void
    {
        StoreProduct::factory()->create(['is_active' => true]);
        StoreProduct::factory()->create(['is_active' => false]);
        $admin = $this->userWithRole('super_admin');

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/products?active=false');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertFalse($response->json('data.0.is_active'));
    }

    public function test_creating_plan_without_features_returns_validation_error(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/plans', [
                'name' => 'Gold',
                'slug' => 'gold',
                'price_monthly' => 19.99,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('features');
    }

    public function test_creating_plan_with_features_succeeds(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/plans', [
                'name' => 'Gold',
                'slug' => 'gold',
                'price_monthly' => 19.99,
                'features' => ['max_products' => 10],
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('plans', ['slug' => 'gold']);
    }

    public function test_cannot_delete_plan_with_subscriptions(): void
    {
        $plan = $this->createPlan();
        $store = Store::factory()->create();
        $this->createSubscription($plan, $store);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/admin/plans/'.$plan->id)
            ->assertStatus(400);

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_updating_subscription_with_invalid_status_returns_validation_error(): void
    {
        $plan = $this->createPlan();
        $store = Store::factory()->create();
        $subscription = $this->createSubscription($plan, $store);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->patchJson('/api/admin/subscriptions/'.$subscription->id, ['status' => 'bogus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_updating_subscription_status_to_cancelled_succeeds(): void
    {
        $plan = $this->createPlan();
        $store = Store::factory()->create();
        $subscription = $this->createSubscription($plan, $store);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->patchJson('/api/admin/subscriptions/'.$subscription->id, ['status' => 'cancelled'])
            ->assertOk();

        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_cannot_delete_store_with_subscription_history(): void
    {
        $plan = $this->createPlan();
        $store = Store::factory()->create();
        $this->createSubscription($plan, $store);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/admin/stores/'.$store->id)
            ->assertStatus(400);

        $this->assertDatabaseHas('stores', ['id' => $store->id]);
    }

    public function test_super_admin_stats_endpoint_returns_stats(): void
    {
        Store::factory()->create(['is_active' => true]);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_stores', 'active_stores', 'plan_distribution']]);
    }

    public function test_assigning_a_paid_plan_creates_active_subscription_and_marks_store_premium(): void
    {
        $plan = $this->createPlan();
        $store = Store::factory()->create(['is_premium' => false]);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/stores/'.$store->id.'/subscription', [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.id', $plan->id);

        $this->assertDatabaseHas('subscriptions', [
            'store_id' => $store->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'payment_method' => 'manual',
        ]);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_premium' => true]);
    }

    public function test_assigning_free_plan_clears_premium_and_has_no_payment(): void
    {
        $free = $this->createPlan(['name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'price_yearly' => 0]);
        $store = Store::factory()->create(['is_premium' => true]);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/stores/'.$store->id.'/subscription', [
                'plan_id' => $free->id,
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.last_payment_at', null);

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'is_premium' => false]);
    }

    public function test_assigning_a_trial_does_not_record_a_payment(): void
    {
        $plan = $this->createPlan();
        $store = Store::factory()->create();
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/stores/'.$store->id.'/subscription', [
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'trial_days' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.last_payment_at', null)
            ->assertJsonPath('data.last_payment_amount', null);

        $this->assertDatabaseHas('subscriptions', [
            'store_id' => $store->id,
            'status' => 'trialing',
            'last_payment_at' => null,
            'last_payment_amount' => null,
        ]);

        $this->assertNotNull(Subscription::where('store_id', $store->id)->first()->trial_ends_at);
    }

    public function test_assigning_a_plan_cancels_the_previous_active_subscription(): void
    {
        $oldPlan = $this->createPlan(['slug' => 'pro-old']);
        $newPlan = $this->createPlan(['slug' => 'pro-new']);
        $store = Store::factory()->create();
        $old = $this->createSubscription($oldPlan, $store);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/stores/'.$store->id.'/subscription', [
                'plan_id' => $newPlan->id,
            ])
            ->assertOk();

        $old->refresh();
        $this->assertSame('cancelled', $old->status);
        $this->assertNotNull($old->cancelled_at);

        $this->assertDatabaseHas('subscriptions', [
            'store_id' => $store->id,
            'plan_id' => $newPlan->id,
            'status' => 'active',
        ]);
    }

    public function test_assigning_subscription_requires_plan_id(): void
    {
        $store = Store::factory()->create();
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/stores/'.$store->id.'/subscription', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_assigning_subscription_to_unknown_store_returns_not_found(): void
    {
        $plan = $this->createPlan();
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/stores/999999/subscription', ['plan_id' => $plan->id])
            ->assertStatus(404);
    }

    public function test_expert_cannot_assign_subscriptions(): void
    {
        $plan = $this->createPlan();
        $store = Store::factory()->create();
        $expert = $this->userWithRole('expert');

        $this->actingAs($expert, 'api')
            ->postJson('/api/admin/stores/'.$store->id.'/subscription', ['plan_id' => $plan->id])
            ->assertForbidden();
    }
}
