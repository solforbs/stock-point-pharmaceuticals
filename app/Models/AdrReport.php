<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part 11.4 — an adverse drug reaction report (PPB PViMS reference once filed).
 *
 * @property-read Carbon|null $submitted_at
 * @property-read Carbon|null $closed_at
 */
class AdrReport extends Model
{
    use HasUuids;

    public const SERIOUS = ['SERIOUS', 'LIFE_THREATENING', 'FATAL'];

    protected $fillable = [
        'doc_number', 'organisation_id', 'branch_id', 'product_id', 'batch_id', 'customer_id',
        'patient_initials', 'patient_age', 'patient_sex', 'reaction_description', 'onset_date',
        'seriousness', 'outcome', 'action_taken', 'reporter_name', 'investigation_notes', 'status', 'ppb_reference',
        'submitted_at', 'submitted_by', 'closed_at', 'closed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'onset_date' => 'date:Y-m-d',
            'patient_age' => 'integer',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isSerious(): bool
    {
        return in_array($this->seriousness, self::SERIOUS, true);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
