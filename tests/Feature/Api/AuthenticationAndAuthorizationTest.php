<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 18.2 — "Server-side on every request. Hidden buttons are not
 * security." Guests get 401, users without the permission get 403, and
 * branch-scoped roles resolve through the request's active branch.
 */
class AuthenticationAndAuthorizationTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
    }

    public function test_guests_get_a_json_401_from_the_api(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_guests_get_a_401_from_logout_even_without_a_json_accept_header(): void
    {
        $this->post('/auth/logout')->assertStatus(401);
    }

    public function test_login_returns_the_user_and_starts_a_session(): void
    {
        $this->post('/auth/login', ['email' => 'cashier@example.test', 'password' => 'password-long-enough'])
            ->assertOk()
            ->assertJsonPath('email', 'cashier@example.test')
            ->assertJsonMissingPath('password');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_login_with_wrong_password_is_rejected_without_revealing_whether_the_user_exists(): void
    {
        $wrongPassword = $this->postJson('/auth/login', ['email' => 'cashier@example.test', 'password' => 'wrong'])
            ->assertStatus(422)->json('errors.email.0');
        $unknownUser = $this->postJson('/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])
            ->assertStatus(422)->json('errors.email.0');

        $this->assertSame($wrongPassword, $unknownUser, 'Part 22.1: the message must not reveal whether the username exists');
        $this->assertGuest();
    }

    public function test_a_user_without_the_permission_cannot_create_a_product(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/products', $this->productPayload())->assertStatus(403);
        $this->assertSame(1, Product::count());
    }

    public function test_a_user_with_a_branch_scoped_role_can_create_a_product_in_that_branch(): void
    {
        Permission::create(['name' => 'product.create', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Storekeeper', 'guard_name' => 'web', 'branch_id' => null]);
        $role->givePermissionTo('product.create');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('active_branch_id', $this->branch->id)
            ->assertJsonFragment(['permissions' => ['product.create']]);

        $this->postJson('/api/products', $this->productPayload())
            ->assertCreated()
            ->assertJsonPath('code', 'PARA500')
            ->assertJsonCount(1, 'uoms');

        $product = Product::where('code', 'PARA500')->firstOrFail();
        $this->assertSame($this->org->id, $product->organisation_id, 'Product is scoped to the active branch\'s organisation');
        $this->assertTrue($product->uoms->first()->is_base, 'The base UOM row is created with the product');
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(): array
    {
        return [
            'code' => 'PARA500',
            'name' => 'Paracetamol 500mg Tablets',
            'base_uom_id' => $this->uoms['TAB']->id,
        ];
    }
}
