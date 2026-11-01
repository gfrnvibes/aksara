<?php

namespace App\Filament\Resources\JamPelajarans\Pages;

use App\Enums\Hari;
use App\Filament\Resources\JamPelajarans\JamPelajaranResource;
use App\Models\JamPelajaran;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ManageJamPelajarans extends ManageRecords
{
    protected static string $resource = JamPelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('isiBawaan')
                ->label('Pakai Jam Bawaan')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->authorize('create', JamPelajaran::class)
                ->requiresConfirmation()
                ->modalDescription('Seluruh jam pelajaran diganti dengan jam bawaan: Senin dan Sabtu 8 JP, Selasa–Kamis 9 JP, Jum\'at 5 JP, 1 JP = 35 menit, dengan Shalat Duha/Tadarus, istirahat, dan Shalat Dzuhur. Jadwal yang sudah ada tidak berubah.')
                ->action(function (): void {
                    JamPelajaran::isiBawaan();

                    Notification::make()->success()->title('Jam pelajaran bawaan dipasang')->send();
                }),
            Action::make('salinHari')
                ->label('Salin Hari')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->authorize('create', JamPelajaran::class)
                ->modalWidth('md')
                ->modalDescription('Jam pada hari tujuan diganti dengan salinan jam dari hari sumber.')
                ->schema([
                    Select::make('dari')
                        ->label('Salin dari')
                        ->options(Hari::class)
                        ->required(),
                    CheckboxList::make('ke')
                        ->label('Ke hari')
                        ->options(Hari::class)
                        ->columns(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $sumber = JamPelajaran::query()->where('hari', $data['dari'])->get();
                    $dari = $data['dari'] instanceof Hari ? $data['dari'] : Hari::from((int) $data['dari']);

                    DB::transaction(function () use ($sumber, $data, $dari): void {
                        foreach ($data['ke'] as $ke) {
                            $hari = $ke instanceof Hari ? $ke : Hari::from((int) $ke);

                            if ($hari === $dari) {
                                continue;
                            }

                            JamPelajaran::query()->where('hari', $hari)->get()->each->delete();

                            foreach ($sumber as $jam) {
                                $jam->replicate(['jp_ke'])->fill(['hari' => $hari])->save();
                            }
                        }
                    });

                    Notification::make()->success()->title('Jam pelajaran disalin')->send();
                }),
            CreateAction::make(),
        ];
    }
}
