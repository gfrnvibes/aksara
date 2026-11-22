<?php

namespace App\Filament\Resources\BukuPerpustakaans\Pages;

use App\Filament\Resources\BukuPerpustakaans\BukuPerpustakaanResource;
use App\Models\User;
use App\Services\Perpustakaan\ImporBukuPerpustakaan;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ManageBukuPerpustakaans extends ManageRecords
{
    protected static string $resource = BukuPerpustakaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imporKatalog')
                ->label('Impor Katalog (CSV/JSON)')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->schema([
                    FileUpload::make('file_katalog')
                        ->label('File Katalog (CSV / JSON)')
                        ->disk('local')
                        ->directory('perpustakaan/impor')
                        ->acceptedFileTypes(['application/json', 'text/csv', 'text/plain'])
                        ->helperText('Khusus jenjang MTs (tingkat 7, 8, 9). Kolom: judul, penulis, penerbit, tahun_terbit, isbn, kategori, sumber, tingkat, mapel, deskripsi, pdf_url.')
                        ->required(),
                ])
                ->action(function (array $data, ImporBukuPerpustakaan $importer): void {
                    $relPath = (string) ($data['file_katalog'] ?? '');
                    $fullPath = Storage::disk('local')->path($relPath);
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    $rows = [];

                    try {
                        if ($ext === 'json') {
                            $decoded = json_decode((string) file_get_contents($fullPath), true);
                            if (is_array($decoded)) {
                                $rows = array_values(array_filter($decoded, 'is_array'));
                            }
                        } else {
                            $handle = fopen($fullPath, 'r');
                            if ($handle !== false) {
                                $header = fgetcsv($handle);
                                if (is_array($header)) {
                                    $keys = array_map(fn ($h): string => trim((string) $h), $header);
                                    while (($line = fgetcsv($handle)) !== false) {
                                        if (count($line) === count($keys)) {
                                            $rows[] = array_combine($keys, $line);
                                        }
                                    }
                                }
                                fclose($handle);
                            }
                        }
                    } finally {
                        Storage::disk('local')->delete($relPath);
                    }

                    $user = auth()->user();
                    $hasil = $importer->imporDariBaris($rows, $user instanceof User ? $user->id : null);

                    $notif = Notification::make()
                        ->title("Impor selesai: {$hasil['berhasil']} buku berhasil")
                        ->body(
                            $hasil['gagal'] !== []
                                ? count($hasil['gagal']).' buku gagal: '.implode(' | ', array_slice($hasil['gagal'], 0, 3))
                                : ($hasil['dilewati'] > 0 ? "{$hasil['dilewati']} baris dilewati (bukan jenjang MTs 7–9)." : null)
                        );

                    if ($hasil['gagal'] !== []) {
                        $notif->warning();
                    } else {
                        $notif->success();
                    }

                    $notif->send();
                }),
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['pengunggah_id'] = auth()->id();
                    if (empty($data['file_pdf']) && ! empty($data['sumber_url'])) {
                        $data['file_pdf'] = app(ImporBukuPerpustakaan::class)
                            ->unduhPdfKeStorage((string) $data['sumber_url'], (string) ($data['judul'] ?? 'buku'));
                    }

                    return $data;
                }),
        ];
    }
}
