<?php

namespace App\Filament\Guru\Resources\ProgramSemesters\Pages;

use App\Filament\Guru\Resources\ProgramSemesters\ProgramSemesterResource;
use App\Models\ProgramSemester;
use App\Models\ProgramTahunan;
use App\Services\Gemini\KlienGemini;
use App\Services\Kurikulum\PenyusunProsemKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditProgramSemester extends EditRecord
{
    protected static string $resource = ProgramSemesterResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveHeader')
                ->label('Simpan')
                ->color('primary')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('cetak')
                ->label('Cetak')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('prosem.cetak', $this->record))
                ->openUrlInNewTab(),

            Action::make('tarikDariProta')
                ->label('Tarik PROTA')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('info')
                ->modalHeading('Tarik Data dari Program Tahunan (PROTA)')
                ->modalDescription('Pilih PROTA untuk menjabarkan ulang Tujuan Pembelajaran dan Materi Pokok ke jadwal Program Semester ini.')
                ->modalSubmitActionLabel('Terapkan')
                ->schema(function (): array {
                    /** @var ProgramSemester $prosem */
                    $prosem = $this->record;
                    $gtk = ProgramSemesterResource::gtk();

                    return [
                        Select::make('program_tahunan_id')
                            ->label('Pilih PROTA')
                            ->options(function () use ($gtk): array {
                                return ProgramTahunan::query()
                                    ->where('gtk_id', $gtk->id ?? 0)
                                    ->with(['mataPelajaran', 'kelas', 'tahunAjaran'])
                                    ->get()
                                    ->mapWithKeys(fn (ProgramTahunan $p) => [$p->id => "PROTA {$p->mataPelajaran->nama} {$p->kelas->nama} ({$p->tahunAjaran->nama})"])
                                    ->all();
                            })
                            ->default($prosem->program_tahunan_id)
                            ->required()
                            ->searchable(),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var ProgramSemester $prosem */
                    $prosem = $this->record;

                    /** @var ProgramTahunan $prota */
                    $prota = ProgramTahunan::query()->findOrFail((int) $data['program_tahunan_id']);

                    $penyusun = app(PenyusunProsemKurikulumMerdeka::class);
                    $hasil = $penyusun->salinDariProta($prota, $prosem->semester, $prosem->jp_per_minggu);

                    $prosem->update([
                        'program_tahunan_id' => $prota->id,
                        'catatan' => $hasil['catatan'],
                    ]);

                    $penyusun->terapkanKeProsem($prosem, $hasil['items']);
                    $prosem->refresh();

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('PROSEM Berhasil Dijabarkan dari PROTA')
                        ->body(count($hasil['items']).' butir materi telah dimuat ke dalam jadwal semester.')
                        ->send();
                }),

            Action::make('muatDrafStandar')
                ->label('Draf Standar')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Muat Draf Standar Kurikulum Merdeka')
                ->modalDescription('Seluruh rincian materi semester saat ini akan diganti dengan susunan kalender Kurikulum Merdeka standar yang realistis. Lanjutkan?')
                ->modalSubmitActionLabel('Ya, Terapkan Draf')
                ->action(function (): void {
                    /** @var ProgramSemester $prosem */
                    $prosem = $this->record;

                    $penyusun = app(PenyusunProsemKurikulumMerdeka::class);
                    $hasil = $penyusun->dapatkanDrafStandar(
                        $prosem->mataPelajaran->nama,
                        $prosem->fase,
                        $prosem->semester,
                        $prosem->jp_per_minggu
                    );

                    $prosem->update(['catatan' => $hasil['catatan']]);
                    $penyusun->terapkanKeProsem($prosem, $hasil['items']);
                    $prosem->refresh();

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Draf Kurikulum Merdeka berhasil diterapkan')
                        ->body(count($hasil['items']).' materi pembelajaran telah disusun untuk semester ini.')
                        ->send();
                }),

            Action::make('susunDenganAi')
                ->label('AI Gemini')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('success')
                ->modalHeading('Generate Ulang PROSEM dengan AI Gemini')
                ->modalDescription('AI Gemini akan menyusun ulang rincian Tujuan Pembelajaran, materi pokok, dan distribusi jam per minggu sesuai permintaan Anda.')
                ->modalSubmitActionLabel('Generate dengan AI')
                ->modalWidth('lg')
                ->schema(function (): array {
                    /** @var ProgramSemester $prosem */
                    $prosem = $this->record;

                    return [
                        TextInput::make('jp_per_minggu')
                            ->label('Alokasi Jam per Minggu')
                            ->numeric()
                            ->default($prosem->jp_per_minggu)
                            ->minValue(1)
                            ->maxValue(10)
                            ->suffix('JP / minggu')
                            ->required(),

                        Textarea::make('instruksi_khusus')
                            ->label('Instruksi / Catatan Khusus untuk AI (Opsional)')
                            ->rows(3)
                            ->maxLength(1000),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var ProgramSemester $prosem */
                    $prosem = $this->record;

                    $jp = (int) ($data['jp_per_minggu'] ?? $prosem->jp_per_minggu);
                    $instruksiKhusus = ! empty($data['instruksi_khusus']) ? (string) $data['instruksi_khusus'] : null;

                    try {
                        $penyusun = app(PenyusunProsemKurikulumMerdeka::class);
                        $hasil = $penyusun->susunProsemLengkap(
                            (int) (auth()->id() ?? 0),
                            $prosem->mataPelajaran->nama,
                            $prosem->kelas->nama,
                            $prosem->fase,
                            $prosem->semester,
                            $jp,
                            $instruksiKhusus
                        );

                        $prosem->update([
                            'jp_per_minggu' => $jp,
                            'catatan' => $hasil['catatan'],
                        ]);
                        $penyusun->terapkanKeProsem($prosem, $hasil['items']);
                        $prosem->refresh();

                        $this->fillForm();

                        Notification::make()
                            ->success()
                            ->title('PROSEM Berhasil Disusun oleh AI Gemini')
                            ->body(count($hasil['items']).' materi pembelajaran telah diperbarui.')
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Gagal menyusun dengan AI')
                            ->body(KlienGemini::alasanGagal($e))
                            ->send();
                    }
                }),

            DeleteAction::make(),
        ];
    }
}
