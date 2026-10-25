<?php

namespace App\Filament\Actions;

use App\Enums\IsiDokumen;
use App\Exports\BukuIndukExport;
use App\Http\Controllers\CetakBukuIndukController;
use App\Models\Rapor;
use App\Models\Siswa;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export (Excel/PDF) and mass print/download actions for the student and grade tables.
 */
class EksporBukuIndukActions
{
    /**
     * Above this many students a Buku Induk PDF takes too long for one request; printing in the browser has no limit.
     */
    public const int MAKS_SISWA_PDF_BUKU_INDUK = 100;

    public const string FORMAT_EXCEL = 'xlsx';

    public const string FORMAT_PDF = 'pdf';

    public const string MODE_CETAK = 'cetak';

    public const string MODE_UNDUH = 'unduh';

    /**
     * Export every student matching the current table search and filters.
     */
    public static function exportSiswa(): Action
    {
        return Action::make('exportSiswa')
            ->label('Export')
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->modalHeading('Export Data (semua hasil filter)')
            ->modalSubmitActionLabel('Export')
            ->schema(fn (): array => static::formExport())
            ->authorize(fn (): bool => Gate::allows('viewAny', Siswa::class))
            ->action(fn (array $data, HasTable $livewire): Response => static::unduhExportSiswa(
                static::siswaDariQuery(static::queryTabel($livewire, Siswa::class)),
                static::isi($data['isi']),
                $data['format'],
            ));
    }

    public static function exportSiswaTerpilih(): BulkAction
    {
        return BulkAction::make('exportSiswaTerpilih')
            ->label('Export terpilih')
            ->icon(Heroicon::ArrowDownTray)
            ->modalHeading('Export Data Siswa terpilih')
            ->modalSubmitActionLabel('Export')
            ->schema(fn (): array => static::formExport())
            ->authorize(fn (): bool => Gate::allows('viewAny', Siswa::class))
            ->deselectRecordsAfterCompletion()
            ->action(fn (array $data, EloquentCollection $records): Response => static::unduhExportSiswa(
                static::siswaDariQuery(Siswa::query()->whereKey($records->modelKeys())),
                static::isi($data['isi']),
                $data['format'],
            ));
    }

    /**
     * Export every report card matching the current table search and filters (grades only).
     */
    public static function exportRapor(): Action
    {
        return Action::make('exportRapor')
            ->label('Export')
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->modalHeading('Export Data Nilai Siswa (semua hasil filter)')
            ->modalSubmitActionLabel('Export')
            ->schema([static::fieldFormat()])
            ->authorize(fn (): bool => Gate::allows('viewAny', Rapor::class))
            ->action(function (array $data, HasTable $livewire): Response {
                $daftarRapor = BukuIndukExport::muatRapor(static::queryTabel($livewire, Rapor::class));

                return $data['format'] === static::FORMAT_PDF
                    ? static::unduhPdf(BukuIndukExport::pdfRekap(collect(), IsiDokumen::Nilai, $daftarRapor), BukuIndukExport::namaFile('export', IsiDokumen::Nilai, 'pdf'))
                    : static::unduhExcel(BukuIndukExport::excelRapor($daftarRapor), BukuIndukExport::namaFile('export', IsiDokumen::Nilai, 'xlsx'));
            });
    }

    /**
     * Print or download the full Buku Induk of every student matching the current table search and filters.
     */
    public static function cetakSiswa(): Action
    {
        return Action::make('cetakBukuInduk')
            ->label('Cetak / Unduh Buku Induk')
            ->icon(Heroicon::Printer)
            ->color('gray')
            ->modalHeading('Cetak / Unduh Buku Induk (semua hasil filter)')
            ->modalSubmitActionLabel('Proses')
            ->schema(fn (): array => static::formCetak())
            ->authorize(fn (): bool => Gate::allows('viewAny', Siswa::class))
            ->action(fn (array $data, HasTable $livewire): ?Response => static::prosesCetak(
                static::queryTabel($livewire, Siswa::class)->orderBy('nis')->pluck('id')->all(),
                static::isi($data['isi']),
                $data['mode'],
            ));
    }

