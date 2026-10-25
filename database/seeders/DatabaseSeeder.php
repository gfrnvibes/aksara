<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            MasterDataSeeder::class,
        ]);

        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => 'password', 'email_verified_at' => now()],
        )->assignRole(config('filament-shield.super_admin.name'));
    }
}
