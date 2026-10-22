<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The system administrators (super admins) who run the platform itself:
 * deployments (CI/CD), backups, system health and the institutions on it.
 * They are kept apart from the institutions' own staff, so a Director can
 * run the business but never deploy code.
 *
 *   php artisan platform:admin admin@example.com --name="System Administrator"
 *       creates a platform-only account (no institution, no institution role)
 *       and prints its one-time password; on an existing account it grants
 *       system-administrator rights.
 *   php artisan platform:admin director@example.com --revoke
 *       takes system-administrator rights away (the account keeps its
 *       institution role).
 */
class ManagePlatformAdmin extends Command
{
    protected $signature = 'platform:admin
        {email : The account\'s email address}
        {--name= : Display name for a new account (defaults to "System Administrator")}
        {--revoke : Remove system-administrator rights instead of granting them}
        {--list : List the current system administrators and stop}';

    protected $description = 'Create a system administrator (super admin) account, or grant or revoke system-administrator rights';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->table(['Name', 'Email', 'Institution', 'Active'], User::query()->where('is_platform_admin', true)->orderBy('name')->get()
                ->map(fn (User $u) => [$u->name, $u->email, $u->organisation_id ? 'yes (also institution staff)' : 'none (platform only)', $u->is_active ? 'yes' : 'no'])->all());

            return self::SUCCESS;
        }

        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($this->option('revoke')) {
            if (! $user || ! $user->is_platform_admin) {
                $this->warn("{$email} is not a system administrator.");

                return self::SUCCESS;
            }
            if (User::query()->where('is_platform_admin', true)->where('is_active', true)->whereKeyNot($user->id)->doesntExist()) {
                $this->error('This is the last active system administrator; create another one first, or nobody could run the platform.');

                return self::FAILURE;
            }
            $user->forceFill(['is_platform_admin' => false])->save();
            AuditLog::record('PLATFORM_ADMIN_REVOKED', 'user', (string) $user->id, ['reference' => $user->email]);
            $this->info("{$email} is no longer a system administrator.");

            return self::SUCCESS;
        }

        if ($user) {
            $user->forceFill(['is_platform_admin' => true])->save();
            AuditLog::record('PLATFORM_ADMIN_GRANTED', 'user', (string) $user->id, ['reference' => $user->email]);
            $this->info("{$email} is now a system administrator.");
            if ($user->organisation_id) {
                $this->warn('This account also belongs to an institution. To keep running the system apart from running the business, prefer a separate platform-only account.');
            }

            return self::SUCCESS;
        }

        $username = Str::before($email, '@');
        $validator = Validator::make(['email' => $email, 'username' => $username], [
            'email' => ['required', 'email'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $password = Str::password(20, symbols: false);
        $user = User::create([
            'name' => (string) ($this->option('name') ?: 'System Administrator'),
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill([
            'organisation_id' => null,
            'is_platform_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
        AuditLog::record('PLATFORM_ADMIN_CREATED', 'user', (string) $user->id, ['reference' => $user->email]);

        $this->info("System administrator {$email} created (platform only — no institution).");
        $this->line("One-time password: <comment>{$password}</comment>");
        $this->line('Sign in and change it straight away; it is not shown again.');

        return self::SUCCESS;
    }
}
