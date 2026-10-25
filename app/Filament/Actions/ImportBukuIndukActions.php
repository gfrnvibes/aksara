<?php

namespace App\Filament\Actions;

use App\Filament\Imports\BukuIndukGabunganImporter;
use App\Filament\Imports\BukuIndukImporter;
use App\Filament\Imports\NilaiImporter;
use App\Filament\Imports\SiswaImporter;
use App\Models\Rapor;
use App\Models\Siswa;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
        return static::make('importNilai', NilaiImporter::class)
            ->label('Import Data Nilai Siswa')
            ->modalHeading('Import Data Nilai Siswa')
            ->optionsFormComponents([static::petunjuk('Satu baris = satu siswa pada satu semester. Siswa harus sudah terdaftar (dicocokkan lewat NIS).')])
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
    protected static function make(string $name, string $importer): ExcelImportAction
    {
        return ExcelImportAction::make($name)
            ->importer($importer)
            ->registerModalActions([
                Action::make('downloadTemplate')
                    ->label('Unduh template Excel')
                    ->link()
                    ->action(fn (): BinaryFileResponse => response()
                        ->download(TemplateWriter::write($importer::getColumns()), $importer::getNamaTemplate().'.xlsx', [
                            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->deleteFileAfterSend()),
            ]);
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
