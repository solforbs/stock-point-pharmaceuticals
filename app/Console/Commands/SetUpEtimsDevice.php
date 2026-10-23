<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\EtimsDevice;
use App\Services\Tax\Etims\Oscu\OscuCatalogue;
use Illuminate\Console\Command;

/**
 * Records the OSCU device KRA approved for a branch and initialises it,
 * which is when KRA issues the communication key. Run once per branch,
 * with the PIN, branch id and serial from KRA's approval:
 *
 *   php artisan etims:device MAIN --pin=P051234567M --bhf=00 --serial=ABC123 --environment=sandbox
 */
class SetUpEtimsDevice extends Command
{
    protected $signature = 'etims:device
        {branch : The branch code, or its id}
        {--pin= : The taxpayer KRA PIN the device was approved for}
        {--bhf=00 : KRA branch id (00 is head office)}
        {--serial= : The device serial KRA approved}
        {--environment=sandbox : sandbox or production}
        {--organisation= : The institution id, when branch codes repeat across institutions}';

    protected $description = 'Set up and initialise a branch\'s KRA eTIMS OSCU device';

    public function handle(OscuCatalogue $catalogue): int
    {
        $environment = (string) $this->option('environment');
        if (! in_array($environment, ['sandbox', 'production'], true)) {
            $this->error('--environment must be sandbox or production.');

            return self::FAILURE;
        }

        $branch = Branch::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('code', $this->argument('branch'))->orWhere('id', $this->argument('branch')))
            ->when($this->option('organisation'), fn ($q, $organisationId) => $q->where('organisation_id', $organisationId))
            ->get();
        if ($branch->count() !== 1) {
            $this->error($branch->isEmpty() ? 'No such branch.' : 'That branch code exists in more than one institution; pass --organisation.');

            return self::FAILURE;
        }
        $branch = $branch->first();

        $existing = EtimsDevice::withoutGlobalScopes()->where('branch_id', $branch->id)->first();
        $pin = strtoupper(trim((string) ($this->option('pin') ?: $existing?->tin)));
        $serial = trim((string) ($this->option('serial') ?: $existing?->device_serial));
        if ($pin === '' || $serial === '') {
            $this->error('Pass --pin and --serial from KRA\'s approval.');

            return self::FAILURE;
        }

        $changed = ! $existing || $existing->tin !== $pin || $existing->device_serial !== $serial
            || $existing->bhf_id !== (string) $this->option('bhf') || $existing->environment !== $environment;

        $device = EtimsDevice::withoutGlobalScopes()->updateOrCreate(['branch_id' => $branch->id], [
            'organisation_id' => $branch->organisation_id,
            'environment' => $environment,
            'tin' => $pin,
            'bhf_id' => (string) $this->option('bhf'),
            'device_serial' => $serial,
        ] + ($changed ? ['cmc_key' => null, 'status' => EtimsDevice::PENDING, 'initialised_at' => null] : []));

        if ($device->isActive()) {
            $this->info("{$branch->name} is already initialised with device {$serial}.");

            return self::SUCCESS;
        }

        $this->line("Initialising device {$serial} for {$branch->name} with KRA ({$environment})…");
        try {
            $catalogue->initialise($device);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        AuditLog::record('ETIMS_DEVICE_INITIALISED', 'branch', $branch->id, [
            'reference' => $branch->code, 'after_json' => ['pin' => $pin, 'bhf_id' => $device->bhf_id, 'serial' => $serial, 'environment' => $environment],
        ]);
        $this->info('Initialised. KRA issued the communication key; it is stored encrypted.');

        return self::SUCCESS;
    }
}
