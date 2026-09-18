<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Part 18 — users, roles and permissions; Part 17 — branches, stores and
 * settings; Part 19 — the audit log. Role definitions are global, role
 * assignments are per branch (the Spatie "team" is the branch).
 */
class AdminController extends ApiController
{
    /** The settings the application resolves, with their defaults, so the screen can show every knob (Part 17.4). */
    public const SETTING_CATALOGUE = [
        ['scope' => 'pos', 'key' => 'default_sale_mode', 'type' => 'string', 'default' => null, 'description' => 'Sale mode a terminal opens in; leaving it empty uses the first mode the branch trades in.'],
        ['scope' => 'pricing', 'key' => 'quote_ttl_minutes', 'type' => 'integer', 'default' => 15, 'description' => 'How long a server price quote stays valid before checkout must re-quote.'],
        ['scope' => 'pricing', 'key' => 'round_to', 'type' => 'string', 'default' => 'NONE', 'description' => 'Rounding applied to quoted unit prices: NONE, 0.05, 0.10, 0.50 or 1.'],
        ['scope' => 'pricing', 'key' => 'default_max_discount_pct', 'type' => 'decimal', 'default' => '0', 'description' => 'Ceiling for line discounts when no product or tier policy says otherwise.'],
        ['scope' => 'pricing', 'key' => 'default_discount_approval_pct', 'type' => 'decimal', 'default' => null, 'description' => 'Discount percentage above which a second approver is required.'],
        ['scope' => 'pricing', 'key' => 'default_min_margin_pct', 'type' => 'decimal', 'default' => '0', 'description' => 'Minimum gross margin a quote may fall to before it is refused.'],
        ['scope' => 'sales', 'key' => 'min_shelf_life_days', 'type' => 'integer', 'default' => 90, 'description' => 'FEFO will not allocate a batch expiring sooner than this.'],
        ['scope' => 'sales', 'key' => 'min_shelf_life_days_institutional', 'type' => 'integer', 'default' => 180, 'description' => 'Shelf-life floor for hospital, tender and institutional customers.'],
        ['scope' => 'inventory', 'key' => 'adjustment_approval_threshold', 'type' => 'decimal', 'default' => '10000', 'description' => 'Adjustment value (at cost) above which a second approver is required.'],
        ['scope' => 'inventory', 'key' => 'count_variance_approval_threshold', 'type' => 'decimal', 'default' => '10000', 'description' => 'Stock-count variance value above which a second approver is required.'],
    ];

    // ---- Users -------------------------------------------------------------

