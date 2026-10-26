<?php

namespace App\Models\Contracts;

/**
 * Master data that refuses to be deleted while students or report cards still reference it.
 */
interface TerlindungDariHapus
{
    /**
     * Human-readable description of where the record is still used, or null when it can be deleted.
     */
    public function keteranganPemakaian(): ?string;
}
