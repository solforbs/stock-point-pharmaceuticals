<?php

namespace App\Services\Finance;

use App\Models\JournalEntry;
use App\Models\Setting;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tells finance when the ledger moved. Off by default: set
 * finance.posting_alert_threshold to an amount and every journal of at least
 * that value raises an in-app message (bell, and live over the websocket) for
 * everyone who may post journals in that branch; finance.posting_alert_email
 * adds an email. A failing alert never blocks the posting that caused it.
 */
class PostingAlerts
{
    public function __construct(private readonly Notifier $notifier) {}

    public function journalPosted(JournalEntry $journal, string $amount): void
    {
        if (! $journal->branch_id) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $team = $registrar->getPermissionsTeamId();

        try {
            $threshold = (string) Setting::resolve($journal->organisation_id, $journal->branch_id, 'finance', 'posting_alert_threshold', '0');
            if (! is_numeric($threshold) || bccomp($threshold, '0', 4) <= 0 || bccomp($amount, $threshold, 4) < 0) {
                return;
            }

            $subject = "Journal {$journal->doc_number} posted — KES ".number_format((float) $amount, 2);
            $body = trim("{$journal->narration}\nSource: ".str_replace('_', ' ', (string) ($journal->source_doc_type ?: 'manual')).'.');
            $link = '/finance/journals?q='.urlencode($journal->doc_number);

            $this->notifier->toPermission('journal.post', $journal->branch_id, $subject, $body, 'FINANCE', $link, 'NORMAL', $journal->posted_by);

            if ((bool) Setting::resolve($journal->organisation_id, $journal->branch_id, 'finance', 'posting_alert_email', false)) {
                foreach ($this->notifier->holdersOf('journal.post', $journal->branch_id, $journal->posted_by) as $user) {
                    if ($user->email) {
                        Mail::raw($body."\n\nOpen Finance → Journals to see the lines.", fn ($m) => $m->to($user->email, $user->name)->subject($subject));
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Posting alert failed', ['journal' => $journal->doc_number, 'error' => $e->getMessage()]);
        } finally {
            $registrar->setPermissionsTeamId($team);
        }
    }
}
