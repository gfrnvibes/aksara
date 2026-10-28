<?php

namespace App\Filament\Actions;

use App\Enums\Semester;
use App\Exports\BukuIndukExport;
use App\Filament\Imports\BukuIndukGabunganImporter;
use App\Filament\Imports\BukuIndukImporter;
use App\Filament\Imports\Concerns\ImportsDataNilai;
use App\Filament\Imports\NilaiImporter;
use App\Filament\Imports\SiswaImporter;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Imports\ImportColumn;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use RomanSulzhyk\FilamentImport\Actions\ExcelImportAction;
use RomanSulzhyk\FilamentImport\Importing\TemplateWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds the three import actions (biodata, grades, combined), each with its own template.
 */
class ImportBukuIndukActions
{
    public static function group(): ActionGroup
    {
        return ActionGroup::make([
            static::siswa(),
            static::nilai(),
            static::gabungan(),
        ])
            ->label('Import')
            ->icon(Heroicon::ArrowUpTray)
            ->color('gray')
            ->button();
    }

    public static function siswa(): ExcelImportAction
    {
        return static::make('importSiswa', SiswaImporter::class)
            ->label('Import Data Siswa')
            ->modalHeading('Import Data Siswa')
            ->optionsFormComponents([static::petunjuk('Satu baris = satu siswa. Siswa dengan NIS yang sudah ada akan diperbarui.')])
            ->authorize(fn (): bool => static::bolehMenulis(Siswa::class));
    }

    public static function nilai(): ExcelImportAction
    {
        return static::make('importNilai', NilaiImporter::class, static::unduhTemplateNilai())
            ->label('Import Data Nilai Siswa')
            ->modalHeading('Import Data Nilai Siswa')
            ->optionsFormComponents([static::petunjuk('Satu baris = satu siswa pada satu semester. Siswa harus sudah terdaftar (dicocokkan lewat NIS). Unduh template per kelas agar daftar siswa dan nilai yang sudah ada langsung terisi.')])
            ->authorize(fn (): bool => static::bolehMenulis(Rapor::class));
    }

    public static function gabungan(): ExcelImportAction
    {
        return static::make('importGabungan', BukuIndukGabunganImporter::class)
            ->label('Import Data Siswa & Nilai')
            ->modalHeading('Import Data Siswa & Nilai')
            ->optionsFormComponents([static::petunjuk('Satu baris = satu siswa pada satu semester, biodata diulang di tiap baris. Kosongkan Tahun Ajaran dan Semester untuk baris yang hanya berisi biodata.')])
            ->authorize(fn (): bool => static::bolehMenulis(Siswa::class) && static::bolehMenulis(Rapor::class));
    }

    /**
     * @param  class-string<BukuIndukImporter>  $importer
     */
    protected static function make(string $name, string $importer, ?Action $unduhTemplate = null): ExcelImportAction
    {
        return ExcelImportAction::make($name)
            ->importer($importer)
            ->registerModalActions([
                $unduhTemplate ?? Action::make('downloadTemplate')
                    ->label('Unduh template Excel')
                    ->link()
                    ->action(fn (): BinaryFileResponse => static::unduhTemplateKosong($importer)),
            ]);
    }

    /**
     * The grade template: pre-filled for one class when a class is chosen, otherwise an empty template.
     */
    protected static function unduhTemplateNilai(): Action
    {
        return Action::make('downloadTemplate')
            ->label('Unduh template Excel')
            ->link()
            ->modalHeading('Unduh template nilai')
            ->modalDescription('Pilih kelas agar template berisi daftar siswa kelas tersebut beserta nilai yang sudah pernah diinput. Kosongkan kelas untuk template kosong.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Unduh')
            ->schema([
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->requiredWith('kelas_id'),
                Select::make('semester')
                    ->options(Semester::class)
                    ->default(fn (): Semester => Semester::berdasarkanTanggal(now()))
                    ->requiredWith('kelas_id'),
                Select::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn (): array => Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                    ->placeholder('Template kosong'),
            ])
            ->action(function (array $data): BinaryFileResponse {
                if (blank($data['kelas_id'] ?? null)) {
                    return static::unduhTemplateKosong(NilaiImporter::class);
                }

                $kelas = Kelas::query()->findOrFail((int) $data['kelas_id']);
                $tahunAjaran = TahunAjaran::query()->findOrFail((int) $data['tahun_ajaran_id']);
                $semester = $data['semester'] instanceof Semester ? $data['semester'] : Semester::from((int) $data['semester']);

                return response()
                    ->download(
                        BukuIndukExport::templateNilaiKelas($kelas, $tahunAjaran, $semester),
                        BukuIndukExport::namaFileTemplateNilaiKelas($kelas, $tahunAjaran, $semester),
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    )
                    ->deleteFileAfterSend();
            });
    }

    /**
     * An empty template whose headers are the column labels (e.g. "Matematika (P)"), which the import recognises.
     *
     * @param  class-string<BukuIndukImporter>  $importer
     */
    protected static function unduhTemplateKosong(string $importer): BinaryFileResponse
    {
        $kolom = $importer::getColumns();

        if (in_array(ImportsDataNilai::class, class_uses_recursive($importer), true)) {
            $kolom = NilaiImporter::kolomUntukSkema($kolom, TahunAjaran::skemaUntuk(TahunAjaran::query()->where('is_aktif', true)->value('id')));
        }

        $kolom = array_map(fn (ImportColumn $kolom): ImportColumn => $kolom->exampleHeader((string) $kolom->getLabel()), $kolom);

        return response()
            ->download(TemplateWriter::write($kolom), $importer::getNamaTemplate().'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    protected static function petunjuk(string $teks): Text
    {
        return Text::make($teks)->color('gray');
    }

    /**
     * @param  class-string  $model
     */
    protected static function bolehMenulis(string $model): bool
    {
        return Gate::allows('create', $model) && Gate::allows('update', new $model);
    }
}
