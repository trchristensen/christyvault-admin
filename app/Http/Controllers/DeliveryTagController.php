<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\Snappy\Facades\SnappyPdf;

class DeliveryTagController extends Controller
{
    public function view(Order $order)
    {
        if (!file_exists(public_path('images/form.jpeg'))) {
            abort(404, 'Form template not found');
        }

        $pdf = SnappyPdf::loadView('orders.print', ['order' => $order])
            ->setOption('page-width', '8.5in')
            ->setOption('page-height', '7.625in')
            ->setOption('margin-top', 0)
            ->setOption('margin-right', 0)
            ->setOption('margin-bottom', 0)
            ->setOption('margin-left', 0)
            ->setOption('enable-local-file-access', true)
            ->setOption('enable-smart-shrinking', false)
            ->setOption('zoom', 1.0)
            ->setOption('background', true)  // Enable backgrounds
            ->setOption('window-status', 'ready');  // Wait for window.status to be 'ready'



        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="order-' . $order->order_number . '.pdf"');
    }

    public function viewWithFormBg(Order $order)
    {
        if (!file_exists(public_path('images/form.jpeg'))) {
            abort(404, 'Form template not found');
        }

        $pdf = SnappyPdf::loadView('orders.print-formbg', ['order' => $order])
            ->setOption('page-width', '11in')
            ->setOption('page-height', '8.5in')
            ->setOption('margin-top', 0)
            ->setOption('margin-right', 0)
            ->setOption('margin-bottom', 0)
            ->setOption('margin-left', 0)
            ->setOption('orientation', 'Landscape')
            ->setOption('enable-local-file-access', true)
            ->setOption('enable-smart-shrinking', false)
            ->setOption('zoom', 1.0)
            ->setOption('background', true)
            ->setOption('window-status', 'ready');

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="order-' . $order->order_number . '-formbg.pdf"');
    }
}
