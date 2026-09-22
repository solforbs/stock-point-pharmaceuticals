<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerTier;
use App\Models\DosageForm;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StorageCondition;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\User;
use App\Services\Admin\RecordDeletionService;
use App\Services\Admin\RecordNotDeletableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Part 18.3 — deleting master data. Transactions are never deletable: a sale
 * is voided, a journal is reversed, a batch is disposed. What can go is the
 * reference data behind them — a product typed twice, a category nobody
 * wanted, a supplier that never traded — and then only while nothing points
 * at it. Every deletion needs `record.delete` on top of the permission that
 * area already requires, and the whole row is written to the audit log first.
 */
class RecordDeletionController extends ApiController
{
    /**
     * What an administrator may delete, and the permission each needs
     * alongside `record.delete`.
     *
     * @var array<string, array{model: class-string<Model>, permission: string, label: string}>
     */
    private const DELETABLE = [
        'products' => ['model' => Product::class, 'permission' => 'product.edit', 'label' => 'code'],
        'product-categories' => ['model' => ProductCategory::class, 'permission' => 'product.edit', 'label' => 'code'],
        'dosage-forms' => ['model' => DosageForm::class, 'permission' => 'product.edit', 'label' => 'code'],
        'storage-conditions' => ['model' => StorageCondition::class, 'permission' => 'product.edit', 'label' => 'code'],
        'tax-codes' => ['model' => TaxCode::class, 'permission' => 'admin.settings', 'label' => 'code'],
        'customers' => ['model' => Customer::class, 'permission' => 'customer.manage', 'label' => 'code'],
        'customer-contacts' => ['model' => CustomerContact::class, 'permission' => 'customer.manage', 'label' => 'name'],
        'customer-tiers' => ['model' => CustomerTier::class, 'permission' => 'customer.manage', 'label' => 'code'],
        'suppliers' => ['model' => Supplier::class, 'permission' => 'supplier.manage', 'label' => 'code'],
        'price-lists' => ['model' => PriceList::class, 'permission' => 'price.manage', 'label' => 'code'],
        'stores' => ['model' => Store::class, 'permission' => 'admin.settings', 'label' => 'code'],
        'branches' => ['model' => Branch::class, 'permission' => 'admin.settings', 'label' => 'code'],
        'users' => ['model' => User::class, 'permission' => 'admin.users', 'label' => 'email'],
    ];

    /** GET /api/admin/deletable — the types this user may delete from. */
    public function types(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'record.delete');

        $types = [];
        foreach (self::DELETABLE as $type => $definition) {
            $types[] = [
                'type' => $type,
                'permission' => $definition['permission'],
                'allowed' => (bool) $request->user()?->can($definition['permission']),
            ];
        }

        return response()->json(['data' => $types]);
    }

    /**
     * GET /api/admin/records/{type}/{id}/references — what would block a
     * deletion, so a screen can warn before the button is pressed.
     */
    public function references(Request $request, string $type, string $id, RecordDeletionService $deletions): JsonResponse
    {
        $model = $this->find($request, $type, $id);
        $references = $deletions->references($model->getTable(), (string) $model->getKey());

        return response()->json([
            'type' => $type,
            'id' => (string) $model->getKey(),
            'label' => $this->labelFor($type, $model),
            'deletable' => $references === [],
            'references' => $references,
        ]);
    }

    /** DELETE /api/admin/records/{type}/{id} */
    public function destroy(Request $request, string $type, string $id, RecordDeletionService $deletions): JsonResponse
    {
        $model = $this->find($request, $type, $id);
        $label = $this->labelFor($type, $model);

        $this->guardSpecialCases($request, $type, $model);
        $deletions->delete($model, $label, $request->user()?->id);

        return response()->json(['deleted' => true, 'type' => $type, 'id' => (string) $model->getKey(), 'label' => $label]);
    }

    /**
     * Cases the foreign keys cannot see: an administrator must not be able to
     * delete their own account, nor the last account that can manage users —
     * either would lock everyone out of the system.
     */
    private function guardSpecialCases(Request $request, string $type, Model $model): void
    {
        if ($type !== 'users') {
            return;
        }

        if ((int) $model->getKey() === (int) $request->user()?->id) {
            throw new RecordNotDeletableException('CANNOT_DELETE_SELF', 422, 'You cannot delete the account you are signed in with.');
        }

        /** @var User $model */
        $remaining = User::where('organisation_id', $this->organisationId($request))->where('is_active', true)->whereKeyNot($model->getKey())->get()
            ->filter(fn (User $u) => $u->can('admin.users'))->count();

        if ($remaining === 0) {
            throw new RecordNotDeletableException('LAST_ADMINISTRATOR', 422, 'This is the last account that can manage users; deleting it would lock everyone out.');
        }
    }

    private function find(Request $request, string $type, string $id): Model
    {
        $definition = self::DELETABLE[$type] ?? null;
        if ($definition === null) {
            throw new RecordNotDeletableException('NOT_DELETABLE', 404, "There is no deletable record type '{$type}'. Transactions are voided or reversed, never deleted.");
        }

        $this->requirePermission($request, 'record.delete');
        $this->requirePermission($request, $definition['permission']);

        // Tenant-owned models filter themselves to the signed-in institution;
        // users and contacts are confined here explicitly.
        $organisationId = $this->organisationId($request);
        $query = $definition['model']::query();
        if ($type === 'users') {
            $query->where('organisation_id', $organisationId);
        } elseif ($type === 'customer-contacts') {
            $query->whereIn('customer_id', Customer::query()->select('id'));
        }

        /** @var Model|null $model */
        $model = $query->find($id);
        if ($model === null) {
            throw new RecordNotDeletableException('NOT_FOUND', 404, 'No record with that id exists.');
        }

        return $model;
    }

    private function labelFor(string $type, Model $model): string
    {
        $attribute = self::DELETABLE[$type]['label'];

        return (string) ($model->getAttribute($attribute) ?? $model->getKey());
    }
}
