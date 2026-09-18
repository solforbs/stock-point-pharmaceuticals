<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $date_joined
 * @property-read Carbon|null $date_left
 */
class Employee extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'user_id', 'employee_no', 'name', 'national_id', 'kra_pin', 'nssf_no', 'shif_no',
        'job_title', 'department', 'employment_type', 'date_joined', 'date_left', 'basic_salary', 'regular_allowances',
        'pension_contribution', 'bank_name', 'bank_account', 'is_active', 'created_by',
    ];

    protected $hidden = ['bank_account'];

    protected function casts(): array
    {
        return [
            'date_joined' => 'date',
            'date_left' => 'date',
            'basic_salary' => 'decimal:4',
            'regular_allowances' => 'decimal:4',
            'pension_contribution' => 'decimal:4',
            'is_active' => 'boolean',
            // Part 19.2 — bank details are a fraud vector; encrypted at rest.
            'bank_name' => 'encrypted',
            'bank_account' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
