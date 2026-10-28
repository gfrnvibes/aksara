<?php

namespace App\Filament\Resources\Ijazahs\Pages;

use App\Filament\Resources\Ijazahs\IjazahResource;
use App\Models\Ijazah;
use App\Services\Arsip\ImporArsipIjazah;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ManageIjazahs extends ManageRecords
{
    protected static string $resource = IjazahResource::class;

    /**
     * Most files one bulk upload accepts.
     */
    public const int MAKS_FILE_MASSAL = 200;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiUnggahMassal(),
            CreateAction::make(),
        ];
    }

    /**
     * Upload a whole graduation year at once; each file is filed under the graduate whose NIS or NISN is its name.
     */
    protected function aksiUnggahMassal(): Action
    {
        return Action::make('unggahMassal')
            ->label('Unggah Massal')
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->color('gray')
            ->authorize(fn (): bool => IjazahResource::canCreate())
            ->modalHeading('Unggah ijazah satu angkatan')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Unggah')
            ->schema([
                Text::make('Beri nama setiap file dengan NIS atau NISN lulusan, misalnya 0133438941.pdf. Untuk ijazah lebih dari satu halaman tambahkan akhiran: 0133438941-1.jpg, 0133438941-2.jpg (atau _depan / _belakang).')
                    ->color('gray'),
                TextInput::make('tahun_lulus')
                    ->label('Tahun Lulus')
                    ->numeric()
                    ->integer()
                    ->minValue(1950)
                    ->maxValue((int) now()->year + 1)
                    ->default((int) now()->year)
                    ->required(),
                FileUpload::make('berkas')
                    ->label('File ijazah')
                    ->multiple()
                    ->maxFiles(static::MAKS_FILE_MASSAL)
                    ->acceptedFileTypes(IjazahResource::JENIS_BERKAS)
                    ->maxSize(IjazahResource::UKURAN_MAKS_KB)
                    ->storeFiles(false)
                    ->required(),
                Toggle::make('buat_entri_baru')
                    ->label('Buat data baru untuk nomor yang belum terdaftar')
                    ->helperText('Untuk alumni lama yang belum ada di arsip maupun Data Siswa; nama dilengkapi belakangan.')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $hasil = app(ImporArsipIjazah::class)->impor(
                    (int) $data['tahun_lulus'],
                    array_values((array) $data['berkas']),
                    (bool) ($data['buat_entri_baru'] ?? false),
                );

                $tidakCocok = $hasil['tidakCocok'];

                Notification::make()
                    ->title("Ijazah {$data['tahun_lulus']} diunggah")
                    ->body(collect([
                        $hasil['dibuat'] > 0 ? "{$hasil['dibuat']} data baru" : null,
                        $hasil['diperbarui'] > 0 ? "{$hasil['diperbarui']} data ditambah berkasnya" : null,
                        $tidakCocok !== [] ? count($tidakCocok).' file tidak cocok: '.Str::limit(implode(', ', $tidakCocok), 300) : null,
                    ])->filter()->implode('. ').'.')
                    ->color($tidakCocok === [] ? 'success' : 'warning')
                    ->duration($tidakCocok === [] ? 6000 : 'persistent')
                    ->send();
            });
    }

    public function getSubheading(): ?string
    {
        $jumlah = Ijazah::query()->count();

        return $jumlah === 0 ? null : "{$jumlah} ijazah dari ".Ijazah::query()->distinct()->count('tahun_lulus').' angkatan.';
    }
}
