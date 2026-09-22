<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part 16.7 — a sale made at the counter while the server was unreachable.
 *
 * POSTED: it is in the books as {@see $sale_id}. CONFLICT: it could not be
 * posted and waits for a supervisor. DISMISSED: a supervisor decided it
 * will not be posted, with a reason.
 *
 * @property-read string $status
 */
class OfflineSale extends Model
{
    use BelongsToOrganisation, HasUuids;

    public const Posted = 'POSTED';

    public const Conflict = 'CONFLICT';

    public const Dismissed = 'DISMISSED';

    protected $fillable = [
        'organisation_id', 'branch_id', 'store_id', 'terminal_id', 'user_id', 'idempotency_key',
        'sold_at', 'payload_json', 'offline_total', 'server_total', 'price_variance', 'status', 'sale_id',
        'error_code', 'error_message', 'attempts', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'resolved_at' => 'datetime',
            'payload_json' => 'array',
            'offline_total' => 'decimal:4',
            'server_total' => 'decimal:4',
            'price_variance' => 'decimal:4',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
