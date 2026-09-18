<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class Branch extends Model
{
    use HasUuids;

    // Roles that apply organisation-wide: holders are auto-assigned to every
    // branch, including new ones, because Spatie's "teams" pivot requires a
    // real (non-null) branch_id on every assignment — see Part 0.7's
    // "organisation -> branch -> store" structure and 18.3's role list.
    public const GLOBAL_ROLES = ['Director', 'System Administrator', 'Auditor'];

    protected $fillable = [
        'organisation_id', 'code', 'name', 'address', 'county',
        'is_active', 'retail_enabled', 'wholesale_enabled', 'dispensing_enabled',
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

            $globalRoleIds = Role::withoutGlobalScopes()
                ->whereIn('name', self::GLOBAL_ROLES)
                ->pluck('id', 'name');

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
