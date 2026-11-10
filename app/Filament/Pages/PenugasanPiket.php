<?php

namespace App\Filament\Pages;

use App\Filament\Guru\Pages\RekapHarian;
use App\Models\Gtk;
use App\Models\PenugasanPiket as Penugasan;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class PenugasanPiket extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?string $title = 'Penugasan Piket';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && AksesHarian::pengelola($user);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('tugaskan')->label('Tugaskan Guru Piket')->schema([
            DatePicker::make('tanggal')->required(), Select::make('gtk_id')->label('Guru piket')->options(fn (): array => Gtk::query()->aktif()->pluck('nama', 'id')->all())->searchable()->required(),
            TextInput::make('catatan')->maxLength(500),
        ])->action(function (array $data): void {
            abort_unless(static::canAccess(), 403);
            Gtk::query()->aktif()->findOrFail($data['gtk_id']);
            Penugasan::query()->updateOrCreate(['tanggal' => $data['tanggal'], 'gtk_id' => $data['gtk_id']], ['catatan' => $data['catatan'] ?? null, 'pencatat_id' => Filament::auth()->id()]);
            Notification::make()->success()->title('Penugasan piket tersimpan')->send();
        }), Action::make('rekap')->label('Rekap & Cetak')->color('gray')->url(fn (): string => RekapHarian::getUrl(panel: 'admin'))];
    }

    public function table(Table $table): Table
    {
        return $table->query(Penugasan::query()->with('gtk'))->defaultSort('tanggal', 'desc')->defaultPaginationPageOption(25)
            ->columns([TextColumn::make('tanggal')->date('d M Y')->sortable(), TextColumn::make('gtk.nama')->label('Guru piket'), TextColumn::make('catatan')])
            ->recordActions([DeleteAction::make()->label('Batalkan penugasan')->before(fn () => abort_unless(static::canAccess(), 403))]);
    }
}
