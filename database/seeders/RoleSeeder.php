<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the Shield permissions plus the default roles.
     *
     * - super_admin: full access (handled by Shield).
     * - operator: manages Buku Induk and master data, but not users or roles.
     */
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate(config('filament-shield.super_admin.name'), 'web');
        $superAdmin->syncPermissions(Permission::all());

        Role::findOrCreate('operator', 'web')->syncPermissions(
            Permission::query()
                ->where('name', 'not like', '%:User')
                ->where('name', 'not like', '%:Role')
                ->get(),
        );
    }
}
