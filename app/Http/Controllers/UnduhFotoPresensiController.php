<?php

namespace App\Http\Controllers;

use App\Models\PresensiGtk;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnduhFotoPresensiController extends Controller
{
    public function __invoke(Request $request, PresensiGtk $presensiGtk): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $isOwner = $presensiGtk->gtk->user_id === $user->id;
        $isPiket = AksesHarian::piket($user, $presensiGtk->tanggal->format('Y-m-d'));
        abort_unless($isOwner || $isPiket || AksesHarian::pengelola($user), 403);

        $path = $presensiGtk->foto_path;
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