    public static function cetakSiswaTerpilih(): BulkAction
    {
        return BulkAction::make('cetakBukuIndukTerpilih')
            ->label('Cetak / Unduh Buku Induk terpilih')
            ->icon(Heroicon::Printer)
            ->modalHeading('Cetak / Unduh Buku Induk siswa terpilih')
            ->modalSubmitActionLabel('Proses')
            ->schema(fn (): array => static::formCetak())
            ->authorize(fn (): bool => Gate::allows('viewAny', Siswa::class))
            ->deselectRecordsAfterCompletion()
            ->action(fn (array $data, EloquentCollection $records): ?Response => static::prosesCetak(
                $records->modelKeys(),
                static::isi($data['isi']),
                $data['mode'],
            ));
    }

    /**
     * Print or download the Buku Induk of a single student, choosing what to include.
     */
    public static function cetakSatuSiswa(): ActionGroup
    {
        $aksi = [];

        foreach ([static::MODE_CETAK, static::MODE_UNDUH] as $mode) {
            foreach (IsiDokumen::cases() as $isi) {
                $aksi[] = Action::make("{$mode}_{$isi->value}")
                    ->label(($mode === static::MODE_CETAK ? 'Cetak: ' : 'Unduh PDF: ').$isi->getLabel())
                    ->icon($mode === static::MODE_CETAK ? Heroicon::Printer : Heroicon::DocumentArrowDown)
                    ->url(fn (Siswa $record): string => route('siswa.cetak', [
                        'siswa' => $record,
                        'isi' => $isi->value,
                        ...($mode === static::MODE_UNDUH ? ['format' => static::FORMAT_PDF] : []),
                    ]), shouldOpenInNewTab: $mode === static::MODE_CETAK)
                    ->visible(fn (): bool => ! $isi->termasukNilai() || Gate::allows('viewAny', Rapor::class));
            }
        }

        return ActionGroup::make($aksi)
            ->label('Cetak / Unduh')
            ->icon(Heroicon::Printer)
            ->color('gray');
    }

    /**
     * @param  array<int, int>  $siswaIds
     */
    public static function prosesCetak(array $siswaIds, IsiDokumen $isi, string $mode): ?Response
    {
        if ($siswaIds === []) {
            Notification::make()->title('Tidak ada siswa untuk dicetak.')->warning()->send();

            return null;
        }

        if ($mode === static::MODE_UNDUH) {
            if (count($siswaIds) > static::MAKS_SISWA_PDF_BUKU_INDUK) {
                Notification::make()
                    ->title('Terlalu banyak siswa untuk satu file PDF')
                    ->body('Maksimal '.static::MAKS_SISWA_PDF_BUKU_INDUK.' siswa per unduhan PDF ('.count($siswaIds).' dipilih). Persempit dengan filter kelas, atau pilih "Cetak di browser" lalu simpan sebagai PDF dari dialog cetak.')
                    ->danger()
                    ->persistent()
                    ->send();

                return null;
            }

            $daftarSiswa = BukuIndukExport::muatSiswa(Siswa::query()->whereKey($siswaIds));

            return static::unduhPdf(BukuIndukExport::pdfBukuInduk($daftarSiswa, $isi), BukuIndukExport::namaFile('buku-induk', $isi, 'pdf'));
        }

        $url = route('buku-induk.cetak-massal', CetakBukuIndukController::siapkanCetakMassal($siswaIds, $isi));

        Notification::make()
            ->title('Halaman cetak siap')
            ->body(count($siswaIds).' siswa · '.$isi->getLabel().'. Tautan berlaku '.CetakBukuIndukController::MASA_BERLAKU_MENIT.' menit.')
            ->success()
            ->persistent()
            ->actions([
                Action::make('buka')
                    ->label('Buka halaman cetak')
                    ->button()
                    ->url($url, shouldOpenInNewTab: true),
            ])
            ->send();

        return null;
    }

