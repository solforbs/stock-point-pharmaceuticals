<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\ColdChainExcursion;
use App\Models\StorageCondition;
use App\Models\Store;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 8.5 — a reading outside the store's window opens an excursion; it is
 * only closed by a recorded decision, and quarantining holds the stock.
 */
class ColdChainHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Store $fridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->fridge = Store::create(['branch_id' => $this->branch->id, 'code' => 'COLD', 'name' => 'Vaccine fridge', 'store_type' => 'COLD', 'is_sellable' => true]);
        $this->grantPermissions(['stock.view', 'coldchain.record', 'coldchain.review']);
        Sanctum::actingAs($this->user);
    }

    public function test_out_of_range_readings_open_and_extend_one_excursion_that_stays_open_after_recovery(): void
    {
        $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 5, 'recorded_at' => now()->subHours(3)->toDateTimeString()])
            ->assertCreated()->assertJsonPath('is_excursion', false)->assertJsonPath('excursion_id', null);

        $first = $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 9.5, 'recorded_at' => now()->subHours(2)->toDateTimeString()])
            ->assertCreated()->assertJsonPath('is_excursion', true)->json();
        $second = $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 11.25, 'recorded_at' => now()->subHour()->toDateTimeString()])
            ->assertCreated()->json();
        $this->assertSame($first['excursion_id'], $second['excursion_id'], 'a continuing excursion is extended, not duplicated');

        $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 4, 'source' => 'LOGGER'])->assertCreated()->assertJsonPath('is_excursion', false);

        $excursion = ColdChainExcursion::findOrFail($first['excursion_id']);
        $this->assertSame('OPEN', $excursion->status);
        $this->assertNotNull($excursion->ended_at);
        $this->assertSame('9.50', (string) $excursion->min_temp);
        $this->assertSame('11.25', (string) $excursion->max_temp);
        $this->assertTrue(AuditLog::where('action', 'COLD_CHAIN_EXCURSION_OPENED')->where('entity_id', $excursion->id)->exists());

        $this->getJson('/api/cold-chain/summary')->assertOk()
            ->assertJsonPath('0.store.code', 'COLD')
            ->assertJsonPath('0.range.min', '2.00')
            ->assertJsonPath('0.range.max', '8.00')
            ->assertJsonPath('0.open_excursions', 1)
            ->assertJsonPath('0.last_in_range', true)
            ->assertJsonPath('1.store.code', 'MAIN')
            ->assertJsonPath('1.range.min', '15.00')
            ->assertJsonPath('1.last_reading', null);

        $this->getJson('/api/cold-chain/readings?store_id='.$this->fridge->id.'&excursions_only=1')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/cold-chain/readings?from='.now()->addDay()->toDateString())->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/cold-chain/excursions/{$excursion->id}")->assertOk()->assertJsonCount(2, 'readings');
        $this->getJson('/api/cold-chain/excursions?status=UNRESOLVED')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_a_storage_condition_window_overrides_the_store_type_default(): void
    {
        $condition = StorageCondition::create(['organisation_id' => $this->org->id, 'code' => 'FROZEN', 'name' => 'Frozen', 'min_temp_c' => -25, 'max_temp_c' => -15]);
        $this->fridge->update(['storage_condition_id' => $condition->id]);

        $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => -20])->assertCreated()->assertJsonPath('is_excursion', false);
        $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 4])->assertCreated()->assertJsonPath('is_excursion', true);
    }

    public function test_closing_with_quarantine_holds_every_released_batch_with_stock_in_the_store(): void
    {
        $batch = $this->receive('C1', now()->addYear()->toDateString(), '100', '2.0000');
        $this->store->update(['store_type' => 'COLD']);
        $reading = $this->postJson('/api/cold-chain/readings', ['store_id' => $this->store->id, 'temperature_c' => 12])->assertCreated()->json();
        $id = $reading['excursion_id'];

        $this->postJson("/api/cold-chain/excursions/{$id}/close", ['impact_assessment' => 'Still out of range', 'action_taken' => 'NO_IMPACT'])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->postJson("/api/cold-chain/excursions/{$id}/close", ['action_taken' => 'STOCK_QUARANTINED'])
            ->assertStatus(422)->assertJsonValidationErrors('impact_assessment');

        $this->postJson("/api/cold-chain/excursions/{$id}/review")->assertOk()->assertJsonPath('status', 'UNDER_REVIEW');
        $this->postJson("/api/cold-chain/excursions/{$id}/close", ['impact_assessment' => 'Fridge door left open for 3 hours; insulin exposed to 12 °C.', 'action_taken' => 'STOCK_QUARANTINED'])
            ->assertOk()
            ->assertJsonPath('status', 'CLOSED')
            ->assertJsonPath('action_taken', 'STOCK_QUARANTINED')
            ->assertJsonPath('affected_batch_ids', [$batch->id]);

        $this->assertSame('QUARANTINED', $batch->fresh()->status);
        $this->assertStringContainsString($id, (string) AuditLog::where('action', 'BATCH_QUARANTINED')->where('entity_id', $batch->id)->value('reason'));
        $this->postJson("/api/cold-chain/excursions/{$id}/close", ['impact_assessment' => 'Closing twice is refused', 'action_taken' => 'NO_IMPACT'])->assertStatus(409);
    }

    public function test_permissions_and_validation(): void
    {
        $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 'hot'])->assertStatus(422)->assertJsonValidationErrors('temperature_c');
        $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 5, 'recorded_at' => now()->addDay()->toDateTimeString()])->assertStatus(422)->assertJsonValidationErrors('recorded_at');

        $reading = $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 1])->assertCreated()->json();

        $this->grantPermissions(['stock.view']);
        $this->getJson('/api/cold-chain/summary')->assertOk();
        $this->postJson('/api/cold-chain/readings', ['store_id' => $this->fridge->id, 'temperature_c' => 5])->assertStatus(403);
        $this->postJson("/api/cold-chain/excursions/{$reading['excursion_id']}/close", ['impact_assessment' => 'No one may close this', 'action_taken' => 'NO_IMPACT'])->assertStatus(403);

        $this->grantPermissions(['coldchain.record']);
        $this->getJson('/api/cold-chain/readings')->assertStatus(403);
    }
}
