<?php

namespace App\Console\Commands;

use App\Models\EtimsDevice;
use App\Models\Product;
use App\Services\Tax\Etims\Oscu\OscuCatalogue;
use Illuminate\Console\Command;

/**
 * Downloads KRA's code lists and item classes through an initialised
 * device, and with --products registers the branch's institution's active
 * products with KRA ahead of their first sale.
 */
class SyncEtimsCatalogue extends Command
{
    protected $signature = 'etims:sync
        {branch : The branch code, or its id, whose device to use}
        {--products : Also register every active product with KRA}';

    protected $description = 'Download KRA eTIMS code lists and item classes, and optionally register products';

    public function handle(OscuCatalogue $catalogue): int
    {
        $device = EtimsDevice::withoutGlobalScopes()
            ->whereHas('branch', fn ($q) => $q->withoutGlobalScopes()->where(fn ($q) => $q->where('code', $this->argument('branch'))->orWhere('id', $this->argument('branch'))))
            ->first();
        if (! $device || ! $device->isActive()) {
            $this->error('That branch has no initialised eTIMS device; run etims:device first.');

            return self::FAILURE;
        }

        try {
            $this->info('Codes: '.$catalogue->syncCodes($device));
            $this->info('Item classes: '.$catalogue->syncItemClasses($device));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('products')) {
            return self::SUCCESS;
        }

        $failed = 0;
        $products = Product::withoutGlobalScopes()->where('organisation_id', $device->organisation_id)->where('is_active', true)->orderBy('code')->get();
        foreach ($products as $product) {
            try {
                $this->line($catalogue->ensureRegistered($device, $product).'  '.$product->name);
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("{$product->name}: {$e->getMessage()}");
            }
        }
        $this->info(($products->count() - $failed)." of {$products->count()} products registered.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
