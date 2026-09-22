<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Services\Sales\OfflineSaleService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Part 16.7 — re-prices every selling store's stock for the tills' offline
 * price packs. Pricing is the slow part of a pack (the full quote engine,
 * product by product), so it happens here on a schedule and a till's
 * request only reads the result. Safe to run by hand at any time.
 */
class WarmOfflinePricePacks extends Command
{
    protected $signature = 'pos:warm-price-packs {--store= : Limit to one store code}';

    protected $description = 'Re-price selling stores for the POS offline price packs (Part 16.7)';

    public function handle(OfflineSaleService $offline, TenantContext $tenant): int
    {
        $stores = Store::where('is_sellable', true)
            ->when($this->option('store'), fn ($q, $code) => $q->where('code', $code))
            ->orderBy('code')
            ->get();

        $rows = [];
        foreach ($stores as $store) {
            // A walk-in price never involves a discount, so whose authority the
            // quote runs under makes no difference; it only has to be someone
            // of the store's own institution.
            $organisationId = Branch::whereKey($store->branch_id)->value('organisation_id');
            $user = User::where('organisation_id', $organisationId)->orderBy('id')->first();
            if (! $user) {
                $rows[] = [$store->code, 0, 'no users yet'];

                continue;
            }
            $started = microtime(true);
            $priced = $tenant->run($organisationId, fn () => $offline->warmPriceMap($store, $user));
            $rows[] = [$store->code, $priced, round(microtime(true) - $started, 1).'s'];
        }

        $this->table(['Store', 'Products priced', 'Took'], $rows);

        return self::SUCCESS;
    }
}
