<?php

namespace App\Models\Concerns;

use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A row owned through a store (stock balances, counts, adjustments,
 * transfers, locations). Visible only when its store belongs to the active
 * institution, and every store it names must belong to it too.
 *
 * Override tenantStoreColumns() when the columns are not just store_id.
 */
trait BelongsToTenantStore
{
    public static function bootBelongsToTenantStore(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $context = app(TenantContext::class);
            if ($context->isActive()) {
                $model = $query->getModel();
                $query->whereIn($model->qualifyColumn($model->tenantStoreColumns()[0]), $context->storeIds());
            }
        });

        static::saving(function (Model $model) {
            $context = app(TenantContext::class);
            if (! $context->isActive()) {
                return;
            }
            foreach ($model->tenantStoreColumns() as $column) {
                if ($model->isDirty($column) && $model->{$column} !== null
                    && ! in_array((string) $model->{$column}, $context->storeIds(), true)) {
                    throw new AuthorizationException('That store belongs to another institution.');
                }
            }
        });
    }

    /**
     * The store columns; the first one decides visibility.
     *
     * @return list<string>
     */
    public function tenantStoreColumns(): array
    {
        return ['store_id'];
    }
}
