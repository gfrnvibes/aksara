<?php

namespace App\Filament\Bendahara\Pages;

use App\Models\TagihanSiswa as Tagihan;
use App\Services\Keuangan\PencatatanKeuangan;
use App\Services\Keuangan\RingkasanTagihan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

class DetailTagihanSiswa extends HalamanKeuangan implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'tagihan-siswa/detail/{tagihan}';

    #[Locked]
    public string $nama = '';

    public function mount(int $tagihan): void
    {
        $this->nama = Tagihan::query()->findOrFail($tagihan)->nama;
    }

    public function getTitle(): string
    {
        return 'Detail Tagihan: '.$this->nama;
    }

    public function getSubheading(): ?string
    {
        return 'Daftar siswa yang ditagih untuk nama ini. Ringkasan mengikuti filter tahun ajaran dan status yang dipilih.';
    }

    public function getBreadcrumbs(): array
    {
        return [TagihanSiswa::getUrl() => 'Tagihan Siswa', 'Detail'];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('kembali')->label('Kembali ke Daftar Tagihan')->url(TagihanSiswa::getUrl())->color('gray')];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan Tagihan')->schema(function (): array {
                $ids = $this->getFilteredTableQuery()->toBase()->reorder()->select('tagihan_siswas.id');
                $ringkasan = RingkasanTagihan::query(Tagihan::query()->whereIn('tagihan_siswas.id', $ids))->first();

                return [
                    TextEntry::make('penerima')->label('Siswa Ditagih')->state((int) $ringkasan?->getAttribute('jumlah_siswa')),
                    TextEntry::make('total')->label('Total Tagihan Aktif')->state((int) $ringkasan?->getAttribute('total_nominal'))->money('IDR'),
                    TextEntry::make('terbayar')->label('Total Terbayar')->color('success')->icon(Heroicon::OutlinedCheckCircle)->state((int) $ringkasan?->getAttribute('total_terbayar'))->money('IDR'),
                    TextEntry::make('sisa')->label('Total Sisa Tagihan')->color('danger')->icon(Heroicon::OutlinedExclamationCircle)->state((int) $ringkasan?->getAttribute('total_sisa'))->money('IDR'),
                ];
            })->columns(['default' => 1, 'sm' => 2, 'xl' => 4]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultPaginationPageOption(25)->paginationPageOptions([10, 25, 50, 100])->query(Tagihan::query()->where('nama', $this->nama)->with(['siswa.kelas', 'tahunAjaran'])->withSum('pembayaran', 'nominal'))
            ->defaultSort('jatuh_tempo', 'desc')
            ->columns([
                TextColumn::make('siswa.nama_lengkap')->label('Siswa')->searchable(),
                TextColumn::make('siswa.kelas.nama')->label('Kelas Saat Ini'),
                TextColumn::make('tahunAjaran.nama')->label('Tahun Ajaran'),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('periode')->searchable(),
                TextColumn::make('nominal')->money('IDR')->sortable(),
                TextColumn::make('terbayar')->state(fn (Tagihan $record) => $record->terbayar())->money('IDR')->color('success'),
                TextColumn::make('sisa')->state(fn (Tagihan $record) => $record->sisa())->money('IDR')->color(fn (Tagihan $record): string => $record->dibatalkan_at ? 'gray' : 'danger'),
                TextColumn::make('jatuh_tempo')->date('d M Y')->sortable(),
                TextColumn::make('status')->state(fn (Tagihan $record) => $record->status())->badge()->color(fn (string $state) => match ($state) {
                    'Lunas' => 'success', 'Menunggak' => 'danger', 'Dibatalkan' => 'gray', default => 'warning'
                }),
                TextColumn::make('alasan_pembatalan')->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('tahun_ajaran_id')->label('Tahun Ajaran')->relationship('tahunAjaran', 'nama'),
                SelectFilter::make('status')->options(['aktif' => 'Belum Lunas', 'menunggak' => 'Menunggak', 'lunas' => 'Lunas', 'batal' => 'Dibatalkan'])->query(function (Builder $query, array $data): Builder {
                    $status = $data['value'] ?? null;
                    if ($status === 'batal') {
                        return $query->whereNotNull('dibatalkan_at');
                    }
                    if (! $status) {
                        return $query;
                    }
                    $query->whereNull('dibatalkan_at');
                    $sql = '(select coalesce(sum(nominal), 0) from transaksi_keuangans where tagihan_siswa_id = tagihan_siswas.id and dibatalkan_at is null)';
                    $query->whereRaw('nominal '.($status === 'lunas' ? '<=' : '>')." {$sql}");

                    return $status === 'menunggak' ? $query->whereDate('jatuh_tempo', '<', today()) : $query;
                }),
            ])->recordActions([
                Action::make('bayar')->label('Catat Pembayaran')->visible(fn (Tagihan $record) => $record->dibatalkan_at === null && $record->sisa() > 0)
                    ->schema(fn (Tagihan $record) => [
                        TextInput::make('nominal')->numeric()->integer()->minValue(1)->maxValue($record->sisa())->default($record->sisa())->prefix('Rp')->required(),
                        ...BukuKas::fieldPembayaran(),
                    ])->action(function (Tagihan $record, array $data): void {
                        app(PencatatanKeuangan::class)->catat($data, $record->id);
                        Notification::make()->success()->title('Pembayaran tercatat di buku kas')->send();
                    }),
                Action::make('batalkan')->color('danger')->requiresConfirmation()->visible(fn (Tagihan $record) => $record->dibatalkan_at === null && $record->terbayar() === 0)
                    ->schema([Textarea::make('alasan')->required()->minLength(5)->maxLength(1000)])
                    ->action(fn (Tagihan $record, array $data) => app(PencatatanKeuangan::class)->batalkanTagihan($record->id, $data['alasan'])),
            ])->emptyStateHeading('Belum ada tagihan siswa');
    }
}
