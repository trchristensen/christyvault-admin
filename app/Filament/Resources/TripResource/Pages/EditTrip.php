<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use Filament\Resources\Pages\EditRecord;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;

    public $confirmedDateChange = false;

    protected function afterSave(): void
    {
        // If this was a confirmed date change, update the order dates
        if ($this->confirmedDateChange) {
            $this->record->orders()->update([
                'assigned_delivery_date' => $this->record->scheduled_date
            ]);

            Notification::make()
                ->success()
                ->title('Order Dates Updated')
                ->body('All associated order delivery dates have been updated.')
                ->send();
        }

        $this->confirmedDateChange = false;
    }
}
