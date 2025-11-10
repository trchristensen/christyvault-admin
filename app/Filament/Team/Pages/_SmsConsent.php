<?php

namespace App\Filament\Team\Pages;

use App\Models\Driver;
use App\Models\Employee;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SmsConsent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?string $navigationLabel = 'SMS Notifications';
    protected static ?string $title = 'SMS Delivery Notifications';
    protected static string $view = 'filament.team.pages.sms-consent';

    public ?array $data = [];
    public Driver $driver;
    public Employee $employee;

    public function mount(): void
    {
        $user = Auth::user();
        
        // Use employee if exists, otherwise create mock data
        $this->employee = $user->employee ?? new Employee(['name' => $user->name, 'phone' => 'N/A']);
        $this->driver = $this->employee->driver ?? new Driver(['sms_consent_given' => false]);
        
        $this->form->fill([
            'driver_name' => $this->employee->name ?? $user->name,
            'phone_number' => $this->employee->phone ?? 'N/A - Contact HR',
            'consent_given' => $this->driver->sms_consent_given ?? false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()
                    ->schema([
                        Placeholder::make('info')
                            ->content('Set up SMS notifications to receive your daily delivery assignments directly on your phone.')
                            ->extraAttributes(['class' => 'text-gray-600']),
                        
                        TextInput::make('driver_name')
                            ->label('Driver Name')
                            ->disabled()
                            ->default($this->employee->name ?? ''),
                        
                        TextInput::make('phone_number')
                            ->label('Phone Number')
                            ->disabled()
                            ->default($this->employee->phone ?? '')
                            ->helperText('Contact HR if your phone number needs updating'),
                        
                        Placeholder::make('current_status')
                            ->label('Current Status')
                            ->content(fn () => $this->driver->sms_consent_given 
                                ? '✅ SMS notifications are ENABLED' . ($this->driver->sms_consent_at ? ' (since ' . $this->driver->sms_consent_at->format('M j, Y') . ')' : '')
                                : '❌ SMS notifications are DISABLED'
                            ),
                    ])
                    ->columnSpan('full'),

                Card::make()
                    ->schema([
                        Placeholder::make('what_youll_receive')
                            ->label('📱 What You\'ll Receive')
                            ->content('
                                • Daily delivery schedules each morning
                                • Links to complete deliveries on your phone  
                                • Order updates when new deliveries are assigned
                                • Delivery confirmations when completed
                                
                                This is for work communications only - no marketing messages.
                            '),
                        
                        Checkbox::make('consent_given')
                            ->label('I agree to receive work-related SMS messages from Christy Vault')
                            ->helperText('You can opt out anytime by texting STOP or contacting your supervisor')
                            ->reactive()
                            ->required(),
                    ])
                    ->columnSpan('full')
                    ->visible(fn () => !$this->driver->sms_consent_given),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        if ($data['consent_given']) {
            $this->driver->update([
                'sms_consent_given' => true,
                'sms_consent_at' => now(),
            ]);

            Notification::make()
                ->title('SMS Notifications Enabled!')
                ->body('You\'ll now receive your daily delivery assignments via text message.')
                ->success()
                ->send();
        }

        // Refresh the page to show updated status
        $this->redirect(static::getUrl());
    }

    public function disable(): void
    {
        $this->driver->update([
            'sms_consent_given' => false,
            'sms_consent_at' => null,
        ]);

        Notification::make()
            ->title('SMS Notifications Disabled')
            ->body('You will no longer receive delivery notifications via text message.')
            ->warning()
            ->send();

        $this->redirect(static::getUrl());
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Enable SMS Notifications')
                ->action('save')
                ->color('success')
                ->visible(fn () => !$this->driver->sms_consent_given),
                
            Action::make('disable')
                ->label('Disable SMS Notifications')
                ->action('disable')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Disable SMS Notifications?')
                ->modalDescription('You will stop receiving delivery assignments via text message.')
                ->visible(fn () => $this->driver->sms_consent_given),
        ];
    }

    public static function canAccess(): bool
    {
        return true; // Show for everyone for now
    }
}
