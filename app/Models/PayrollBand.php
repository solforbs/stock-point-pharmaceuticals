<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $effective_from
 * @property-read Carbon|null $effective_to
 * @property-read array<string, mixed>|null $meta_json
 */
class PayrollBand extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'band_type', 'sequence', 'effective_from', 'effective_to',
        'lower', 'upper', 'rate_pct', 'fixed_amount', 'meta_json', 'source',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'lower' => 'decimal:4',
            'upper' => 'decimal:4',
            'rate_pct' => 'decimal:4',
            'fixed_amount' => 'decimal:4',
            'meta_json' => 'array',
        ];
    }

    /**
     * The bands in force on a date: the organisation's own override when
     * one exists, otherwise the statutory (global) rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function inForce(string $organisationId, string $asOf): array
    {
        $rows = static::query()
            ->where(fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $organisationId))
            ->whereDate('effective_from', '<=', $asOf)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOf))
            ->orderBy('band_type')->orderBy('sequence')
            ->get();

        // An organisation override replaces the whole band type.
        $overridden = $rows->whereNotNull('organisation_id')->pluck('band_type')->unique();
        $rows = $rows->reject(fn (PayrollBand $b) => $b->organisation_id === null && $overridden->contains($b->band_type));

        return $rows->map(fn (PayrollBand $b) => [
            'id' => $b->id, 'band_type' => $b->band_type, 'sequence' => (int) $b->sequence,
            'effective_from' => $b->effective_from->toDateString(), 'effective_to' => $b->effective_to?->toDateString(),
            'lower' => (string) $b->lower, 'upper' => $b->upper !== null ? (string) $b->upper : null,
            'rate_pct' => (string) $b->rate_pct, 'fixed_amount' => $b->fixed_amount !== null ? (string) $b->fixed_amount : null,
            'meta' => $b->meta_json ?? [], 'source' => $b->source,
        ])->values()->all();
    }
}
