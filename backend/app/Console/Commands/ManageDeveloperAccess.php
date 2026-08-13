<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ManageDeveloperAccess extends Command
{
    protected $signature = 'caope:developer-access
        {email : Correo del usuario existente}
        {--revoke : Retirar el acceso en lugar de concederlo}';

    protected $description = 'Concede o retira el acceso a la consola técnica de CAOPE';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error('No existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $role = Role::query()->firstOrCreate([
            'name' => 'developer',
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->option('revoke')) {
            $user->removeRole($role);
            $this->info("Acceso de desarrollador retirado a {$user->email}.");

            return self::SUCCESS;
        }

        $user->assignRole($role);
        $this->info("Acceso de desarrollador concedido a {$user->email}.");

        return self::SUCCESS;
    }
}
