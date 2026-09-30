<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $voucher_type TOPUP or EXPENSE
 * @property-read string $status POSTED or VOID
 */
class PettyCashVoucher extends Model
{
    use BelongsToOrganisation, HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'doc_number', 'voucher_type', 'voucher_date', 'account_id',
        'funding_source', 'amount', 'payee', 'description', 'receipt_ref', 'status', 'journal_id',
        'created_by', 'voided_by', 'void_reason', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date:Y-m-d',
            'amount' => 'decimal:4',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
