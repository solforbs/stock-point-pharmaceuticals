<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\OpeningStockService;
use App\Services\Inventory\OpeningStockValidationException;
use Illuminate\Console\Command;

/**
 * Loads the go-live stock take from a CSV with the header
 * product_code,batch_number,expiry_date,qty,unit_cost[,manufacture_date]
 * (qty and unit_cost per product base unit). All-or-nothing: any bad row
 * is reported and nothing is posted.
 */
class ImportOpeningStock extends Command
{
    protected $signature = 'inventory:import-opening-stock
        {file : Path to the CSV file}
        {--branch=LDW : Branch code}
        {--store=MAIN : Store code within the branch}
        {--user= : Email of the user recorded as having posted it}
        {--dry-run : Validate only; post nothing}';

    protected $description = 'Import opening stock (batches, expiry, quantity, cost) from a CSV into a store';

    public function handle(OpeningStockService $openingStock): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $branch = Branch::where('code', $this->option('branch'))->first();
        $store = $branch ? Store::where('branch_id', $branch->id)->where('code', $this->option('store'))->first() : null;
        if (! $store) {
            $this->error('Unknown branch/store '.$this->option('branch').'/'.$this->option('store'));

            return self::FAILURE;
        }

        $user = User::where('email', (string) $this->option('user'))->first();
        if (! $user) {
            $this->error('Pass --user=<email> of an existing user; the posting is recorded against them.');

            return self::FAILURE;
        }

        $rows = self::readCsv($path);

        try {
            if ($this->option('dry-run')) {
                $openingStock->validateOnly($store, $rows);
                $this->info(count($rows).' row(s) are valid. Nothing was posted (dry run).');

                return self::SUCCESS;
            }

            $summary = $openingStock->import($store, $rows, $user->id);
        } catch (OpeningStockValidationException $e) {
            $this->error($e->getMessage());
            foreach ($e->rowErrors as $line => $messages) {
                $this->line(($line === 0 ? 'File' : "Row {$line}").': '.implode('; ', $messages));
            }

            return self::FAILURE;
        }

        $this->info("Posted {$summary['reference']}: {$summary['lines']} batch(es), {$summary['total_qty']} units, value {$summary['total_value']} ({$summary['expired_lines']} expired).");

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $header = null;
        $rows = [];
        while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
            if ($cells === [null]) {
                continue;
            }
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $cells);

                continue;
            }
            $rows[] = array_combine($header, array_pad(array_map(fn ($c) => trim((string) $c), $cells), count($header), ''));
        }
        fclose($handle);

        return $rows;
    }
}
