<?php

namespace App\Models\Concerns;

use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A row owned by one institution (organisation_id). Queries only ever see
 * the active tenant's rows, new rows are stamped with it, and a row can
 * neither be created for nor moved to another tenant.
 */
trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $organisationId = app(TenantContext::class)->organisationId();
            if ($organisationId !== null) {
                $query->where($query->getModel()->qualifyColumn('organisation_id'), $organisationId);
            }
        });

        static::creating(function (Model $model) {
            $organisationId = app(TenantContext::class)->organisationId();
            if ($organisationId === null) {
                return;
            }
            if (empty($model->organisation_id)) {
                $model->organisation_id = $organisationId;
            } elseif ($model->organisation_id !== $organisationId) {
                throw new AuthorizationException('That record belongs to another institution.');
            }
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('organisation_id') && app(TenantContext::class)->isActive()) {
                throw new AuthorizationException('A record cannot be moved to another institution.');
            }
        });
    }
}
