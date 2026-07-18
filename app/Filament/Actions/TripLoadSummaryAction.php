<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Models\Trip;
use App\Services\LoadPlanning\LoadDemandService;
use App\Services\LoadPlanning\RackDiagramService;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class TripLoadSummaryAction
{
    public static function make(string $name = 'loadSummary'): Action
    {
        return Action::make($name)
            ->label('Load Summary')
            ->icon('heroicon-o-cube-transparent')
            ->color('info')
            ->visible(fn (Model $record): bool => self::hasTrip($record) && Gate::allows('view load summary'))
            ->modalHeading(function (Model $record): string {
                $trip = self::tripFor($record);

                return "Load summary — {$trip->trip_number}";
            })
            ->modalContent(function (Model $record) {
                $demand = app(LoadDemandService::class)->forTrip(self::tripFor($record));

                return view('filament.resources.trip-resource.load-summary', [
                    'result' => $demand->toArray(),
                    'diagram' => app(RackDiagramService::class)->forDemand($demand),
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
            return Trip::query()->findOrFail($record->trip_id);
        }

        abort(404, 'This order is not assigned to a trip.');
    }
}
