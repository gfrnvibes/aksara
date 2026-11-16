<?php

namespace App\Filament\Guru\Resources\ProgramSemesters\Pages;

use App\Filament\Guru\Resources\ProgramSemesters\ProgramSemesterResource;
use App\Models\Gtk;
use App\Services\Kurikulum\PenyusunProsemKurikulumMerdeka;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateProgramSemester extends CreateRecord
{
    protected static string $resource = ProgramSemesterResource::class;

    protected function getHeaderActions(): array
    {
        $gtk = ProgramSemesterResource::gtk();

        return [
            Action::make('generateDenganAi')
                ->label('Generate Langsung dengan AI (Gemini)')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('success')
                ->modalHeading('Generate PROSEM Lengkap dengan AI Gemini')
                ->modalDescription('AI Gemini akan bertindak sebagai ahli kurikulum untuk menyusun Program Semester secara terperinci (identitas, Tujuan Pembelajaran, materi pokok, alokasi JP, dan jadwal bulan serta minggu).')
                ->modalSubmitActionLabel('Mulai Generate PROSEM')
                ->modalWidth('2xl')
                ->schema(fn (): array => ListProgramSemesters::skemaModalGenerateAi($gtk))
                ->action(function (array $data) use ($gtk): void {
                    abort_unless($gtk instanceof Gtk, 403);

                    $penyusun = app(PenyusunProsemKurikulumMerdeka::class);

                    $prosem = $penyusun->buatProsemLengkap(
                        (int) (auth()->id() ?? 0),
                        $gtk,
                        (int) $data['tahun_ajaran_id'],
                        (int) $data['kelas_id'],
                        (int) $data['mata_pelajaran_id'],
                        (int) $data['semester'],
                        (string) ($data['fase'] ?? 'D'),
                        (int) ($data['jp_per_minggu'] ?? 4),
                        ! empty($data['instruksi_khusus']) ? (string) $data['instruksi_khusus'] : null,
                        ! empty($data['program_tahunan_id']) ? (int) $data['program_tahunan_id'] : null
                    );

                    Notification::make()
                        ->success()
                        ->title('PROSEM Berhasil Disusun oleh AI Gemini')
                        ->body("Program Semester {$prosem->mataPelajaran->nama} {$prosem->kelas->nama} telah dibuat lengkap dengan {$prosem->items()->count()} materi pembelajaran.")
                        ->send();

                    $this->redirect(ProgramSemesterResource::getUrl('edit', ['record' => $prosem]));
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['gtk_id'] = ProgramSemesterResource::gtk()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ProgramSemesterResource::getUrl('edit', ['record' => $this->record]);
    }
}
