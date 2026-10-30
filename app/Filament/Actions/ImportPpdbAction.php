<?php

namespace App\Filament\Actions;

use App\Filament\Imports\PpdbImporter;
use Filament\Actions\Action;
use Filament\Actions\Imports\ImportColumn;
use RomanSulzhyk\FilamentImport\Actions\ExcelImportAction;
use RomanSulzhyk\FilamentImport\Importing\TemplateWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportPpdbAction
{
    public static function make(): ExcelImportAction
    {
        return ExcelImportAction::make('importPpdb')
            ->label('Import Data PPDB')
            ->importer(PpdbImporter::class)
            ->modalHeading('Import Data PPDB')
            ->registerModalActions([
                Action::make('downloadTemplate')
                    ->label('Unduh template Excel')
                    ->link()
                    ->action(fn (): BinaryFileResponse => static::unduhTemplate()),
            ]);
    }

    protected static function unduhTemplate(): BinaryFileResponse
    {
        $kolom = array_map(
            fn (ImportColumn $kolom): ImportColumn => $kolom->exampleHeader((string) $kolom->getLabel()),
            PpdbImporter::getColumns(),
        );

        return response()
            ->download(TemplateWriter::write($kolom), 'template-ppdb.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
