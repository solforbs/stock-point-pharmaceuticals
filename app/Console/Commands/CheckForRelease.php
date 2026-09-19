<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\UserMessage;
use App\Services\Admin\DeploymentService;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Command;

/**
 * Part 17 — watches GitHub for a newer release and tells whoever may deploy.
 *
 * Work continues on a version until it is tagged; the next version starts
 * from the next tag. When a tag newer than the running one appears, the
 * people who hold `deploy.run` are told once — not once a day until they
 * act.
 */
class CheckForRelease extends Command
{
    protected $signature = 'deploy:check-release {--notify=1 : Set to 0 to report without telling anyone}';

    protected $description = 'Check GitHub for a newer released version and notify whoever may deploy (Part 17)';

    public function handle(DeploymentService $deployments, Notifier $notifier): int
    {
        $fetch = $deployments->fetch();
        if (! $fetch['ok']) {
            $this->error($fetch['message']);

            return self::FAILURE;
        }

        $status = $deployments->status();
        $running = $status['version'] ?? '(untagged)';
        $latest = $status['latest_version'];

        if ($latest === null) {
            $this->line('No releases have been tagged yet.');

            return self::SUCCESS;
        }

        if (! $status['update_available']) {
            $this->info("Running the newest release ({$running}).");

            return self::SUCCESS;
        }

        $this->warn("Running {$running}; {$latest} is available.");

        if (! $this->option('notify')) {
            return self::SUCCESS;
        }

        $subject = "Version {$latest} is available";
        $told = 0;

        foreach (Branch::where('is_active', true)->get() as $branch) {
            // Told once per version: an unread notice about this same release
            // means they already know.
            $known = UserMessage::where('branch_id', $branch->id)
                ->where('category', 'RELEASE')->where('subject', $subject)->exists();

            if ($known) {
                continue;
            }

            $notes = collect($status['releases'])->firstWhere('version', $latest)['notes'] ?? '';

            $told += $notifier->toPermission(
                'deploy.run',
                $branch->id,
                $subject,
                "This server runs {$running}. ".trim($notes ?: 'A newer release has been published.')
                    .' Review it on the Deployments screen, then update when the shop is quiet.',
                category: 'RELEASE',
                link: '/admin/deployments',
                priority: 'NORMAL',
            );
        }

        $this->info("Told {$told} person(s).");

        return self::SUCCESS;
    }
}
