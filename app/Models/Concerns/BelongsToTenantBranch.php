<?php

namespace App\Models\Concerns;

use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A row owned through a branch (no organisation_id of its own). Visible only
 * when its branch belongs to the active institution, and it can only be
 * created in one of that institution's branches.
 *
 * Override tenantBranchColumn() when the column is not branch_id.
 */
trait BelongsToTenantBranch
{
    public static function bootBelongsToTenantBranch(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $context = app(TenantContext::class);
            if ($context->isActive()) {
                $model = $query->getModel();
                $query->whereIn($model->qualifyColumn($model->tenantBranchColumn()), $context->branchIds());
            }
        });

        static::saving(function (Model $model) {
            $context = app(TenantContext::class);
            $column = $model->tenantBranchColumn();
            if ($context->isActive() && $model->isDirty($column) && $model->{$column} !== null
                && ! in_array((string) $model->{$column}, $context->branchIds(), true)) {
                throw new AuthorizationException('That branch belongs to another institution.');
            }
        });
    }

    public function tenantBranchColumn(): string
    {
        return 'branch_id';
    }
}
