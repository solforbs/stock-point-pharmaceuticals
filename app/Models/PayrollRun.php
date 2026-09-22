<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read array<int, array<string, mixed>>|null $bands_snapshot_json
 * @property-read Carbon|null $bands_as_of
 * @property-read Carbon|null $posted_at
 */
class PayrollRun extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'doc_number', 'period_year', 'period_month', 'status', 'bands_snapshot_json', 'bands_as_of',
        'total_gross', 'total_paye', 'total_nssf_employee', 'total_nssf_employer', 'total_shif', 'total_housing_levy_employee',
        'total_housing_levy_employer', 'total_other_deductions', 'total_net', 'journal_id', 'payment_journal_id',
        'created_by', 'approved_by', 'posted_by', 'computed_at', 'approved_at', 'posted_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'bands_snapshot_json' => 'array',
            'bands_as_of' => 'date',
            'total_gross' => 'decimal:4', 'total_paye' => 'decimal:4', 'total_nssf_employee' => 'decimal:4', 'total_nssf_employer' => 'decimal:4',
            'total_shif' => 'decimal:4', 'total_housing_levy_employee' => 'decimal:4', 'total_housing_levy_employer' => 'decimal:4',
            'total_other_deductions' => 'decimal:4', 'total_net' => 'decimal:4',
            'computed_at' => 'datetime', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PayrollRunLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class);
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