    /**
     * A fresh query for the model, limited to the rows that match the table's current search and filters.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    protected static function queryTabel(HasTable $livewire, string $model): Builder
    {
        $queryTabel = $livewire->getFilteredTableQuery() ?? throw new LogicException('Tabel tidak memiliki query.');
        $kolomKunci = (new $model)->getQualifiedKeyName();

        return $model::query()->whereIn($kolomKunci, $queryTabel->reorder()->select($kolomKunci));
    }

    /**
     * @param  Collection<int, Siswa>  $daftarSiswa
     */
    protected static function unduhExportSiswa(Collection $daftarSiswa, IsiDokumen $isi, string $format): Response
    {
        if ($format === static::FORMAT_PDF) {
            return static::unduhPdf(BukuIndukExport::pdfRekap($daftarSiswa, $isi), BukuIndukExport::namaFile('export', $isi, 'pdf'));
        }

        return static::unduhExcel(BukuIndukExport::excelSiswa($daftarSiswa, $isi), BukuIndukExport::namaFile('export', $isi, 'xlsx'));
    }

    /**
     * @param  Builder<Siswa>  $query
     * @return Collection<int, Siswa>
     */
    protected static function siswaDariQuery(Builder $query): Collection
    {
        return BukuIndukExport::muatSiswa($query);
    }

    protected static function unduhExcel(string $path, string $namaFile): Response
    {
        return response()
            ->download($path, $namaFile, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    protected static function unduhPdf(string $pdf, string $namaFile): Response
    {
        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $namaFile, ['Content-Type' => 'application/pdf']);
    }

    /**
     * @return array<int, Radio>
     */
    protected static function formExport(): array
    {
        return [static::fieldIsi(), static::fieldFormat()];
    }

    /**
     * @return array<int, Radio>
     */
    protected static function formCetak(): array
    {
        return [
            static::fieldIsi(),
            Radio::make('mode')
                ->label('Keluaran')
                ->options([
                    static::MODE_CETAK => 'Cetak di browser',
                    static::MODE_UNDUH => 'Unduh PDF',
                ])
                ->descriptions([
                    static::MODE_CETAK => 'Membuka halaman cetak di tab baru (bisa juga disimpan sebagai PDF dari dialog cetak).',
                    static::MODE_UNDUH => 'Maksimal '.static::MAKS_SISWA_PDF_BUKU_INDUK.' siswa per file.',
                ])
                ->default(static::MODE_CETAK)
                ->required(),
        ];
    }

    /**
     * The enum-backed radio holds an IsiDokumen once cast, but raw form input is still a string.
     */
    protected static function isi(mixed $nilai): IsiDokumen
    {
        return $nilai instanceof IsiDokumen ? $nilai : IsiDokumen::from((string) $nilai);
    }

    protected static function fieldIsi(): Radio
    {
        $bolehLihatNilai = Gate::allows('viewAny', Rapor::class);

        return Radio::make('isi')
            ->label('Isi')
            ->options(IsiDokumen::class)
            ->disableOptionWhen(fn (string $value): bool => ! $bolehLihatNilai && IsiDokumen::from($value)->termasukNilai())
            ->default(IsiDokumen::Siswa->value)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($bolehLihatNilai): void {
                if (! $bolehLihatNilai && static::isi($value)->termasukNilai()) {
                    $fail('Anda tidak memiliki akses ke data nilai.');
                }
            })
            ->required();
    }

    protected static function fieldFormat(): Radio
    {
        return Radio::make('format')
            ->label('Format')
            ->options([
                static::FORMAT_EXCEL => 'Excel (.xlsx)',
                static::FORMAT_PDF => 'PDF',
            ])
            ->descriptions([
                static::FORMAT_EXCEL => 'Kolom sama dengan template import, sehingga bisa diedit lalu di-import kembali.',
                static::FORMAT_PDF => 'Tabel rekap siap cetak (A4 mendatar).',
            ])
            ->default(static::FORMAT_EXCEL)
            ->inline()
            ->required();
    }
}
