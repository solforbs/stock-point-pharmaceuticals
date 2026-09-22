<?php

namespace Tests\Feature\Api;

use App\Mail\PurchaseOrderSentMail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 9/17 — the notifications a workflow raises. A requisition nobody
 * knows is waiting never gets approved, and an order the supplier never
 * receives never gets dispatched.
 */
class WorkflowNotificationsTest extends TestCase
{
    use BuildsBlueprintWorld;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions([
            'requisition.create', 'requisition.approve', 'po.create', 'po.approve', 'grn.create',
            'stock.transfer.create', 'stock.transfer.approve', 'stock.transfer.dispatch', 'product.view',
        ]);

        $this->approver = $this->userWith('approver@stockpoint.test', 'Approvers', ['requisition.approve', 'grn.create']);
        Sanctum::actingAs($this->user);
    }

    /**
     * Another person holding the given permissions in this branch.
     *
     * @param  list<string>  $permissions
     */
    private function userWith(string $email, string $roleName, array $permissions): User
    {
        $user = $this->colleague([
            'name' => ucfirst(explode('@', $email)[0]), 'username' => explode('@', $email)[0], 'email' => $email,
            'password' => Hash::make('a-long-enough-password'), 'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::firstOrCreate(['organisation_id' => $this->org->id, 'name' => $roleName, 'guard_name' => 'web', 'branch_id' => null]);
        $role->syncPermissions($permissions);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        return $user;
    }

    public function test_submitting_a_requisition_tells_whoever_may_approve_it(): void
    {
        $requisition = $this->postJson('/api/requisitions', [
            'needed_by' => now()->addWeek()->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'qty_base' => '500']],
        ])->assertCreated()->json();

        $this->postJson("/api/requisitions/{$requisition['id']}/submit")->assertOk();

        $message = UserMessage::where('recipient_id', $this->approver->id)->firstOrFail();
        $this->assertStringContainsString($requisition['doc_number'], $message->subject);
        $this->assertStringContainsString('needs approval', $message->subject);
        $this->assertSame('REQUISITION', $message->category);
        $this->assertSame('HIGH', $message->priority);
        $this->assertNull($message->sender_id, 'raised by the application, not by a person');

        $this->assertSame(0, UserMessage::where('recipient_id', $this->user->id)->count(), 'nobody is told what they just did themselves');
    }

    public function test_the_requester_is_told_the_outcome(): void
    {
        $requisition = $this->postJson('/api/requisitions', ['lines' => [['product_id' => $this->amox->id, 'qty_base' => '10']]])->assertCreated()->json();
        $this->postJson("/api/requisitions/{$requisition['id']}/submit")->assertOk();

        // The approver, not the requester, decides.
        Sanctum::actingAs($this->approver);
        $this->postJson("/api/requisitions/{$requisition['id']}/reject", ['reason' => 'Stock already on order'])->assertOk();

        $message = UserMessage::where('recipient_id', $this->user->id)->firstOrFail();
        $this->assertStringContainsString('rejected', $message->subject);
        $this->assertSame('Stock already on order', $message->body);
    }

    public function test_sending_a_purchase_order_emails_the_supplier_who_must_dispatch_it(): void
    {
        Mail::fake();
        $this->supplier->update(['email' => 'orders@pharmadistributors.test', 'contact_name' => 'Order Desk']);

        $po = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'expected_date' => now()->addDays(3)->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '5', 'unit_price' => '420']],
        ])->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $this->postJson("/api/purchase-orders/{$po['id']}/send")->assertOk()
            ->assertJsonPath('status', 'SENT')
            ->assertJsonPath('supplier_notified', true);

        Mail::assertSent(PurchaseOrderSentMail::class, function (PurchaseOrderSentMail $mail) use ($po) {
            $body = $mail->render();
            $this->assertStringContainsString($po['doc_number'], $body);
            $this->assertStringContainsString('AMOX500', $body, 'the supplier is told what to pick');
            $this->assertStringContainsString('batch number and expiry date', $body);

            return $mail->hasTo('orders@pharmadistributors.test');
        });
    }

    public function test_a_supplier_with_no_address_is_reported_rather_than_silently_skipped(): void
    {
        Mail::fake();
        $this->supplier->update(['email' => null]);

        $po = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '2', 'unit_price' => '400']],
        ])->assertCreated()->json();
        $this->postJson("/api/purchase-orders/{$po['id']}/approve")->assertOk();

        $this->postJson("/api/purchase-orders/{$po['id']}/send")->assertOk()
            ->assertJsonPath('status', 'SENT')
            ->assertJsonPath('supplier_notified', false);

        Mail::assertNothingSent();

        $message = UserMessage::where('category', 'PURCHASE_ORDER')->where('recipient_id', $this->approver->id)->firstOrFail();
        $this->assertStringContainsString('no email address on file', $message->body);
        $this->assertSame('HIGH', $message->priority, 'somebody has to send it by hand');
    }

    public function test_a_dispatched_transfer_is_announced_only_to_those_who_can_receive_it(): void
    {
        $receiver = $this->userWith('receiver@stockpoint.test', 'Receivers', ['stock.transfer.receive']);

        $batch = $this->receive('B-TRF', now()->addYear()->toDateString(), '100', '2.0000');
        $cold = Store::create([
            'branch_id' => $this->branch->id, 'code' => 'COLD2', 'name' => 'Cold Room', 'store_type' => 'COLD', 'is_sellable' => false,
        ]);

        $transfer = $this->postJson('/api/inventory/transfers', [
            'from_store_id' => $this->store->id,
            'to_store_id' => $cold->id,
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $batch->id, 'qty_base' => '10']],
        ])->assertCreated()->json();

        // Segregation of duties: someone other than the requester approves.
        Sanctum::actingAs($this->userWith('transfers@stockpoint.test', 'Transfer approvers', ['stock.transfer.approve']));
        $this->postJson("/api/inventory/transfers/{$transfer['id']}/approve")->assertOk();
        Sanctum::actingAs($this->user);
        $this->postJson("/api/inventory/transfers/{$transfer['id']}/dispatch")->assertOk();

        $this->assertSame(1, UserMessage::where('category', 'TRANSFER')->where('recipient_id', $receiver->id)->count());
        $this->assertSame(0, UserMessage::where('category', 'TRANSFER')->where('recipient_id', $this->approver->id)->count(), 'the approver cannot receive transfers, so is not told');
    }
}
