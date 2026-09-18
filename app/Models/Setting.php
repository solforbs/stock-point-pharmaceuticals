<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuids;

    public $timestamps = false; // set_at is the only timestamp, set explicitly

    protected $fillable = [
        'organisation_id', 'branch_id', 'scope', 'key', 'value_json',
        'effective_from', 'effective_to', 'set_by', 'set_at',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'set_at' => 'datetime',
        ];
    }

    /**
     * Resolve a setting's value, most specific first: a branch-scoped row
     * beats the organisation-wide one for the same scope/key.
     */
    public static function resolve(string $organisationId, ?string $branchId, string $scope, string $key, mixed $default = null): mixed
    {
        $today = now()->toDateString();

        $query = fn ($branch) => static::query()
            ->where('organisation_id', $organisationId)
            ->where('branch_id', $branch)
            ->where('scope', $scope)
            ->where('key', $key)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderByDesc('effective_from')
            ->first();

        if ($branchId && ($setting = $query($branchId))) {
            return $setting->value_json;
        }

        if ($setting = $query(null)) {
            return $setting->value_json;
        }

        return $default;
    }
}
