<?php

namespace App\Livewire\Profile;

use App\Services\SharedProfileUpdater;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class EmployeeInformation extends MyProfileComponent
{
    protected string $view = 'filament.profile.employee-information';

    public ?array $data = [];

    public $employee;

    public static $sort = 20;

    /**
     * Breezy returns an Illuminate Stringable here, but Livewire snapshots
     * require the internal component name to be a native string.
     */
    public function getName(): string
    {
        return (string) $this->__name;
    }

    public static function canView(): bool
    {
        return auth()->user()?->employee !== null;
    }

    public function mount(): void
    {
        $this->employee = Filament::getCurrentOrDefaultPanel()->auth()->user()?->employee;

        abort_unless($this->employee, 403);

        $this->form->fill([
            'phone' => $this->employee->phone,
            'address' => $this->employee->address,
            'official_name' => $this->employee->name,
            'plant' => ucfirst((string) $this->employee->christy_location),
            'hire_date' => $this->employee->hire_date?->format('M j, Y'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                PhoneInput::make('phone')
                    ->label('Contact phone')
                    ->defaultCountry('US')
                    ->helperText('Contact information only. It does not change how you sign in.'),
                TextInput::make('address')
                    ->label('Mailing address')
                    ->maxLength(255),
                TextInput::make('official_name')
                    ->label('Official employee name')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('plant')
                    ->label('Plant')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('hire_date')
                    ->label('Hire date')
                    ->placeholder('Not recorded')
                    ->disabled()
                    ->dehydrated(false),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function submit(): void
    {
        $user = auth()->user();

        abort_unless($user?->employee?->is($this->employee), 403);

        $data = $this->form->getState();
        $this->employee = app(SharedProfileUpdater::class)->updateEmployeeContact($user, $data);

        Notification::make()
            ->success()
            ->title('Contact information updated')
            ->send();
    }
}
