<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A request for quotation: what is needed, which suppliers are asked, their
 * quotes, and the award that turns the chosen quotes into purchase orders.
 *
 * @property-read string $status
 * @property-read Carbon|null $needed_by
 * @property-read Carbon|null $awarded_at
 */
class Rfq extends Model
{
    use BelongsToTenantBranch, HasUuids;

    public const DEFAULT_WEIGHTS = ['price' => 60, 'lead_time' => 15, 'payment_terms' => 10, 'supplier_record' => 15];

    protected $fillable = [
        'doc_number', 'branch_id', 'title', 'needed_by', 'notes', 'status',
        'weight_price', 'weight_lead_time', 'weight_payment_terms', 'weight_supplier_record',
        'created_by', 'sent_at', 'closed_at',
        'award_mode', 'award_followed_recommendation', 'award_justification', 'awarded_by', 'awarded_at',
        'analysis_snapshot', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
            'awarded_at' => 'datetime',
            'award_followed_recommendation' => 'boolean',
            'analysis_snapshot' => 'array',
            'weight_price' => 'decimal:2',
            'weight_lead_time' => 'decimal:2',
            'weight_payment_terms' => 'decimal:2',
            'weight_supplier_record' => 'decimal:2',
        ];
    }

    /**
     * @return array{price: float, lead_time: float, payment_terms: float, supplier_record: float}
     */
    public function weights(): array
    {
        return [
            'price' => (float) $this->weight_price,
            'lead_time' => (float) $this->weight_lead_time,
            'payment_terms' => (float) $this->weight_payment_terms,
            'supplier_record' => (float) $this->weight_supplier_record,
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<RfqLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RfqLine::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<RfqSupplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(RfqSupplier::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
