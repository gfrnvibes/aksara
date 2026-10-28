<div class="kop">
    <div class="instansi">KEMENTERIAN AGAMA REPUBLIK INDONESIA</div>
    <div class="madrasah">{{ strtoupper(config('sekolah.nama')) }}</div>
    @if (filled(config('sekolah.alamat')))
        <div class="alamat">{{ config('sekolah.alamat') }}</div>
    @endif
    <div class="alamat">{{ config('sekolah.wilayah') }}</div>
</div>
