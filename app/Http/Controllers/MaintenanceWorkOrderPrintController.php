<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceWorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MaintenanceWorkOrderPrintController extends Controller
{
    public function __invoke(Request $request, MaintenanceWorkOrder $workOrder): View
    {
        abort_unless(
            $request->user() && Gate::forUser($request->user())->allows('view', $workOrder),
            403,
        );

        $workOrder->load([
            'asset.location',
            'assignedTo',
            'fleetServiceRun.plan',
        ]);

        return view('maintenance.work-order-print', [
            'workOrder' => $workOrder,
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
