<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Models\Trip;
use App\Services\LoadPlanning\TripLoadPlanService;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

final class TripLoadSummaryAction
{
    public const ICON = 'heroicon-o-cube-transparent';

    public static function make(string $name = 'loadSummary'): Action
    {
        return Action::make($name)
            ->label('Load Summary')
            ->icon(self::ICON)
            ->color('gray')
            ->visible(fn (Model $record): bool => self::hasTrip($record)
                && self::tripFor($record)->loadSummaryIsVisibleTo(auth()->user()))
            ->modalHeading(function (Model $record): string {
                $trip = self::authorizedTripFor($record);

                return "Load summary — {$trip->trip_number}";
            })
            ->modalContent(function (Model $record) {
                $trip = self::authorizedTripFor($record);
                $plan = app(TripLoadPlanService::class)->forTrip($trip);

                return view('filament.resources.trip-resource.load-summary', [
                    'result' => $plan['demand']->toArray(),
                    'diagram' => $plan['diagram'],
                    'fillAllocations' => $plan['fill_allocations'],
                    'printUrl' => route('trips.load-summary.print', [
                        'trip' => $trip,
                        'print' => 1,
                    ]),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(fn (Model $record): string => $record instanceof Order ? 'Back to order' : 'Close')
            ->modalWidth('7xl');
    }

    private static function hasTrip(Model $record): bool
    {
        return $record instanceof Trip
            || ($record instanceof Order && filled($record->trip_id));
    }

    private static function tripFor(Model $record): Trip
    {
        if ($record instanceof Trip) {
            return $record;
        }

        if ($record instanceof Order && filled($record->trip_id)) {
            if ($record->relationLoaded('trip') && $record->trip) {
                return $record->trip;
            }

            return Trip::query()->findOrFail($record->trip_id);
        }

        abort(404, 'This order is not assigned to a trip.');
    }

    private static function authorizedTripFor(Model $record): Trip
    {
        $trip = self::tripFor($record);

        abort_unless($trip->loadSummaryIsVisibleTo(auth()->user()), 403);

        return $trip;
    }
}
