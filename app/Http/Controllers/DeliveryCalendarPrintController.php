<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DeliveryCalendarPrintController extends Controller
{
    public function view()
    {
        // Get start date (next Monday if we're on weekend)
        $startDate = Carbon::now()->startOfWeek();

        // Get end date (next Friday)
        $endDate = Carbon::now()->addWeeks(1)->endOfWeek()->weekday(5); // 5 = Friday

        // Create a collection of weekdays between start and end date
        $weekdays = collect();
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            if ($currentDate->isWeekday()) { // Carbon helper to check if it's a weekday
                $weekdays->push($currentDate->copy());
            }
            $currentDate->addDay();
        }

        // Get orders within date range, excluding weekends
        $orders = Order::query()
            ->with(['orderProducts.product', 'driver'])
            ->whereBetween('assigned_delivery_date', [$startDate, $endDate])
            ->whereRaw("EXTRACT(DOW FROM assigned_delivery_date) BETWEEN 1 AND 5") // Monday = 1, Friday = 5
            ->orderBy('assigned_delivery_date')
            ->get()
            ->groupBy(function ($order) {
                return $order->assigned_delivery_date->format('Y-m-d');
            });

        $pdf = SnappyPdf::loadView('delivery-calendar.print', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'weekdays' => $weekdays,
            'orders' => $orders,
        ])
            ->setOption('margin-top', '10mm')
            ->setOption('margin-right', '10mm')
            ->setOption('margin-bottom', '10mm')
            ->setOption('margin-left', '10mm')
            ->setOption('page-size', 'Letter')
            ->setOption('orientation', 'Landscape');

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="delivery-schedule.pdf"')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
