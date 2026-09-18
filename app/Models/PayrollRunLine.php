<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read array<string, mixed>|null $breakdown_json
 */
class PayrollRunLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'basic', 'allowances', 'overtime', 'gross', 'pension_contribution', 'taxable',
        'paye', 'nssf_employee', 'nssf_employer', 'shif', 'housing_levy_employee', 'housing_levy_employer', 'other_deductions', 'net', 'breakdown_json',
    ];

    protected function casts(): array
    {
        return [
            'basic' => 'decimal:4', 'allowances' => 'decimal:4', 'overtime' => 'decimal:4', 'gross' => 'decimal:4', 'pension_contribution' => 'decimal:4',
            'taxable' => 'decimal:4', 'paye' => 'decimal:4', 'nssf_employee' => 'decimal:4', 'nssf_employer' => 'decimal:4', 'shif' => 'decimal:4',
            'housing_levy_employee' => 'decimal:4', 'housing_levy_employer' => 'decimal:4', 'other_deductions' => 'decimal:4', 'net' => 'decimal:4',
            'breakdown_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
