<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $status
 */
class FinancialPeriod extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'fiscal_year', 'period_no', 'start_date', 'end_date', 'status', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public static function openPeriodFor(string $organisationId, \DateTimeInterface $date): ?self
    {
        return static::query()
            ->where('organisation_id', $organisationId)
            ->where('status', 'OPEN')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }
}
