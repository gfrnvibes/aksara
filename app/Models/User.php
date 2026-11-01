<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email Optional for teachers, who sign in with their NIP, NUPTK or phone number.
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $wajib_ganti_password Set on accounts created for teachers until they choose their own password.
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gtk|null $gtk
 */
#[Fillable(['name', 'email', 'password', 'wajib_ganti_password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wajib_ganti_password' => 'boolean',
        ];
    }

    /**
     * The admin panel opens to any user holding at least one role, and Shield policies decide what they can see. The
     * teacher panel opens to users linked to an active GTK, and shows only their own classes and subjects.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'guru' => $this->gtk()->aktif()->exists(),
            default => $this->roles()->exists(),
        };
    }

    /**
     * @return HasOne<Gtk, $this>
     */
    public function gtk(): HasOne
    {
        return $this->hasOne(Gtk::class);
    }

    /**
     * The account a login form identifier belongs to: an email address, or the NIP, NUPTK or phone number of the
     * GTK linked to the account.
     */
    public static function cariDariLogin(string $login): ?self
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            return static::query()->where('email', $login)->first();
        }

        $gtk = Gtk::query()
            ->whereNotNull('user_id')
            ->where(fn (Builder $query) => $query
                ->where('nip', $login)
                ->orWhere('nuptk', $login)
                ->when(Gtk::normalkanNomorHp($login), fn (Builder $query, string $nomor) => $query->orWhere('nomor_hp', $nomor)))
            ->first();

        return $gtk?->user;
    }

    /**
     * Whether the user is the homeroom teacher of the class.
     */
    public function adalahWaliKelas(?int $kelasId): bool
    {
        return $kelasId !== null && $this->gtk !== null && $this->gtk->kelasPerwalian()->whereKey($kelasId)->exists();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function activityTitle(): ?string
    {
        return $this->name;
    }
}
