<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NumberSequence extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'scope', 'branch_id', 'prefix',
        'fiscal_year', 'current_value', 'padding', 'reset_policy',
    ];

    /**
     * Issue the next gapless number for a document scope (Part 14.4).
     * Must run inside the same transaction as the document it numbers, so
     * that a rolled-back document doesn't leave a used-then-abandoned number
     * — but the SELECT ... FOR UPDATE lock itself is taken here regardless.
     */
    public static function next(string $organisationId, string $scope, ?string $branchId, string $prefix, int $padding = 6, string $resetPolicy = 'ANNUAL'): string
    {
        return DB::transaction(function () use ($organisationId, $scope, $branchId, $prefix, $padding, $resetPolicy) {
            $fiscalYear = $resetPolicy === 'ANNUAL' ? (int) now()->format('Y') : null;

            $sequence = static::query()
                ->where('organisation_id', $organisationId)
                ->where('scope', $scope)
                ->where('branch_id', $branchId)
                ->where('fiscal_year', $fiscalYear)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = static::create([
                    'organisation_id' => $organisationId,
                    'scope' => $scope,
                    'branch_id' => $branchId,
                    'prefix' => $prefix,
                    'fiscal_year' => $fiscalYear,
                    'current_value' => 0,
                    'padding' => $padding,
                    'reset_policy' => $resetPolicy,
                ]);

                $sequence = static::query()->whereKey($sequence->id)->lockForUpdate()->first();
            }

            $sequence->increment('current_value');

            $padded = str_pad((string) $sequence->current_value, $sequence->padding, '0', STR_PAD_LEFT);

            return $sequence->fiscal_year
                ? "{$sequence->prefix}-{$sequence->fiscal_year}-{$padded}"
                : "{$sequence->prefix}-{$padded}";
        });
    }
}
