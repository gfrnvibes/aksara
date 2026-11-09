<?php

namespace App\Services\Keuangan;

use App\Models\TagihanSiswa;
use App\Models\TransaksiKeuangan;
use Illuminate\Database\Eloquent\Builder;

class RingkasanTagihan
{
    /**
     * Aggregate payments per invoice before joining, so installments never multiply billed amounts.
     *
     * @param  Builder<TagihanSiswa>|null  $tagihan
     * @return Builder<TagihanSiswa>
     */
    public static function query(?Builder $tagihan = null): Builder
    {
        $pembayaran = TransaksiKeuangan::query()->whereNull('dibatalkan_at')
            ->select('tagihan_siswa_id')->selectRaw('sum(nominal) as terbayar')->groupBy('tagihan_siswa_id');
        $ringkasan = ($tagihan ?? TagihanSiswa::query())
            ->leftJoinSub($pembayaran, 'pembayaran_tagihan', 'pembayaran_tagihan.tagihan_siswa_id', '=', 'tagihan_siswas.id')
            ->select('tagihan_siswas.nama')
            ->selectRaw('min(tagihan_siswas.id) as id, count(distinct siswa_id) as jumlah_siswa, count(*) as jumlah_tagihan')
            ->selectRaw('sum(case when tagihan_siswas.dibatalkan_at is null then tagihan_siswas.nominal else 0 end) as total_nominal')
            ->selectRaw('sum(case when tagihan_siswas.dibatalkan_at is null then coalesce(pembayaran_tagihan.terbayar, 0) else 0 end) as total_terbayar')
            ->selectRaw('sum(case when tagihan_siswas.dibatalkan_at is null then tagihan_siswas.nominal - coalesce(pembayaran_tagihan.terbayar, 0) else 0 end) as total_sisa')
            ->selectRaw('sum(case when tagihan_siswas.dibatalkan_at is not null then 1 else 0 end) as jumlah_batal')
            ->groupBy('tagihan_siswas.nama');

        return TagihanSiswa::query()
            ->joinSub($ringkasan, 'ringkasan_tagihan', 'tagihan_siswas.id', '=', 'ringkasan_tagihan.id')
            ->select('tagihan_siswas.*', 'ringkasan_tagihan.jumlah_siswa', 'ringkasan_tagihan.jumlah_tagihan',
                'ringkasan_tagihan.total_nominal', 'ringkasan_tagihan.total_terbayar', 'ringkasan_tagihan.total_sisa', 'ringkasan_tagihan.jumlah_batal');
    }
}
