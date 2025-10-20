<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'expedientes.view',
            'expedientes.manage',
            'usuarios.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        $roles = [
            'admin' => $permissions,
            'coordinador' => ['expedientes.view', 'expedientes.manage'],
            'docente' => ['expedientes.view'],
            'alumno' => ['expedientes.view'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(
                Permission::query()->whereIn('name', $rolePermissions)->get()
            );
        }
    }
}
