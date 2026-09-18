<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Part 18.3 — the first account has to come from somewhere other than the
 * application it administers. This creates a user holding the blueprint's
 * "System Administrator" role (users, settings, audit log — no clinical or
 * financial posting rights) in the chosen branches.
 *
 * --full-access additionally grants a "Super Administrator" role that
 * carries every permission. That deliberately bypasses separation of
 * duties: it exists for initial setup and development, and should be
 * removed from real people before go-live.
 */
class CreateAdminUser extends Command
{
    public const SYSTEM_ADMINISTRATOR = 'System Administrator';

    public const SUPER_ADMINISTRATOR = 'Super Administrator';

    protected $signature = 'user:create-admin
        {email : The email address the administrator signs in with}
        {--name= : Display name (defaults to "System Administrator")}
        {--username= : Username (defaults to the part of the email before @)}
        {--password= : Password, at least 12 characters (a strong one is generated and shown once when omitted)}
        {--branch=* : Branch code(s) to grant access to (defaults to every active branch)}
        {--full-access : Also grant the Super Administrator role carrying every permission (setup and development only)}';

    protected $description = 'Create an administrator account and assign its roles per branch (Part 18.3)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $username = (string) ($this->option('username') ?: Str::before($email, '@'));
        $generated = ! $this->option('password');
        $password = $generated ? Str::password(20, symbols: false) : (string) $this->option('password');

        $validator = Validator::make(
            ['email' => $email, 'username' => $username, 'password' => $password],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'username' => ['required', 'string', 'max:50', 'unique:users,username'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $codes = array_filter((array) $this->option('branch'));
        $branches = Branch::query()->where('is_active', true)->when($codes, fn ($q) => $q->whereIn('code', $codes))->orderBy('code')->get();
        if ($branches->isEmpty() || ($codes && $branches->count() !== count($codes))) {
            $this->error($codes ? 'Unknown or inactive branch code: '.implode(', ', array_diff($codes, $branches->pluck('code')->all())) : 'No active branch exists yet; run the OrganisationSeeder first.');

            return self::FAILURE;
        }

        $sysAdmin = Role::where('name', self::SYSTEM_ADMINISTRATOR)->where('guard_name', 'web')->whereNull('branch_id')->first();
        if (! $sysAdmin) {
            $this->error('The "'.self::SYSTEM_ADMINISTRATOR.'" role does not exist; run the PermissionSeeder and RoleSeeder first.');

            return self::FAILURE;
        }

        $registrar = app(PermissionRegistrar::class);

        $user = DB::transaction(function () use ($email, $username, $password, $branches, $sysAdmin, $registrar) {
            $user = User::create([
                'name' => (string) ($this->option('name') ?: 'System Administrator'),
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ]);
            $user->forceFill(['is_active' => true, 'email_verified_at' => now()])->save();

            $roles = [$sysAdmin];
            if ($this->option('full-access')) {
                $registrar->setPermissionsTeamId(null);
                $super = Role::firstOrCreate(['name' => self::SUPER_ADMINISTRATOR, 'guard_name' => 'web', 'branch_id' => null]);
                $super->syncPermissions(Permission::where('guard_name', 'web')->get());
                $roles[] = $super;
            }

            // Role definitions are global; the assignment is per branch (Part 18.2).
            foreach ($branches as $branch) {
                $registrar->setPermissionsTeamId($branch->id);
                $user->assignRole($roles);
            }
            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();

            AuditLog::record('USER_CREATED', 'user', (string) $user->id, [
                'reference' => $user->email,
                'reason' => 'Created from the console (user:create-admin)',
                'after_json' => ['roles' => collect($roles)->pluck('name')->all(), 'branches' => $branches->pluck('code')->all()],
            ]);

            return $user;
        });

        $this->info("Administrator {$user->email} created.");
        $this->table(['Field', 'Value'], [
            ['Name', $user->name],
            ['Email', $user->email],
            ['Username', $user->username],
            ['Password', $generated ? $password : '(as supplied)'],
            ['Branches', $branches->pluck('code')->implode(', ')],
            ['Roles', $this->option('full-access') ? self::SYSTEM_ADMINISTRATOR.', '.self::SUPER_ADMINISTRATOR : self::SYSTEM_ADMINISTRATOR],
        ]);
        if ($generated) {
            $this->warn('The password is shown once and stored only as a hash. Change it after the first sign-in.');
        }
        if ($this->option('full-access')) {
            $this->warn('Super Administrator carries every permission and bypasses separation of duties. Remove it from real people before go-live.');
        }

        return self::SUCCESS;
    }
}
