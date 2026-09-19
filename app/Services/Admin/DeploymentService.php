<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Part 17 — operations: what version is running, what is waiting on GitHub,
 * and starting the deploy that closes the gap.
 *
 * Nothing the browser sends ever reaches a shell. The only command this can
 * run is `deploy/deploy.sh`, with no arguments; the buttons choose between
 * fixed actions, never compose one. The script runs detached so it survives
 * the request that started it, writing to a log the screen then follows.
 */
class DeploymentService
{
    /** How long a run may sit unfinished before it is treated as dead. */
    private const STALE_AFTER_MINUTES = 30;

    public function root(): string
    {
        return (string) config('deployment.root', base_path());
    }

    public function branch(): string
    {
        return (string) config('deployment.branch', 'main');
    }

    private function logDirectory(): string
    {
        $path = storage_path('app/private/deployments');
        File::ensureDirectoryExists($path);

        return $path;
    }

    /**
     * The running version, and how far behind the branch it is.
     *
     * @return array{
     *     available: bool, branch: string, commit: ?string, subject: ?string, committed_at: ?string,
     *     author: ?string, dirty: bool, behind: int, incoming: list<array{sha: string, subject: string}>,
     *     fetched_at: ?string, deploy: array<string, mixed>
     * }
     */
    public function status(): array
    {
        $git = $this->git(['log', '-1', '--pretty=%h%x1f%s%x1f%cI%x1f%an']);
        $available = $git !== null;
        [$commit, $subject, $committedAt, $author] = $available ? array_pad(explode("\x1f", trim($git)), 4, null) : [null, null, null, null];

        $behindRaw = $this->git(['rev-list', '--count', 'HEAD..origin/'.$this->branch()]);
        $incoming = [];
        if ($behindRaw !== null && (int) trim($behindRaw) > 0) {
            $lines = (string) $this->git(['log', '--pretty=%h%x1f%s', '-20', 'HEAD..origin/'.$this->branch()]);
            foreach (array_filter(explode("\n", trim($lines))) as $line) {
                [$sha, $message] = array_pad(explode("\x1f", $line), 2, '');
                $incoming[] = ['sha' => $sha, 'subject' => $message];
            }
        }

        $fetchHead = $this->root().'/.git/FETCH_HEAD';

        return [
            'available' => $available,
            'branch' => $this->branch(),
            'commit' => $commit,
            'subject' => $subject,
            'committed_at' => $committedAt,
            'author' => $author,
            'dirty' => trim((string) $this->git(['status', '--porcelain'])) !== '',
            'behind' => (int) trim((string) ($behindRaw ?? '0')),
            'incoming' => $incoming,
            'fetched_at' => is_file($fetchHead) ? Carbon::createFromTimestamp((int) filemtime($fetchHead))->toIso8601String() : null,
            'deploy' => $this->lastRun(),
        ];
    }

    /**
     * Asks GitHub what is new. Read-only: it fetches, it never moves HEAD.
     *
     * @return array{ok: bool, message: string}
     */
    public function fetch(): array
    {
        $process = Process::fromShellCommandline(
            (string) config('deployment.fetch_command', 'sudo -n /usr/local/bin/stockpoint-fetch'),
            $this->root(), null, null, 120
        );
        $process->run();

        if (! $process->isSuccessful()) {
            return ['ok' => false, 'message' => trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'git fetch failed.'];
        }

        return ['ok' => true, 'message' => 'Up to date with GitHub.'];
    }

    /**
     * Starts a deploy in the background and returns its run id. Refuses while
     * one is already running, so two administrators cannot deploy at once.
     *
     * @throws DeploymentFailedException
     */
    public function start(int $userId): string
    {
        if ($this->lastRun()['status'] === 'running') {
            throw new DeploymentFailedException('A deploy is already running; wait for it to finish.');
        }

        $script = $this->root().'/deploy/deploy.sh';
        if (! is_file($script)) {
            throw new DeploymentFailedException("deploy/deploy.sh is missing from {$this->root()}.");
        }

        $runId = now()->format('Ymd-His');
        $log = $this->logDirectory().'/deploy-'.$runId.'.log';
        $startedAt = now()->toIso8601String();
        $command = (string) config('deployment.command', 'sudo -n /usr/local/bin/stockpoint-deploy');

        $this->writeState(['run_id' => $runId, 'started_at' => $startedAt, 'started_by' => $userId, 'exit_code' => null, 'finished_at' => null]);

        // The deploy is detached so it outlives the request that asked for it.
        // Its output goes to the log the screen tails, and its exit code is
        // written back to the state file — so a failure is visible even though
        // nobody was watching when it happened.
        $record = sprintf(
            'printf %s "$code" "$(date -Iseconds)" > %s',
            escapeshellarg('{"run_id":"'.$runId.'","started_at":"'.$startedAt.'","started_by":'.$userId.',"exit_code":%s,"finished_at":"%s"}'),
            escapeshellarg($this->statePath())
        );

        $inner = sprintf('%s >> %s 2>&1; code=$?; %s', $command, escapeshellarg($log), $record);
        $shell = sprintf('nohup setsid bash -c %s > /dev/null 2>&1 &', escapeshellarg($inner));

        Process::fromShellCommandline($shell, $this->root(), null, null, 10)->run();

        return $runId;
    }

    /**
     * @return array{run_id: ?string, status: string, started_at: ?string, finished_at: ?string, exit_code: ?int, started_by: ?int, log: string}
     */
    public function lastRun(int $logLines = 400): array
    {
        $empty = ['run_id' => null, 'status' => 'never', 'started_at' => null, 'finished_at' => null, 'exit_code' => null, 'started_by' => null, 'log' => ''];
        if (! is_file($this->statePath())) {
            return $empty;
        }

        /** @var array<string, mixed>|null $state */
        $state = json_decode((string) File::get($this->statePath()), true);
        if (! is_array($state)) {
            return $empty;
        }

        $state = array_merge($empty, $state);

        // Status is derived, never stored: the deploy records only its exit
        // code, so the two can never disagree.
        $state['exit_code'] = $state['exit_code'] === null ? null : (int) $state['exit_code'];
        $state['status'] = match (true) {
            $state['finished_at'] !== null => $state['exit_code'] === 0 ? 'succeeded' : 'failed',
            // A run whose recorder never fired (the server rebooted mid-deploy)
            // must not leave the screen saying "running" for ever.
            $state['started_at'] !== null && Carbon::parse((string) $state['started_at'])->diffInMinutes(now()) > self::STALE_AFTER_MINUTES => 'unknown',
            default => 'running',
        };

        $log = $this->logDirectory().'/deploy-'.$state['run_id'].'.log';
        $state['log'] = is_file($log) ? $this->tail($log, $logLines) : '';

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function writeState(array $state): void
    {
        File::put($this->statePath(), (string) json_encode($state, JSON_PRETTY_PRINT));
    }

    private function statePath(): string
    {
        return $this->logDirectory().'/last-run.json';
    }

    private function tail(string $path, int $lines): string
    {
        $content = (string) File::get($path);
        $split = explode("\n", $content);

        return implode("\n", array_slice($split, -$lines));
    }

    /** Runs a read-only git command, or null when git or the repository is unavailable. */
    private function git(array $arguments): ?string
    {
        $process = new Process(['git', ...$arguments], $this->root(), null, null, 30);
        $process->run();

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
