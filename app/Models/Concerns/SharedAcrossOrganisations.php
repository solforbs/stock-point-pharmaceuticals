<?php

namespace App\Models\Concerns;

use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * A reference list with shared rows (organisation_id NULL, maintained by the
 * platform) beside each institution's private additions. A tenant sees the
 * shared rows and its own, never another tenant's, and cannot change or
 * delete a shared row.
 */
trait SharedAcrossOrganisations
{
    public static function bootSharedAcrossOrganisations(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $organisationId = app(TenantContext::class)->organisationId();
            if ($organisationId !== null) {
                $column = $query->getModel()->qualifyColumn('organisation_id');
                $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, $organisationId));
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

        $guardShared = function (Model $model) {
            $context = app(TenantContext::class);
            if (! $context->isActive()) {
                return;
            }
            if ($model->getOriginal('organisation_id') === null) {
                // Platform administrators keep the shared rows current (a new
                // statutory rate, a new dosage form) from inside the app.
                if (Auth::user()?->is_platform_admin && ! $model->isDirty('organisation_id')) {
                    return;
                }
                throw new AuthorizationException('Shared reference entries are maintained by the platform and cannot be changed here.');
            }
            if ($model->isDirty('organisation_id')) {
                throw new AuthorizationException('A record cannot be moved to another institution.');
            }
        };
        static::updating($guardShared);
        static::deleting($guardShared);
    }

    public function isShared(): bool
    {
        return $this->organisation_id === null;
    }
}
