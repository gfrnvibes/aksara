<?php

namespace App\Http\Middleware;

use App\Filament\Auth\EditProfile;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a user who still has the starting password from an operator on the account page until they choose
 * their own.
 */
class WajibGantiPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->wajib_ganti_password || $request->routeIs(EditProfile::getRouteName(), Filament::getCurrentPanel()?->generateRouteName('auth.logout') ?? '')) {
            return $next($request);
        }

        return redirect()->to(EditProfile::getUrl());
    }
}
