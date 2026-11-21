<?php

namespace App\Console\Commands;

use App\Models\PushToken;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PertemuanMengajar;
use App\Services\Notifikasi\KlienExpoPush;
use Illuminate\Console\Command;

class KirimNotifikasiPagiGuruCommand extends Command
{
    protected $signature = 'aksara:notifikasi-pagi-guru';

    protected $description = 'Mengirim notifikasi pagi ke guru yang memiliki jadwal mengajar hari ini';

    public function handle(PertemuanMengajar $pertemuan, KlienExpoPush $expo): int
    {
        $tanggal = AksesHarian::hariIni();
        if (AksesHarian::libur($tanggal)) {
            $this->info("Hari libur ({$tanggal}), tidak ada notifikasi pagi yang dikirim.");

            return self::SUCCESS;
        }

        $tokensByUser = PushToken::query()
            ->with('user.gtk')
            ->get()
            ->groupBy('user_id');

        $messages = [];

        foreach ($tokensByUser as $userTokens) {
            /** @var User|null $user */
            $user = $userTokens->first()?->user;
            if (! $user instanceof User || $user->gtk === null || ! $user->gtk->is_aktif) {
                continue;
            }

            $sesi = $pertemuan->daftar($user, $tanggal, false)->values();
            if ($sesi->isEmpty()) {
                continue;
            }

            $pertama = $sesi->first();
            $jumlah = $sesi->count();
            $mulai = filled($pertama['mulai'] ?? null) ? substr((string) $pertama['mulai'], 0, 5) : null;
            $infoMulai = $mulai !== null
                ? "mulai JP {$pertama['jp_mulai']} pukul {$mulai} di kelas {$pertama['nama_kelas']}"
                : "mulai JP {$pertama['jp_mulai']} di kelas {$pertama['nama_kelas']}";

            $body = "Selamat pagi, {$user->gtk->nama}. Anda memiliki {$jumlah} sesi mengajar hari ini ({$infoMulai}).";

            foreach ($userTokens as $pushToken) {
                $messages[] = [
                    'to' => $pushToken->token,
                    'title' => 'Jadwal Mengajar Hari Ini',
                    'body' => $body,
                    'sound' => 'default',
                    'data' => ['screen' => 'jadwal', 'tanggal' => $tanggal],
                ];
            }
        }

        $terkirim = $expo->kirim($messages);
        $this->info("Notifikasi pagi dikirim ke {$terkirim} perangkat.");

        return self::SUCCESS;
    }
}
