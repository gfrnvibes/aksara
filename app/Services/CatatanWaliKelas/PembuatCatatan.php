<?php

namespace App\Services\CatatanWaliKelas;

/**
 * Writes a homeroom note. The text addresses the student as "Ananda"; the caller adds the nickname afterwards.
 */
interface PembuatCatatan
{
    public function buat(RingkasanRapor $ringkasan): string;
}
