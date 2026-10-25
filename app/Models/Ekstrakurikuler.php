<?php

namespace App\Models;

use Database\Factories\EkstrakurikulerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property bool $is_wajib
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'is_wajib'])]
class Ekstrakurikuler extends Model
{
    /** @use HasFactory<EkstrakurikulerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_wajib' => 'boolean',
        ];
    }
}
