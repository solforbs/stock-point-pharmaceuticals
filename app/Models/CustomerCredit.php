<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredit extends Model
{
    protected $primaryKey = 'customer_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_id', 'credit_limit', 'current_balance', 'unallocated_receipts',
        'on_hold', 'hold_reason', 'reviewed_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:4',
            'current_balance' => 'decimal:4',
            'unallocated_receipts' => 'decimal:4',
            'on_hold' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Part 10.4 — value of confirmed-but-undelivered sales orders. "Open
     * sales orders count against the limit": stock promised to a customer
     * is credit already committed, even though no invoice exists yet.
     */
    public function openOrderExposure(): string
    {
        $rows = SalesOrderLine::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_orders.customer_id', $this->customer_id)
            ->whereIn('sales_orders.status', ['CONFIRMED', 'IN_PROGRESS', 'PARTIALLY_FULFILLED'])
            ->select('sales_order_lines.line_total', 'sales_order_lines.qty_base', 'sales_order_lines.qty_dispatched_base')
            ->get();

        $exposure = '0.0000';
        foreach ($rows as $row) {
            if (bccomp((string) $row->qty_base, '0', 4) <= 0) {
                continue;
            }
            $undelivered = bcsub((string) $row->qty_base, (string) $row->qty_dispatched_base, 4);
            $share = bcdiv($undelivered, (string) $row->qty_base, 6);
            $exposure = bcadd($exposure, bcmul((string) $row->line_total, $share, 4), 4);
        }

        return $exposure;
    }

    /**
     * Outstanding invoices plus open orders — everything already counting
     * against the limit before the current cart is considered.
     */
    public function exposure(): string
    {
        return bcadd((string) $this->current_balance, $this->openOrderExposure(), 4);
    }

    /**
     * Part 7.3/10.4 — credit_available = credit_limit − outstanding_invoices − open_sales_orders.
     */
    public function availableCredit(): string
    {
        return bcsub((string) $this->credit_limit, $this->exposure(), 4);
    }
}
