<?php

namespace App\Filament\Pages;

use App\Models\PengaturanPresensi;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** @property-read Schema $form */
class PengaturanPresensiPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Presensi & Lokasi';

    protected static ?string $title = 'Pengaturan Presensi Geolocation';

    protected static ?string $slug = 'pengaturan-presensi';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && AksesHarian::pengelola($user);
    }

    public function mount(): void
    {
        $pengaturan = PengaturanPresensi::aktif();

        $this->form->fill([
            'aktif' => $pengaturan->aktif,
            'nama_lokasi' => $pengaturan->nama_lokasi,
            'latitude' => $pengaturan->latitude,
            'longitude' => $pengaturan->longitude,
            'radius_meter' => $pengaturan->radius_meter,
            'jam_mulai' => $pengaturan->jam_mulai,
            'jam_selesai' => $pengaturan->jam_selesai,
            'wajib_foto' => $pengaturan->wajib_foto,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Batas Lokasi & Jam Presensi Mobile')
                ->description('Mengatur validasi GPS, radius, jam buka presensi, dan kewajiban foto saat guru mencatat kehadiran dari aplikasi mobile.')
                ->columns(2)
                ->schema([
                    Toggle::make('aktif')
                        ->label('Aktifkan validasi lokasi & jam presensi')
                        ->columnSpanFull(),
                    TextInput::make('nama_lokasi')
                        ->label('Nama lokasi')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('radius_meter')
                        ->label('Radius maksimal (meter)')
                        ->numeric()
                        ->integer()
                        ->minValue(10)
                        ->maxValue(10000)
                        ->required(),
                    TextInput::make('latitude')
                        ->label('Latitude Madrasah')
                        ->numeric()
                        ->step('0.0000001')
                        ->helperText('Kosongkan jika belum ingin membatasi koordinat GPS.'),
                    TextInput::make('longitude')
                        ->label('Longitude Madrasah')
                        ->numeric()
                        ->step('0.0000001'),
                    TextInput::make('jam_mulai')
                        ->label('Jam mulai presensi (WIB)')
                        ->placeholder('06:00')
                        ->rules(['regex:/^\d{2}:\d{2}$/'])
                        ->required(),
                    TextInput::make('jam_selesai')
                        ->label('Jam selesai presensi (WIB)')
                        ->placeholder('08:30')
                        ->rules(['regex:/^\d{2}:\d{2}$/'])
                        ->required(),
                    Toggle::make('wajib_foto')
                        ->label('Wajib ambil foto kamera (selfie / lingkungan madrasah)')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simpan')
                ->label('Simpan Pengaturan')
                ->icon(Heroicon::OutlinedCheck)
                ->action(function (): void {
                    $state = $this->form->getState();
                    PengaturanPresensi::aktif()->update($state);

                    Notification::make()
                        ->success()
                        ->title('Pengaturan presensi disimpan')
                        ->send();
                }),
        ];
    }
}
