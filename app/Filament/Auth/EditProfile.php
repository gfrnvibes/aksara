<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use SensitiveParameter;

/**
 * The account page, where a teacher given a starting password must choose their own before using the panel.
 */
class EditProfile extends BaseEditProfile
{
    /**
     * Set when this save replaced the starting password, to send the teacher on to the panel.
     */
    protected bool $passwordAwalDiganti = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Password Anda masih password awal dari operator. Buat password baru terlebih dahulu sebelum memakai aplikasi.')
                ->color('warning')
                ->visible(fn (): bool => $this->wajibGantiPassword()),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        $password = parent::getPasswordFormComponent();

        return $password instanceof TextInput
            ? $password->required(fn (): bool => $this->wajibGantiPassword())
            : $password;
    }

    /**
     * Teachers sign in with their NIP, NUPTK or phone number, so the email address is optional.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email (opsional)')
            ->email()
            ->maxLength(255)
            ->unique(ignoreRecord: true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        if (filled($data['password'] ?? null) && $this->wajibGantiPassword()) {
            $data['wajib_ganti_password'] = false;
            $this->passwordAwalDiganti = true;
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->passwordAwalDiganti ? filament()->getUrl() : null;
    }

    protected function wajibGantiPassword(): bool
    {
        $user = $this->getUser();

        return $user instanceof User && $user->wajib_ganti_password;
    }
}
