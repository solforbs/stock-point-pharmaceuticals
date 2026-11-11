<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE_TYPES = [
        'GRN_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_BONUS', 'SALE_VOID', 'DISPENSING',
        'CUSTOMER_RETURN', 'TRANSFER_OUT', 'TRANSFER_IN', 'ADJUSTMENT_UP',
        'ADJUSTMENT_DOWN', 'COUNT_VARIANCE', 'QUARANTINE_IN', 'QUARANTINE_OUT',
        'EXPIRY_WRITE_OFF', 'DAMAGE_WRITE_OFF', 'RECALL_BLOCK',
        'REPACK_OUT', 'REPACK_IN', 'OPENING_BALANCE',
    ];

    /**
     * Corrections of a posted goods receipt or sales invoice get their own
     * ledger movement types, so the stock card shows them for what they are.
     */
    public function up(): void
    {
        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->enum('txn_type', [...self::BASE_TYPES, 'GRN_CORRECTION', 'SALE_CORRECTION'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->enum('txn_type', self::BASE_TYPES)->change();
        });
    }
};
