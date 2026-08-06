<?php

namespace App\Livewire\Profile;

use App\Services\SharedProfileUpdater;
use Filament\Forms\Components\TextInput;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;

class AccountInformation extends PersonalInfo
{
    protected string $view = 'filament.profile.account-information';

    /**
     * Breezy returns an Illuminate Stringable here, but Livewire snapshots
     * require the internal component name to be a native string.
     */
    public function getName(): string
    {
        return (string) $this->__name;
    }

    protected function getNameComponent(): TextInput
    {
        return TextInput::make('name')
            ->label('Display name')
            ->helperText('The name shown in the application. This does not change your official employee name.')
            ->required()
            ->maxLength(255);
    }

    protected function getEmailComponent(): TextInput
    {
        return TextInput::make('email')
            ->label('Login email')
            ->helperText('Magic links are sent here. Ask an administrator to change this address.')
            ->email()
            ->disabled()
            ->dehydrated(false);
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $this->user = app(SharedProfileUpdater::class)->updateAccount($this->user, $data);

        $this->sendNotification();
    }
}
