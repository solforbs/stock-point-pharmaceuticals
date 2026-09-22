<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\Alerts\AlertDigestMailer;
use App\Services\Alerts\AlertScanner;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Part 17 — rebuilds the standing alerts (payment deadlines and shelf-life
 * risk) for every branch. Runs each morning before the counter opens, and
 * is safe to run by hand at any time: it is a recompute, not an append.
 */
class ScanAlerts extends Command
{
    protected $signature = 'alerts:scan
        {--branch= : Limit the scan to one branch code}
        {--no-mail : Recompute the alerts without sending the digest}';

    protected $description = 'Recompute payment-deadline and expiry alerts for every branch (Part 17)';

    public function handle(AlertScanner $scanner, AlertDigestMailer $mailer, TenantContext $tenant): int
    {
        $branches = Branch::query()
            ->when($this->option('branch'), fn ($q, $code) => $q->where('code', $code))
            ->orderBy('code')
            ->get();

        if ($branches->isEmpty()) {
            $this->error('No branch matched.');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($branches as $branch) {
            // Each branch is scanned as its own institution.
            [$result, $mailed] = $tenant->run($branch->organisation_id, fn () => [
                $scanner->scan($branch),
                $this->option('no-mail') ? ['sent' => '—'] : $mailer->send($branch),
            ]);
            $rows[] = [$branch->code, $result['opened'], $result['refreshed'], $result['resolved'], $mailed['sent']];
        }

        $this->table(['Branch', 'Opened', 'Still true', 'Resolved', 'Digests sent'], $rows);

        return self::SUCCESS;
    }
}
