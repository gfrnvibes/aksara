<?php

namespace App\Filament\Guru\Pages;

use App\Models\Gtk;
use App\Models\Kelas;
use App\Services\Harian\AksesHarian;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** @property-read Schema $form */
class RekapHarian extends HalamanHarian
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $title = 'Rekap & Cetakan Harian';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['jenis' => 'siswa', 'mulai' => now('Asia/Jakarta')->startOfMonth()->toDateString(), 'selesai' => AksesHarian::hariIni()]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Siapkan rekap')->description('Cetakan memuat data yang tercatat dan kolom tanda tangan. Tanda — berarti belum diisi. Presensi siswa dicetak per bulan.')->columns(2)->schema([
                Select::make('jenis')->options(['siswa' => 'Presensi siswa', 'gtk' => 'Kehadiran GTK', 'jurnal' => 'Jurnal mengajar', 'piket' => 'Buku piket'])->required()->live(),
                Select::make('kelas_id')->label('Kelas')->options(fn (): array => Kelas::query()->aktif()
                    ->when(! AksesHarian::pengelola(static::user()), fn ($q) => $q->where('wali_kelas_id', static::user()->gtk->id ?? 0))
                    ->orderBy('nama')->pluck('nama', 'id')->all())->live()->visible(fn ($get): bool => $get('jenis') === 'siswa')->required(fn ($get): bool => $get('jenis') === 'siswa'),
                Select::make('gtk_id')->label('Guru / GTK')->placeholder('Semua GTK (pengelola)')->options(fn (): array => Gtk::query()->aktif()
                    ->when(! AksesHarian::pengelola(static::user()), fn ($q) => $q->where('user_id', static::user()->id))
                    ->orderBy('nama')->pluck('nama', 'id')->all())->live()->visible(fn ($get): bool => in_array($get('jenis'), ['gtk', 'jurnal']))->searchable(),
                DatePicker::make('mulai')->label('Dari tanggal')->required()->live(onBlur: true),
                DatePicker::make('selesai')->label('Sampai tanggal')->required()->live(onBlur: true)->afterOrEqual('mulai'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('cetak')->label('Buka cetakan')->icon(Heroicon::OutlinedPrinter)
            ->disabled(fn (): bool => ! $this->siapCetak())
            ->url(fn (): string => route('harian.cetak', $this->data ?? []))->openUrlInNewTab(),
            Action::make('pdf')->label('Unduh PDF')->color('gray')->icon(Heroicon::OutlinedArrowDownTray)
                ->disabled(fn (): bool => ! $this->siapCetak())
                ->url(fn (): string => route('harian.cetak', [...($this->data ?? []), 'format' => 'pdf']))->openUrlInNewTab()];
    }

    protected function siapCetak(): bool
    {
        return filled($this->data['mulai'] ?? null) && filled($this->data['selesai'] ?? null)
            && (($this->data['jenis'] ?? null) !== 'siswa' || filled($this->data['kelas_id'] ?? null));
    }
}