    public function users(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');

        $term = trim((string) $request->input('q', ''));
        $users = User::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')->paginate($request->integer('per_page', 50));

        $assignments = $this->assignmentsFor($users->getCollection()->pluck('id')->all());
        $users->getCollection()->transform(fn (User $u) => $this->userPayload($u, $assignments[$u->id] ?? []));

        return response()->json($users);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:12'],
            'must_change_password' => ['nullable', 'boolean'],
        ] + $this->assignmentRules());

        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'username', 'email', 'phone', 'password'])->all());
            // A password issued by an administrator is temporary by default (Part 18.2).
            $user->forceFill(['is_active' => true, 'must_change_password' => $data['must_change_password'] ?? true])->save();
            $this->syncAssignments($user, $data['assignments'] ?? []);

            AuditLog::record('USER_CREATED', 'user', (string) $user->id, [
                'reference' => $user->email,
                'after_json' => ['assignments' => $data['assignments'] ?? []],
            ]);

            return $user;
        });

        return response()->json($this->userPayload($user, ($this->assignmentsFor([$user->id])[$user->id] ?? [])), 201);
    }

    public function updateUser(Request $request, int $user): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');
        $user = User::findOrFail($user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:12'],
            'reset_mfa' => ['nullable', 'boolean'],
        ] + $this->assignmentRules());

        if ($user->id === $request->user()->id && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return $this->error('SELF_DEACTIVATION', 'You cannot deactivate your own account.', 422);
        }

        $audited = ['name', 'email', 'phone', 'is_active', 'must_change_password', 'mfa_required'];
        $before = $user->only($audited) + ['assignments' => ($this->assignmentsFor([$user->id])[$user->id] ?? [])];

        DB::transaction(function () use ($user, $data) {
            $user->fill(collect($data)->only(['name', 'email', 'phone'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = $data['must_change_password'] ?? true;
            }
            foreach (['is_active', 'must_change_password'] as $flag) {
                if (array_key_exists($flag, $data)) {
                    $user->{$flag} = $data[$flag];
                }
            }
            if ($data['reset_mfa'] ?? false) {
                // The next sign-in goes straight through; the user re-enrols from Security.
                $user->forceFill(['mfa_secret' => null, 'mfa_required' => false]);
            }
            $user->save();

            if (array_key_exists('assignments', $data)) {
                $this->syncAssignments($user, $data['assignments'] ?? []);
            }
        });

        $user = $user->fresh();
        $assignments = ($this->assignmentsFor([$user->id])[$user->id] ?? []);
        AuditLog::record('USER_UPDATED', 'user', (string) $user->id, [
            'reference' => $user->email,
            'before_json' => $before,
            'after_json' => $user->only($audited) + ['assignments' => $assignments],
            'changed_fields' => array_keys($data),
        ]);

        return response()->json($this->userPayload($user, $assignments));
    }

    /** POST /api/admin/users/{id}/unlock — clears a lockout (Part 18.2) without touching the password. */
    public function unlockUser(Request $request, int $user): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');
        $user = User::findOrFail($user);
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        AuditLog::record('USER_UNLOCKED', 'user', (string) $user->id, ['reference' => $user->email]);

        return response()->json($this->userPayload($user->fresh(), ($this->assignmentsFor([$user->id])[$user->id] ?? [])));
    }

    // ---- Roles and permissions --------------------------------------------

    public function roles(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $names = config('permission.table_names');
        $usersPerRole = DB::table($names['model_has_roles'])->where('model_type', User::class)
            ->selectRaw('role_id, COUNT(DISTINCT model_id) as users')->groupBy('role_id')->pluck('users', 'role_id');

        $roles = Role::where('guard_name', 'web')->whereNull(config('permission.column_names.team_foreign_key'))
            ->with('permissions')->orderBy('name')->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
                'users_count' => (int) ($usersPerRole[$role->id] ?? 0),
            ]);

        return response()->json($roles->values());
    }

    public function storeRole(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        AuditLog::record('ROLE_CREATED', 'role', (string) $role->id, ['reference' => $role->name, 'after_json' => ['permissions' => $data['permissions'] ?? []]]);

        return response()->json(['id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->sort()->values(), 'users_count' => 0], 201);
    }

    /** PATCH /api/admin/roles/{id} — replace a role's permission set; takes effect for every holder at once. */
    public function updateRole(Request $request, int $role): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $role = Role::where('guard_name', 'web')->findOrFail($role);

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $role->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $after = $role->permissions()->pluck('name')->sort()->values()->all();

        AuditLog::record('ROLE_PERMISSIONS_CHANGED', 'role', (string) $role->id, [
            'reference' => $role->name,
            'before_json' => ['permissions' => $before],
            'after_json' => ['permissions' => $after],
            'changed_fields' => array_merge(array_diff($before, $after), array_diff($after, $before)),
        ]);

        return response()->json(['id' => $role->id, 'name' => $role->name, 'permissions' => $after]);
    }

    /** GET /api/admin/permissions — the catalogue, grouped by its dotted prefix (Part 18.3). */
    public function permissions(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.users');

        $grouped = Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($names, $group) => ['group' => $group, 'permissions' => $names->values()])
            ->values();

        return response()->json($grouped);
    }

    // ---- Branches and stores ----------------------------------------------

    public function branches(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        return response()->json(
            Branch::where('organisation_id', $this->organisationId($request))->with(['stores' => fn ($q) => $q->orderBy('code')])->orderBy('code')->get()
        );
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisationId = $this->organisationId($request);

        $data = $request->validate($this->branchRules($organisationId, null));
        $branch = Branch::create($data + ['organisation_id' => $organisationId, 'is_active' => $data['is_active'] ?? true, 'created_by' => $request->user()->id]);
        AuditLog::record('BRANCH_CREATED', 'branch', $branch->id, ['reference' => $branch->code]);

        return response()->json($branch->load('stores'), 201);
    }

    public function updateBranch(Request $request, string $branch): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisationId = $this->organisationId($request);
        $branch = Branch::where('organisation_id', $organisationId)->findOrFail($branch);

        $data = $request->validate($this->branchRules($organisationId, $branch));
        $before = $branch->only(['name', 'address', 'county', 'is_active', 'retail_enabled', 'wholesale_enabled', 'dispensing_enabled']);
        $branch->update($data + ['updated_by' => $request->user()->id]);
        AuditLog::record('BRANCH_UPDATED', 'branch', $branch->id, ['reference' => $branch->code, 'before_json' => $before, 'after_json' => $branch->fresh()->only(array_keys($before)), 'changed_fields' => array_keys($data)]);

        return response()->json($branch->fresh()->load('stores'));
    }

    public function storeStore(Request $request, string $branch): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $branch = Branch::where('organisation_id', $this->organisationId($request))->findOrFail($branch);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('stores', 'code')->where('branch_id', $branch->id)],
            'name' => ['required', 'string', 'max:100'],
            'store_type' => ['required', 'in:MAIN,COLD,QUARANTINE,RETAIL,TRANSIT,DISPENSARY'],
            'storage_condition_id' => ['nullable', 'uuid', 'exists:storage_conditions,id'],
            'is_sellable' => ['nullable', 'boolean'],
        ]);

        $store = Store::create($data + ['branch_id' => $branch->id, 'is_sellable' => $data['is_sellable'] ?? false, 'created_by' => $request->user()->id]);
        AuditLog::record('STORE_CREATED', 'store', $store->id, ['reference' => "{$branch->code}/{$store->code}"]);

        return response()->json($store, 201);
    }

    // ---- Settings and number sequences ------------------------------------

    /** GET /api/admin/settings — the catalogue merged with the value in force for the active branch, and where it comes from. */
    public function settings(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisationId = $this->organisationId($request);
        $branchId = $this->branchId($request);

        $rows = collect(self::SETTING_CATALOGUE)->map(function (array $item) use ($organisationId, $branchId) {
            $branchRow = $this->settingRow($organisationId, $branchId, $item['scope'], $item['key']);
            $orgRow = $this->settingRow($organisationId, null, $item['scope'], $item['key']);
            $effective = $branchRow ?? $orgRow;

            return $item + [
                'value' => $effective->value_json ?? $item['default'],
                'source' => $branchRow ? 'branch' : ($orgRow ? 'organisation' : 'default'),
                'organisation_value' => $orgRow?->value_json,
                'branch_value' => $branchRow?->value_json,
                'set_at' => $effective?->set_at,
                'set_by' => $effective?->set_by,
            ];
        });

        return response()->json(['branch_id' => $branchId, 'data' => $rows->values()]);
    }

    /**
     * PUT /api/admin/settings — writes a new effective-dated row (Part 17.4: settings are versioned, never edited in place)
     * for the organisation, or for the active branch when branch_scoped is true.
     */
    public function putSetting(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisationId = $this->organisationId($request);

        $data = $request->validate([
            'scope' => ['required', 'string', 'max:40'],
            'key' => ['required', 'string', 'max:80'],
            'value' => ['present'],
            'branch_scoped' => ['nullable', 'boolean'],
        ]);
        $branchId = ($data['branch_scoped'] ?? false) ? $this->branchId($request) : null;
        $today = now()->toDateString();

        $setting = DB::transaction(function () use ($organisationId, $branchId, $data, $today, $request) {
            $current = $this->settingRow($organisationId, $branchId, $data['scope'], $data['key']);
            $before = $current?->value_json;

            if ($current && Carbon::parse($current->effective_from)->toDateString() === $today) {
                $current->update(['value_json' => $data['value'], 'set_by' => $request->user()->id, 'set_at' => now()]);
                $setting = $current;
            } else {
                $current?->update(['effective_to' => now()->subDay()->toDateString()]);
                $setting = Setting::create([
                    'organisation_id' => $organisationId, 'branch_id' => $branchId,
                    'scope' => $data['scope'], 'key' => $data['key'], 'value_json' => $data['value'],
                    'effective_from' => $today, 'set_by' => $request->user()->id, 'set_at' => now(),
                ]);
            }

            AuditLog::record('SETTING_CHANGED', 'setting', $setting->id, [
                'reference' => "{$data['scope']}.{$data['key']}".($branchId ? ' (branch)' : ''),
                'before_json' => ['value' => $before],
                'after_json' => ['value' => $data['value']],
            ]);

            return $setting;
        });

        return response()->json($setting);
    }

    public function numberSequences(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        return response()->json(
            NumberSequence::where('organisation_id', $this->organisationId($request))
                ->orderBy('scope')->orderByDesc('fiscal_year')->get()
        );
    }

    // ---- Audit log --------------------------------------------------------

    public function auditLog(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'audit.view');

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'entity_type' => ['nullable', 'string', 'max:60'],
            'entity_id' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'all_branches' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $rows = AuditLog::query()
            ->when(! ($filters['all_branches'] ?? false), fn ($q) => $q->where(fn ($w) => $w->where('branch_id', $this->branchId($request))->orWhereNull('branch_id')))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['entity_type'] ?? null, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($filters['entity_id'] ?? null, fn ($q, $v) => $q->where('entity_id', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$v}%")->orWhere('reason', 'like', "%{$v}%")->orWhere('username_snapshot', 'like', "%{$v}%")))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '<=', $v))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 50);

        return response()->json($rows->toArray() + [
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function assignmentRules(): array
    {
        return [
            'assignments' => ['nullable', 'array'],
            'assignments.*.branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'assignments.*.roles' => ['required', 'array', 'min:1'],
            'assignments.*.roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @param  list<array{branch_id: string, roles: list<string>}>  $assignments
     */
    private function syncAssignments(User $user, array $assignments): void
    {
        $names = config('permission.table_names');
        $registrar = app(PermissionRegistrar::class);

        DB::table($names['model_has_roles'])->where('model_type', User::class)->where('model_id', $user->id)->delete();
        foreach ($assignments as $assignment) {
            $registrar->setPermissionsTeamId($assignment['branch_id']);
            $user->unsetRelation('roles')->assignRole($assignment['roles']);
        }
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, list<array{branch_id: string, branch_code: string|null, role: string}>>
     */
    private function assignmentsFor(array $userIds): array
    {
        $names = config('permission.table_names');
        $team = config('permission.column_names.team_foreign_key');

        $rows = DB::table("{$names['model_has_roles']} as mr")
            ->join("{$names['roles']} as r", 'r.id', '=', 'mr.role_id')
            ->leftJoin('branches as b', 'b.id', '=', "mr.{$team}")
            ->where('mr.model_type', User::class)->whereIn('mr.model_id', $userIds)
            ->orderBy('b.code')->orderBy('r.name')
            ->get(['mr.model_id', "mr.{$team} as branch_id", 'b.code as branch_code', 'r.name as role']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->model_id][] = ['branch_id' => (string) $row->branch_id, 'branch_code' => $row->branch_code === null ? null : (string) $row->branch_code, 'role' => (string) $row->role];
        }

        return $out;
    }

    /**
     * @param  list<array{branch_id: string, branch_code: string|null, role: string}>  $assignments
     * @return array<string, mixed>
     */
    private function userPayload(User $user, array $assignments): array
    {
        return $user->only(['id', 'name', 'username', 'email', 'phone', 'is_active', 'mfa_required', 'must_change_password', 'failed_login_attempts', 'locked_until', 'last_login_at', 'created_at'])
            + ['assignments' => $assignments];
    }

    private function settingRow(string $organisationId, ?string $branchId, string $scope, string $key): ?Setting
    {
        $today = now()->toDateString();

        return Setting::query()
            ->where('organisation_id', $organisationId)->where('branch_id', $branchId)
            ->where('scope', $scope)->where('key', $key)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderByDesc('effective_from')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function branchRules(string $organisationId, ?Branch $existing): array
    {
        $required = $existing ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:10', Rule::unique('branches', 'code')->where('organisation_id', $organisationId)->ignore($existing?->id)],
            'name' => [$required, 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'retail_enabled' => ['sometimes', 'boolean'],
            'wholesale_enabled' => ['sometimes', 'boolean'],
            'dispensing_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
