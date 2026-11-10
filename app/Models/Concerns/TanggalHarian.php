<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

trait TanggalHarian
{
    /** @return Attribute<never, string> */
    protected function tanggal(): Attribute
    {
        // Store a SQL DATE consistently, including on SQLite, where a date cast
        // otherwise writes a timestamp that will not match a YYYY-MM-DD query.
        return Attribute::make(set: fn (string $value): string => Carbon::parse($value)->toDateString());
    }
}
