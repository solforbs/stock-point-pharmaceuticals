<?php

namespace Tests\Feature\Api;

use App\Services\Modules\ModuleCatalogue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Module access is permission-driven, never role-name-driven: a user's
 * accessible modules come from which permission catalogues they hold, the
 * /api/user payload advertises them, and the module route gates make the
 * other modules unreachable even by typing their URLs.
 */
class ModuleAccessTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_a_pharmacy_only_user_gets_exactly_the_pharmacy_module(): void
    {
        $this->grantPermissions(['sale.view', 'stock.view']);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('modules', [ModuleCatalogue::PHARMACY]);
    }

    public function test_a_clinician_sees_only_the_hospital_module_even_though_they_order_lab_tests(): void
    {
        $this->grantPermissions([
            'hospital.view', 'hospital.consultation.manage', 'laboratory.order.create', 'laboratory.order.view',
        ]);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('modules', [ModuleCatalogue::HOSPITAL]);
    }

    public function test_a_multi_module_user_sees_each_module_they_hold_a_permission_in(): void
    {
        $this->grantPermissions(['hospital.view', 'laboratory.result.enter', 'sale.view']);

        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('modules', [ModuleCatalogue::HOSPITAL, ModuleCatalogue::LABORATORY, ModuleCatalogue::PHARMACY]);
    }

    public function test_module_urls_are_gated_no_permission_means_403_regardless_of_the_url_typed(): void
    {
        $this->grantPermissions(['sale.view']); // pharmacy only

        $this->getJson('/api/hospital/patients')->assertStatus(403)->assertJsonPath('code', 'MODULE_ACCESS_DENIED');
        $this->getJson('/api/laboratory/orders')->assertStatus(403)->assertJsonPath('code', 'MODULE_ACCESS_DENIED');
    }

    public function test_the_module_gate_opens_with_a_module_permission_but_fine_grained_checks_remain(): void
    {
        $this->grantPermissions(['hospital.view']);

        // Through the module gate, and hospital.view may list facilities…
        $this->getJson('/api/hospital/facilities')->assertOk();
        // …but patient records still need their own permission.
        $this->getJson('/api/hospital/patients')->assertStatus(403);
    }
}
