<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class Ppdb extends Model
{
    protected $table = 'ppdbs';

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }
}
