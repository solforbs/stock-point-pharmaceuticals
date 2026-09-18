<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\CustomerContact;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Customer contacts (the people at each customer) and the communication
 * log with follow-ups that stay due until marked done.
 */
class CustomerContactsHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['sale.view', 'customer.manage']);
        Sanctum::actingAs($this->user);
    }

    public function test_contacts_are_created_listed_and_updated_with_a_single_primary(): void
    {
        $buyer = $this->postJson('/api/customer-contacts', ['customer_id' => $this->customer->id, 'name' => 'Sister Akai', 'role' => 'Pharmacy stores', 'phone' => '0722000111', 'is_primary' => true])
            ->assertCreated()->assertJsonPath('customer.code', 'TCRH')->assertJsonPath('is_primary', true)->json();
        $accounts = $this->postJson('/api/customer-contacts', ['customer_id' => $this->customer->id, 'name' => 'John Lokwang', 'role' => 'Accounts', 'email' => 'accounts@tcrh.test'])
            ->assertCreated()->assertJsonPath('is_primary', false)->json();

        $this->postJson('/api/customer-contacts', ['customer_id' => $this->customer->id, 'name' => 'X', 'email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/customer-contacts', ['name' => 'No customer'])->assertStatus(422)->assertJsonValidationErrors('customer_id');

        $this->getJson('/api/customer-contacts?customer_id='.$this->customer->id)->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.id', $buyer['id']);
        $this->getJson('/api/customer-contacts?q=accounts')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $accounts['id']);

        // Making the accounts contact primary demotes the buyer.
        $this->patchJson("/api/customer-contacts/{$accounts['id']}", ['is_primary' => true, 'phone' => '0733000222'])->assertOk()->assertJsonPath('is_primary', true)->assertJsonPath('phone', '0733000222');
        $this->assertFalse(CustomerContact::findOrFail($buyer['id'])->is_primary);
        $this->assertSame(1, AuditLog::where('action', 'CUSTOMER_CONTACT_UPDATED')->count());
    }

    public function test_interactions_are_logged_and_follow_ups_marked_done(): void
    {
        $contact = CustomerContact::create(['customer_id' => $this->customer->id, 'name' => 'Sister Akai']);

        $due = $this->postJson('/api/customer-interactions', ['customer_id' => $this->customer->id, 'contact_id' => $contact->id, 'channel' => 'CALL', 'summary' => 'Asked for the September price list', 'follow_up_date' => now()->subDay()->toDateString()])
            ->assertCreated()->assertJsonPath('contact.name', 'Sister Akai')->assertJsonPath('user.id', $this->user->id)->assertJsonPath('follow_up_done', false)->json();
        $this->postJson('/api/customer-interactions', ['customer_id' => $this->customer->id, 'channel' => 'VISIT', 'summary' => 'Quarterly review', 'follow_up_date' => now()->addWeek()->toDateString()])->assertCreated();
        $this->postJson('/api/customer-interactions', ['customer_id' => $this->customer->id, 'channel' => 'PIGEON', 'summary' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['channel', 'summary']);

        $this->getJson('/api/customer-interactions?customer_id='.$this->customer->id)->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/customer-interactions?follow_ups_due=1')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $due['id']);

        $this->patchJson("/api/customer-interactions/{$due['id']}", ['follow_up_done' => true])->assertOk()->assertJsonPath('follow_up_done', true);
        $this->getJson('/api/customer-interactions?follow_ups_due=1')->assertOk()->assertJsonPath('total', 0);
        $this->assertSame(1, AuditLog::where('action', 'CUSTOMER_FOLLOW_UP_DONE')->where('entity_id', $due['id'])->count());
    }

    public function test_reading_needs_sale_view_and_writing_needs_customer_manage(): void
    {
        $this->grantPermissions(['sale.view']);
        $this->getJson('/api/customer-contacts')->assertOk();
        $this->getJson('/api/customer-interactions')->assertOk();
        $this->postJson('/api/customer-contacts', ['customer_id' => $this->customer->id, 'name' => 'X'])->assertStatus(403);
        $this->postJson('/api/customer-interactions', ['customer_id' => $this->customer->id, 'channel' => 'CALL', 'summary' => 'Hello'])->assertStatus(403);

        $this->grantPermissions([]);
        $this->getJson('/api/customer-contacts')->assertStatus(403);
    }
}
