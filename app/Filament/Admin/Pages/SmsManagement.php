<?php

namespace App\Filament\Admin\Pages;

use App\Models\Employee;
use App\Services\SmsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class SmsManagement extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static string $view = 'filament.admin.pages.sms-management';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $title = 'SMS Management';

    public ?array $testSmsData = [];
    public ?array $dailyScheduleData = [];

    public function mount(): void
    {
        $this->testSmsForm->fill();
        $this->dailyScheduleForm->fill();
    }

    public function testSmsForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('driver_id')
                    ->label('Driver')
                    ->options(Employee::whereNotNull('phone')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Textarea::make('message')
                    ->label('Test Message')
                    ->default('This is a test message from ChristyVault SMS system.')
                    ->required()
                    ->rows(3),
            ])
            ->statePath('testSmsData');
    }

    public function dailyScheduleForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('driver_id')
                    ->label('Driver (Optional)')
                    ->placeholder('Send to all drivers')
                    ->options(Employee::whereNotNull('phone')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->statePath('dailyScheduleData');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestSms')
                ->label('Send Test SMS')
                ->form([
                    Select::make('driver_id')
                        ->label('Driver')
                        ->options(Employee::whereNotNull('phone')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Textarea::make('message')
                        ->label('Test Message')
                        ->default('This is a test message from ChristyVault SMS system.')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data, SmsService $smsService): void {
                    $driver = Employee::find($data['driver_id']);
                    
                    if (!$driver || !$driver->phone) {
                        Notification::make()
                            ->title('Error')
                            ->body('Driver not found or has no phone number')
                            ->danger()
                            ->send();
                        return;
                    }

                    $success = $smsService->sendSms($driver->phone, $data['message']);
                    
                    if ($success) {
                        Notification::make()
                            ->title('Success')
                            ->body("Test SMS sent to {$driver->name}")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Failed')
                            ->body("Failed to send SMS to {$driver->name}")
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-paper-airplane')
                ->color('primary'),

            Action::make('sendDailySchedule')
                ->label('Send Daily Schedule')
                ->form([
                    Select::make('driver_id')
                        ->label('Driver (Optional)')
                        ->placeholder('Send to all drivers')
                        ->options(Employee::whereNotNull('phone')->pluck('name', 'id'))
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $command = 'sms:daily-schedule';
                    
                    if (isset($data['driver_id']) && $data['driver_id']) {
                        $command .= ' --driver=' . $data['driver_id'];
                    }
                    
                    $exitCode = Artisan::call($command);
                    
                    if ($exitCode === 0) {
                        Notification::make()
                            ->title('Success')
                            ->body('Daily schedule SMS sent successfully')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Failed')
                            ->body('Failed to send daily schedule SMS')
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-calendar-days')
                ->color('success'),

            Action::make('testDryRun')
                ->label('Test (Dry Run)')
                ->action(function (): void {
                    $exitCode = Artisan::call('sms:daily-schedule --dry-run');
                    $output = Artisan::output();
                    
                    Notification::make()
                        ->title('Dry Run Complete')
                        ->body('Check the logs for details')
                        ->info()
                        ->send();
                })
                ->icon('heroicon-o-play')
                ->color('gray'),
        ];
    }

    public function getSmsStats(): array
    {
        $driversWithPhone = Employee::whereNotNull('phone')->count();
        $driversWithDeliveriesToday = Employee::whereHas('driverTrips', function ($q) {
            $q->whereDate('scheduled_date', today());
        })->count();

        return [
            'drivers_with_phone' => $driversWithPhone,
            'drivers_with_deliveries_today' => $driversWithDeliveriesToday,
            'sms_enabled' => config('sms.enabled'),
            'daily_schedule_enabled' => config('sms.daily_schedule.enabled'),
            'daily_schedule_time' => config('sms.daily_schedule.time'),
        ];
    }
} 