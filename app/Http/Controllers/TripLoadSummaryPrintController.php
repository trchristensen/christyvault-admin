<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\LoadPlanning\TripLoadPlanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripLoadSummaryPrintController extends Controller
{
    public function __invoke(
        Request $request,
        Trip $trip,
        TripLoadPlanService $loadPlans,
    ): View {
        $user = $request->user();

        abort_unless(
            $user?->hasAnyRole(['admin', 'super-admin'])
                && $trip->loadSummaryIsVisibleTo($user),
            403,
        );

        $plan = $loadPlans->forTrip($trip);

        return view('filament.resources.trip-resource.load-summary-print', [
            'trip' => $trip,
            'result' => $plan['demand']->toArray(),
            'diagram' => $plan['diagram'],
            'fillAllocations' => $plan['fill_allocations'],
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
