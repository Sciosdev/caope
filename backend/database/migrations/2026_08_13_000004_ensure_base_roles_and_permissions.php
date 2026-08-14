<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const DEMO_EMAILS = [
        'admin@demo.local',
        'alumno@demo.local',
        'docente@demo.local',
        'coordinacion@demo.local',
        'paps@demo.local',
    ];

    /**
     * Ensure a fresh production database can authorize registration and the
     * core application without depending on demo seeders.
     */
    public function up(): void
    {
        $this->revokeHistoricalDemoAccounts();

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        throw_if(empty($tableNames), new RuntimeException('Permission table configuration is unavailable.'));

        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $guard = 'web';
        $now = now();
        $permissions = [
            'expedientes.view',
            'expedientes.manage',
            'usuarios.manage',
            'reportes.view',
            'sesiones.validate',
        ];
        $rolePermissions = [
            'admin' => $permissions,
            'coordinador' => ['expedientes.view', 'expedientes.manage', 'reportes.view', 'sesiones.validate'],
            'docente' => ['expedientes.view', 'sesiones.validate'],
            'estratega' => ['expedientes.view', 'sesiones.validate'],
            'alumno' => ['expedientes.view'],
            'paps' => $permissions,
            'developer' => [],
        ];

        DB::table($tableNames['permissions'])->insertOrIgnore(
            array_map(static fn (string $permission): array => [
                'name' => $permission,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ], $permissions)
        );

        DB::table($tableNames['roles'])->insertOrIgnore(
            array_map(static fn (string $role): array => [
                'name' => $role,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_keys($rolePermissions))
        );

        $permissionIds = DB::table($tableNames['permissions'])
            ->where('guard_name', $guard)
            ->whereIn('name', $permissions)
            ->pluck('id', 'name');
        $roleIds = DB::table($tableNames['roles'])
            ->where('guard_name', $guard)
            ->whereIn('name', array_keys($rolePermissions))
            ->pluck('id', 'name');
        $assignments = [];

        foreach ($rolePermissions as $role => $assignedPermissions) {
            foreach ($assignedPermissions as $permission) {
                $assignments[] = [
                    $pivotRole => $roleIds->get($role),
                    $pivotPermission => $permissionIds->get($permission),
                ];
            }
        }

        if ($assignments !== []) {
            DB::table($tableNames['role_has_permissions'])->insertOrIgnore($assignments);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Preserve foreign-key references while making historical demo accounts
     * unusable on installations that previously ran DatabaseSeeder.
     */
    private function revokeHistoricalDemoAccounts(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $accounts = DB::table('users')
            ->whereIn('email', self::DEMO_EMAILS)
            ->get(['id', 'email']);

        if ($accounts->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $account) {
                DB::table('users')
                    ->where('id', $account->id)
                    ->update([
                        'password' => Hash::make(Str::random(64)),
                        'remember_token' => null,
                        'is_active' => false,
                        'approved_at' => null,
                    ]);
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $accounts->pluck('id'))->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->whereIn('email', self::DEMO_EMAILS)->delete();
            }
        });
    }

    /**
     * Baseline authorization records may already be assigned to real users,
     * so rolling back must not delete them.
     */
    public function down(): void
    {
        // Intentionally preserved.
    }
};
