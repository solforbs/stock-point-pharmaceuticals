<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Branch extends Model
{
    use BelongsToOrganisation, HasUuids;

    // Roles that apply organisation-wide: holders are auto-assigned to every
    // branch, including new ones, because Spatie's "teams" pivot requires a
    // real (non-null) branch_id on every assignment — see Part 0.7's
    // "organisation -> branch -> store" structure and 18.3's role list.
    // Governance 2026-09-24: only the Director spans branches by right;
    // every other standard role is assigned per branch.
    public const GLOBAL_ROLES = ['Director'];

    protected $fillable = [
        'organisation_id', 'code', 'name', 'address', 'county',
        'is_active', 'retail_enabled', 'wholesale_enabled', 'dispensing_enabled', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'retail_enabled' => 'boolean',
            'wholesale_enabled' => 'boolean',
            'dispensing_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Branch $branch) {
            $pivotTable = config('permission.table_names.model_has_roles');

            // Only this institution's roles, so a new branch never gains
            // another institution's directors or administrators.
            $globalRoleIds = Role::withoutGlobalScopes()
                ->where('organisation_id', $branch->organisation_id)
                ->whereIn('name', self::GLOBAL_ROLES)
                ->pluck('id', 'name');

            app(TenantContext::class)->refresh();

            foreach ($globalRoleIds as $roleName => $roleId) {
                $userIds = DB::table($pivotTable)
                    ->where('role_id', $roleId)
                    ->distinct()
                    ->pluck('model_id');

                foreach ($userIds as $userId) {
                    DB::table($pivotTable)->insertOrIgnore([
                        'role_id' => $roleId,
                        'model_id' => $userId,
                        'model_type' => User::class,
                        'branch_id' => $branch->id,
                    ]);
                }
            }
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
