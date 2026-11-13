<?php

namespace App\Filament\Ujian\Concerns;

use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Access in the exam panel. Curriculum staff with the model's permissions ("Update:Soal") work on every record;
 * a teacher sees the records of the subjects they teach this year, shared with the subject's other teachers, and
 * changes only the records they made. Records carry `mata_pelajaran_id` and `user_id`.
 */
trait AksesGuruAtauIzin
{
    /**
     * The permission suffix, e.g. "Soal" for "ViewAny:Soal".
     */
    abstract protected static function izin(): string;

    protected static function pengguna(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The subjects the user may work with: all active ones for staff, otherwise those the teacher teaches this year.
     *
     * @return array<int, string>
     */
    public static function pilihanMapel(): array
    {
        $user = static::pengguna();
        $semua = $user?->can('Create:'.static::izin()) ?? false;

        // Asked several times per request (query scope, every row's authorization); look it up once.
        return once(function () use ($user, $semua): array {
            $query = MataPelajaran::query()->aktifBerurutan();

            if (! $semua) {
                $query->whereIn('id', static::pembagianGuru($user)->select('mata_pelajaran_id'));
            }

            return $query->pluck('nama', 'id')->all();
        });
    }

    /**
     * The teacher's teaching assignments this year.
     *
     * @return Builder<PembagianMengajar>
     */
    protected static function pembagianGuru(?User $user): Builder
    {
        $tahunAjaranId = TahunAjaran::query()->where('is_aktif', true)->value('id');

        return PembagianMengajar::query()->milik($user?->gtk, $tahunAjaranId ?? 0);
    }

    protected static function guruAktif(User $user): bool
    {
        return once(fn (): bool => $user->gtk()->aktif()->exists());
    }

    public static function getEloquentQuery(): Builder
    {
        if (static::pengguna()?->can('ViewAny:'.static::izin()) ?? false) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()
            ->where(fn (Builder $query) => $query
                ->whereIn('mata_pelajaran_id', array_keys(static::pilihanMapel()))
                ->orWhere('user_id', static::pengguna()->id ?? 0));
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = static::pengguna();
        $action = $action instanceof UnitEnum ? $action->name : $action;
        $izin = ['viewAny' => 'ViewAny', 'view' => 'View', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete', 'deleteAny' => 'DeleteAny'][$action] ?? null;

        if ($user === null || $izin === null) {
            return Response::deny();
        }

        if ($user->can("{$izin}:".static::izin())) {
            return Response::allow();
        }

        $guru = static::guruAktif($user);
        $milikSendiri = $record !== null && $record->getAttribute('user_id') === $user->id;

        $boleh = match ($action) {
            'viewAny' => $guru,
            'view' => $guru && ($record === null || static::getEloquentQuery()->whereKey($record->getKey())->exists()),
            'create' => $guru && static::pilihanMapel() !== [],
            'update', 'delete' => $guru && $milikSendiri,
            default => false,
        };

        return $boleh ? Response::allow() : Response::deny();
    }
}
