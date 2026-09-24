<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One test on a lab order, carrying its own snapshot (name, price, normal
 * range) and, once processed, its result.
 */
class LabOrderTest extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'lab_order_id', 'lab_test_id', 'test_name', 'price', 'normal_range', 'unit',
        'result_value', 'result_notes', 'is_abnormal', 'result_entered_by', 'result_entered_at',
    ];

    protected function casts(): array
    {
        return ['is_abnormal' => 'boolean', 'result_entered_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function resultEnteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_entered_by');
    }
}
