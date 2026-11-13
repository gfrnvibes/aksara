<?php

namespace App\Filament\Ujian\Resources\Soals;

use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Concerns\AksesGuruAtauIzin;
use App\Filament\Ujian\Resources\Soals\Pages\BuatSoalAi;
use App\Filament\Ujian\Resources\Soals\Pages\CreateSoal;
use App\Filament\Ujian\Resources\Soals\Pages\EditSoal;
use App\Filament\Ujian\Resources\Soals\Pages\KelompokSoal;
use App\Filament\Ujian\Resources\Soals\Pages\ListSoals;
use App\Filament\Ujian\Resources\Soals\Schemas\SoalForm;
use App\Filament\Ujian\Resources\Soals\Tables\SoalsTable;
use App\Models\MataPelajaran;
use App\Models\Soal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The question bank: a card per subject, each opening its list of questions with a tab per grade. See
 * {@see AksesGuruAtauIzin} for who sees and changes which questions.
 */
class SoalResource extends Resource
{
    use AksesGuruAtauIzin;

    protected static ?string $model = Soal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Soal';

    protected static ?string $pluralModelLabel = 'Bank Soal';

    protected static ?string $slug = 'bank-soal';

    protected static function izin(): string
    {
        return 'Soal';
    }

    /**
     * The subject and grade pairs the user writes for: every active subject in every grade for staff, otherwise
     * the subjects and grades of the teacher's classes this year.
     *
     * @return array<int, array{int, int}>
     */
    public static function pasanganMapelKelas(): array
    {
        $user = static::pengguna();

        if ($user?->can('Create:Soal')) {
            return collect(array_keys(static::pilihanMapel()))
                ->crossJoin(array_keys(KelasResource::getTingkatOptions()))
                ->map(fn (array $p): array => [(int) $p[0], (int) $p[1]])
                ->all();
        }

        return static::pembagianGuru($user)
            ->join('kelas', 'kelas.id', '=', 'pembagian_mengajars.kelas_id')
            ->toBase()
            ->distinct()
            ->get(['pembagian_mengajars.mata_pelajaran_id', 'kelas.tingkat'])
            ->map(fn (object $baris): array => [(int) $baris->mata_pelajaran_id, (int) $baris->tingkat])
            ->all();
    }

    /**
     * "IPA", or "IPA · Kelas VII" when a grade is given.
     */
    public static function labelKelompok(int $mataPelajaranId, ?int $tingkat = null): string
    {
        $mapel = (string) MataPelajaran::query()->whereKey($mataPelajaranId)->value('nama');

        return $tingkat === null ? $mapel : "{$mapel} · Kelas ".(KelasResource::getTingkatOptions()[$tingkat] ?? $tingkat);
    }

    /**
     * A subject's question list, opened on a grade's tab when one is given.
     */
    public static function urlKelompok(int $mataPelajaranId, ?int $tingkat = null): string
    {
        return static::getUrl('daftar', array_filter(['mapel' => $mataPelajaranId, 'tab' => $tingkat === null ? null : "kelas-{$tingkat}"]));
    }

    public static function form(Schema $schema): Schema
    {
        return SoalForm::configure($schema, static::pilihanMapel(...));
    }

    public static function table(Table $table): Table
    {
        return SoalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => KelompokSoal::route('/'),
            'daftar' => ListSoals::route('/mapel/{mapel}'),
            'ai' => BuatSoalAi::route('/mapel/{mapel}/ai'),
            'create' => CreateSoal::route('/create'),
            'edit' => EditSoal::route('/{record}/edit'),
        ];
    }
}
