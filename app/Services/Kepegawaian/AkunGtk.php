<?php

namespace App\Services\Kepegawaian;

use App\Models\Gtk;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Login accounts for teachers and staff, created by an admin or operator with a starting password the teacher
 * must replace when first signing in.
 */
class AkunGtk
{
    /**
     * Whether the GTK has something to sign in with: an NIP, NUPTK, phone number or email address.
     */
    public static function punyaIdentitasLogin(Gtk $gtk): bool
    {
        return filled($gtk->nip) || filled($gtk->nuptk) || filled($gtk->nomor_hp) || filled($gtk->email);
    }

    /**
     * What the teacher types in the login form, in the order they are likely to remember it.
     *
     * @return list<string>
     */
    public static function identitasLogin(Gtk $gtk): array
    {
        return array_values(array_filter([$gtk->nip, $gtk->nuptk, $gtk->nomor_hp, $gtk->user->email ?? $gtk->email]));
    }

    public function buat(Gtk $gtk, string $passwordAwal): User
    {
        return DB::transaction(function () use ($gtk, $passwordAwal): User {
            $emailBebas = filled($gtk->email) && ! User::query()->where('email', $gtk->email)->exists();

            $user = User::query()->create([
                'name' => $gtk->nama,
                'email' => $emailBebas ? $gtk->email : null,
                'password' => $passwordAwal,
                'wajib_ganti_password' => true,
            ]);

            $gtk->user()->associate($user)->save();

            return $user;
        });
    }

    public function resetPassword(Gtk $gtk, string $passwordAwal): void
    {
        $gtk->user?->forceFill([
            'password' => $passwordAwal,
            'wajib_ganti_password' => true,
        ])->save();
    }

    /**
     * Operators may reset teacher accounts (including wali_kelas); an account that holds a structural/admin role
     * is reset only by a super admin, so an operator cannot take it over.
     */
    public static function bolehDireset(Gtk $gtk, mixed $oleh): bool
    {
        if ($gtk->user === null || ! $oleh instanceof User) {
            return false;
        }

        $punyaPeranStruktural = $gtk->user->roles()
            ->whereNotIn('name', [RoleSeeder::WALI_KELAS, config('filament-shield.panel_user.name', 'panel_user')])
            ->exists();

        return ! $punyaPeranStruktural || $oleh->hasRole(config('filament-shield.super_admin.name'));
    }
}
