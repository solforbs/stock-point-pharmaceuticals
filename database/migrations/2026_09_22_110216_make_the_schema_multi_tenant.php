<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One database, many institutions. Each organisation is a tenant: users and
 * roles now belong to one, the shared reference lists (units, dosage forms,
 * storage conditions, categories, manufacturers) hold shared rows (NULL
 * organisation) beside each tenant's private additions, and every unique
 * key that was global — document numbers, SKUs, codes — is now unique per
 * tenant or per branch, so two institutions can never collide.
 *
 * Existing data is back-filled so the current single organisation becomes
 * tenant #1 unchanged. If more than one organisation already exists, each
 * one gets its own copy of every role its users hold.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> table => columns that make doc_number unique */
    private const DOC_NUMBER_SCOPES = [
        'stock_transfers' => ['from_store_id'],
        'stock_counts' => ['store_id'],
        'stock_adjustments' => ['store_id'],
        'requisitions' => ['branch_id'],
        'purchase_orders' => ['branch_id'],
        'goods_receipts' => ['branch_id'],
        'supplier_invoices' => ['branch_id'],
        'supplier_returns' => ['branch_id'],
        'journal_entries' => ['organisation_id'],
        'sales' => ['branch_id'],
        'quotations' => ['branch_id'],
        'sales_orders' => ['branch_id'],
        'picking_lists' => ['branch_id'],
        'delivery_notes' => ['branch_id'],
        'customer_returns' => ['branch_id'],
        'waste_disposals' => ['branch_id'],
        'recalls' => ['organisation_id'],
        'adr_reports' => ['branch_id'],
        'payroll_runs' => ['branch_id'],
        'leave_requests' => ['organisation_id'],
    ];

    private const LOOKUPS = ['units_of_measure', 'dosage_forms', 'product_categories', 'manufacturers'];

    /** The standard storage conditions every tenant shares (StorageConditionSeeder). */
    private const SHARED_STORAGE_CODES = ['AMBIENT', 'ROOM', 'COOL', 'COLD', 'FROZEN'];

    public function up(): void
    {
        $primaryOrg = DB::table('organisations')->orderBy('created_at')->value('id');

        // Users belong to one institution; platform admins run the service itself.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('organisation_id')->nullable()->after('id')->constrained('organisations')->cascadeOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('is_active');
        });

        // Roles are per institution, so one tenant editing "Cashier" never changes another's.
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignUuid('organisation_id')->nullable()->after('id')->constrained('organisations')->cascadeOnDelete();
        });

        // Shared reference rows (NULL) beside each tenant's own.
        foreach (self::LOOKUPS as $lookup) {
            Schema::table($lookup, function (Blueprint $table) {
                $table->foreignUuid('organisation_id')->nullable()->after('id')->constrained('organisations')->cascadeOnDelete();
                $table->dropUnique(['code']);
                $table->unique(['organisation_id', 'code']);
            });
        }
        Schema::table('storage_conditions', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropUnique(['code']);
        });
        Schema::table('storage_conditions', function (Blueprint $table) {
            $table->uuid('organisation_id')->nullable()->change();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unique(['organisation_id', 'code']);
        });

        // Audit rows without a branch (logins, admin actions) still need an owner.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignUuid('organisation_id')->nullable()->after('id')->constrained('organisations')->cascadeOnDelete();
            $table->index(['organisation_id', 'occurred_at']);
        });

        if ($primaryOrg) {
            $this->backfill($primaryOrg);
        }

        $this->rescopeUniqueKeys();
    }

    private function backfill(string $primaryOrg): void
    {
        $pivot = config('permission.table_names.model_has_roles');
        $userModel = 'App\\Models\\User';

        // A user's institution is the one their branch roles sit in.
        foreach (DB::table('users')->pluck('id') as $userId) {
            $orgId = DB::table($pivot)
                ->join('branches', 'branches.id', '=', "{$pivot}.branch_id")
                ->where("{$pivot}.model_id", $userId)->where("{$pivot}.model_type", $userModel)
                ->value('branches.organisation_id');
            DB::table('users')->where('id', $userId)->update(['organisation_id' => $orgId ?? $primaryOrg]);
        }

        // Whoever held the platform-wide "Super Administrator" role runs the platform.
        $superRoleIds = DB::table('roles')->where('name', 'Super Administrator')->pluck('id');
        if ($superRoleIds->isNotEmpty()) {
            $userIds = DB::table($pivot)->whereIn('role_id', $superRoleIds)->where('model_type', $userModel)->pluck('model_id');
            DB::table('users')->whereIn('id', $userIds)->update(['is_platform_admin' => true]);
        }

        DB::table('roles')->update(['organisation_id' => $primaryOrg]);
        $this->cloneRolesForOtherOrganisations($primaryOrg, $pivot);

        // Categories and manufacturers were entered by the business, not seeded standards.
        DB::table('product_categories')->update(['organisation_id' => $primaryOrg]);
        DB::table('manufacturers')->update(['organisation_id' => $primaryOrg]);
        DB::table('storage_conditions')->whereIn('code', self::SHARED_STORAGE_CODES)->update(['organisation_id' => null]);

        DB::table('audit_logs')->whereNotNull('branch_id')->update([
            'organisation_id' => DB::raw('(SELECT b.organisation_id FROM branches b WHERE b.id = audit_logs.branch_id)'),
        ]);
        DB::table('audit_logs')->whereNull('organisation_id')->update(['organisation_id' => $primaryOrg]);
    }

    /**
     * Roles were global. Any other organisation whose users hold one gets its
     * own copy (with permissions and discount authority) and those
     * assignments are repointed to it.
     */
    private function cloneRolesForOtherOrganisations(string $primaryOrg, string $pivot): void
    {
        $others = DB::table('organisations')->where('id', '!=', $primaryOrg)->pluck('id');
        foreach ($others as $orgId) {
            $branchIds = DB::table('branches')->where('organisation_id', $orgId)->pluck('id');
            $roleIds = DB::table($pivot)->whereIn('branch_id', $branchIds)->distinct()->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $role = DB::table('roles')->where('id', $roleId)->first();
                $cloneId = DB::table('roles')->insertGetId([
                    'organisation_id' => $orgId, 'name' => $role->name, 'guard_name' => $role->guard_name,
                    'branch_id' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $permissionIds = DB::table('role_has_permissions')->where('role_id', $roleId)->pluck('permission_id');
                DB::table('role_has_permissions')->insert($permissionIds->map(fn ($p) => ['permission_id' => $p, 'role_id' => $cloneId])->all());

                $authority = DB::table('role_discount_authorities')->where('role_id', $roleId)->first();
                if ($authority) {
                    $copy = (array) $authority;
                    $copy['id'] = (string) Str::uuid();
                    $copy['role_id'] = $cloneId;
                    DB::table('role_discount_authorities')->insert($copy);
                }

                DB::table($pivot)->where('role_id', $roleId)->whereIn('branch_id', $branchIds)->update(['role_id' => $cloneId]);
            }
        }
    }

    private function rescopeUniqueKeys(): void
    {
        $teamKey = config('permission.column_names.team_foreign_key');
        Schema::table('roles', function (Blueprint $table) use ($teamKey) {
            $table->dropUnique([$teamKey, 'name', 'guard_name']);
            $table->unique(['organisation_id', $teamKey, 'name', 'guard_name']);
        });

        foreach (self::DOC_NUMBER_SCOPES as $tableName => $scope) {
            Schema::table($tableName, function (Blueprint $table) use ($scope) {
                $table->dropUnique(['doc_number']);
                $table->unique([...$scope, 'doc_number']);
            });
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['organisation_id', 'code']);
        });

        // SKUs are the business's own codes; barcodes are printed by the
        // manufacturer, so two pharmacies stocking the same box share one.
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->unique(['organisation_id', 'sku']);
        });
        Schema::table('product_uoms', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->index('barcode');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['employee_no']);
            $table->unique(['organisation_id', 'employee_no']);
        });

        Schema::table('customer_returns', function (Blueprint $table) {
            $table->dropUnique(['credit_note_number']);
            $table->unique(['branch_id', 'credit_note_number']);
        });

        foreach (['sales', 'sales_orders', 'delivery_notes', 'offline_sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['idempotency_key']);
                $table->unique(['organisation_id', 'idempotency_key']);
            });
        }
    }

    public function down(): void
    {
        foreach (['sales', 'sales_orders', 'delivery_notes', 'offline_sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['organisation_id', 'idempotency_key']);
                $table->unique('idempotency_key');
            });
        }
        Schema::table('customer_returns', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'credit_note_number']);
            $table->unique('credit_note_number');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'employee_no']);
            $table->unique('employee_no');
        });
        Schema::table('product_uoms', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->unique('barcode');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'sku']);
            $table->unique('sku');
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'code']);
            $table->unique('code');
        });
        foreach (self::DOC_NUMBER_SCOPES as $tableName => $scope) {
            Schema::table($tableName, function (Blueprint $table) use ($scope) {
                $table->dropUnique([...$scope, 'doc_number']);
                $table->unique('doc_number');
            });
        }

        $teamKey = config('permission.column_names.team_foreign_key');
        Schema::table('roles', function (Blueprint $table) use ($teamKey) {
            $table->dropUnique(['organisation_id', $teamKey, 'name', 'guard_name']);
            $table->unique([$teamKey, 'name', 'guard_name']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'occurred_at']);
            $table->dropConstrainedForeignId('organisation_id');
        });

        Schema::table('storage_conditions', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'code']);
            $table->unique('code');
        });

        foreach (self::LOOKUPS as $lookup) {
            Schema::table($lookup, function (Blueprint $table) {
                $table->dropUnique(['organisation_id', 'code']);
                $table->unique('code');
                $table->dropConstrainedForeignId('organisation_id');
            });
        }

        Schema::table('roles', fn (Blueprint $table) => $table->dropConstrainedForeignId('organisation_id'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
            $table->dropConstrainedForeignId('organisation_id');
        });
    }
};
