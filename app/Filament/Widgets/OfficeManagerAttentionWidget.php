<?php

namespace App\Filament\Widgets;

use App\Models\Employee;
use App\Models\Trip;
use App\Services\SplitLoadService;
use App\Support\OfficeManagerDashboard;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Validation\ValidationException;

class OfficeManagerAttentionWidget extends Widget
{
    public array $driverSelections = [];

    protected string $view = 'filament.widgets.office-manager-attention-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function assignDriver(int $tripId): void
    {
        abort_unless(auth()->user()?->canAccessPanelById('admin'), 403);

        $driverId = (int) ($this->driverSelections[$tripId] ?? 0);

        if ($driverId < 1) {
            $this->addError("driverSelections.{$tripId}", 'Choose a driver.');

            return;
        }

        try {
            $trip = app(SplitLoadService::class)->assignDriver(
                Trip::query()->findOrFail($tripId),
                $driverId,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Choose an active employee with the driver position.';

            $this->addError("driverSelections.{$tripId}", $message);

            return;
        }

        unset($this->driverSelections[$tripId]);

        Notification::make()
            ->title('Driver assigned')
            ->body("{$trip->trip_number} is assigned to {$trip->driver->name}.")
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        return [
            'items' => app(OfficeManagerDashboard::class)->attentionItems(),
            'drivers' => Employee::query()
                ->where('is_active', true)
                ->whereHas('positions', fn ($query) => $query->where('name', 'driver'))
                ->orderBy('name')
                ->pluck('name', 'id'),
        ];
    }
}
