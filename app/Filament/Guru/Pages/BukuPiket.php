<?php

namespace App\Filament\Guru\Pages;

use App\Models\CatatanPiket;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\PenugasanPiket;
use App\Models\Siswa;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PresensiHarian;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class BukuPiket extends HalamanHarian implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $title = 'Buku Piket';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return parent::canAccess() && (AksesHarian::pengelola(static::user()) || PenugasanPiket::query()->where('gtk_id', static::user()->gtk->id ?? 0)->exists());
    }

    public function getSubheading(): ?string
    {
        return 'Catat kejadian pada tanggal penugasan Anda. Gunakan Kehadiran GTK untuk mencatat guru tidak hadir dan Jurnal Mengajar untuk pelaksanaan pengganti.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('catat')->label('Catat Kejadian')->icon(Heroicon::OutlinedPlus)->schema([
            DatePicker::make('tanggal')->default(AksesHarian::hariIni())->maxDate(AksesHarian::hariIni())->required(),
            Select::make('jenis')->options(CatatanPiket::JENIS)->required(),
            Select::make('kelas_id')->label('Kelas')->options(fn (): array => Kelas::query()->aktif()->pluck('nama', 'id')->all())->live(),
            Select::make('siswa_id')->label('Siswa')->options(fn (Get $get): array => Siswa::query()->where('kelas_id', $get('kelas_id') ?? 0)->orderBy('nama_lengkap')->pluck('nama_lengkap', 'id')->all())->searchable(),
            Select::make('gtk_id')->label('GTK terkait')->options(fn (): array => Gtk::query()->aktif()->pluck('nama', 'id')->all())->searchable(),
            TextInput::make('terlambat_menit')->label('Keterlambatan (menit)')->numeric()->integer()->default(0)->minValue(0)->maxValue(600)->required(),
            TextInput::make('jam_pelajaran')->label('Jam pelajaran terkait')->maxLength(100),
            Textarea::make('kejadian')->required()->maxLength(3000), Textarea::make('tindak_lanjut')->label('Penanganan / tindak lanjut')->maxLength(3000),
        ])->action(function (array $data): void {
            app(PresensiHarian::class)->piket(static::user(), $data['tanggal'], $data);
            Notification::make()->success()->title('Kejadian tercatat')->send();
        })];
    }

    public function table(Table $table): Table
    {
        return $table->query(fn () => CatatanPiket::query()->with(['kelas', 'siswa', 'gtk', 'pencatat'])
            ->when(! AksesHarian::pengelola(static::user()), fn ($q) => $q->where(fn ($q) => $q->where('pencatat_id', static::user()->id)
                ->orWhereIn('tanggal', PenugasanPiket::query()->where('gtk_id', static::user()->gtk->id ?? 0)->select('tanggal')))))
            ->defaultSort('tanggal', 'desc')->defaultPaginationPageOption(25)->columns([
                TextColumn::make('tanggal')->date('d M Y')->sortable(),
                TextColumn::make('jenis')->badge()->formatStateUsing(fn (string $state): string => CatatanPiket::JENIS[$state]),
                TextColumn::make('kelas.nama')->label('Kelas'), TextColumn::make('siswa.nama_lengkap')->label('Siswa'),
                TextColumn::make('gtk.nama')->label('GTK'), TextColumn::make('kejadian')->wrap()->limit(100),
                TextColumn::make('tindak_lanjut')->label('Tindak lanjut')->wrap()->limit(100), TextColumn::make('pencatat.name')->label('Petugas'),
            ]);
    }
}
