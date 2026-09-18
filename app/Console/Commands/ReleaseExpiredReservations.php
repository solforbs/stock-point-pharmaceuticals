<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\StockBalance;
use App\Models\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Part 7.5 — reservations expire automatically so an abandoned order does
 * not freeze stock forever. Each release is audited.
 */
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'inventory:release-expired-reservations';

    protected $description = 'Release ACTIVE stock reservations whose expires_at has passed (Part 7.5)';

    public function handle(): int
    {
        $expired = StockReservation::where('status', 'ACTIVE')->where('expires_at', '<', now())->get();

        foreach ($expired as $reservation) {
            DB::transaction(function () use ($reservation) {
                $balance = StockBalance::query()
                    ->where('product_id', $reservation->product_id)
                    ->where('batch_id', $reservation->batch_id)
                    ->where('store_id', $reservation->store_id)
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $balance->qty_reserved = bcsub((string) $balance->qty_reserved, (string) $reservation->qty_base, 4);
                    $balance->save();
                }

                $reservation->update(['status' => 'EXPIRED']);

                AuditLog::record('RESERVATION_EXPIRED', 'stock_reservation', $reservation->id, [
                    'reference' => $reservation->source_doc_type.':'.$reservation->source_doc_id,
                    'after_json' => ['qty_base' => (string) $reservation->qty_base, 'expired_at' => $reservation->expires_at->toIso8601String()],
                ]);
            });

            $this->line("Released {$reservation->qty_base} reserved for {$reservation->source_doc_type} {$reservation->source_doc_id}");
        }

        $this->info($expired->count().' reservation(s) released.');

        return self::SUCCESS;
    }
}
