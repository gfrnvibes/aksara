<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

/**
 * Signs in with an email address, or — for teachers, who often have none — the NIP, NUPTK or phone number of
 * their GTK record.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email / NIP / NUPTK / No. HP')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * Look the account up by its key, so the identifier may be any of the accepted kinds.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'id' => User::cariDariLogin((string) $data['email'])->id ?? 0,
            'password' => $data['password'],
        ];
    }
}
