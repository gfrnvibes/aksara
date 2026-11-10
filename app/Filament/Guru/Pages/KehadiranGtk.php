<?php

namespace App\Filament\Guru\Pages;

use App\Models\Gtk;
use App\Models\PenugasanPiket;
use App\Models\PresensiGtk;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PresensiHarian;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class KehadiranGtk extends HalamanHarian implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $title = 'Kehadiran GTK';

    protected static ?int $navigationSort = 3;

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function getSubheading(): ?string
    {
        return 'Kehadiran harian berbeda dengan pelaksanaan mengajar. Ketidakhadiran dicatat oleh petugas piket; data yang kosong tetap belum tercatat.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hadir')->label('Saya hadir hari ini')->icon(Heroicon::OutlinedCheck)->visible(fn (): bool => static::user()->gtk->is_aktif ?? false)
                ->action(function (): void {
                    app(PresensiHarian::class)->gtk(static::user(), static::user()->gtk, AksesHarian::hariIni(), 'hadir');
                    Notification::make()->success()->title('Kehadiran hari ini tercatat')->send();
                }),
            Action::make('catat')->label('Catat kehadiran GTK')->color('gray')->schema([
                DatePicker::make('tanggal')->default(AksesHarian::hariIni())->maxDate(AksesHarian::hariIni())->required(),
                Select::make('gtk_id')->label('GTK')->options(fn (): array => Gtk::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all())->searchable()->required(),
                Select::make('status')->options(PresensiGtk::STATUS)->required(),
                Textarea::make('catatan')->label('Keterangan / alasan koreksi')->maxLength(500),
            ])->visible(fn (): bool => AksesHarian::pengelola(static::user()) || PenugasanPiket::query()->where('gtk_id', static::user()->gtk->id ?? 0)->exists())
                ->action(function (array $data): void {
                    abort_unless(AksesHarian::piket(static::user(), $data['tanggal']), 403);
                    app(PresensiHarian::class)->gtk(static::user(), Gtk::query()->findOrFail((int) $data['gtk_id']), $data['tanggal'], $data['status'], $data['catatan'] ?? null);
                    Notification::make()->success()->title('Kehadiran GTK tersimpan')->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->query(fn () => PresensiGtk::query()->with(['gtk', 'pencatat'])
            ->when(! AksesHarian::pengelola(static::user()), fn ($q) => $q->where(fn ($q) => $q->where('gtk_id', static::user()->gtk->id ?? 0)
                ->orWhereIn('tanggal', PenugasanPiket::query()->where('gtk_id', static::user()->gtk->id ?? 0)->select('tanggal')))))
            ->defaultSort('tanggal', 'desc')->defaultPaginationPageOption(25)->columns([
                TextColumn::make('tanggal')->date('d M Y')->sortable(), TextColumn::make('gtk.nama')->label('GTK')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => PresensiGtk::STATUS[$state])
                    ->color(fn (string $state): string => $state === 'hadir' ? 'success' : ($state === 'alpa' ? 'danger' : 'warning')),
                TextColumn::make('dicatat_pada')->label('Waktu pencatatan')->dateTime('d M Y H:i')->timezone('Asia/Jakarta'),
                TextColumn::make('catatan')->wrap(), TextColumn::make('pencatat.name')->label('Petugas'),
            ]);
    }
}
