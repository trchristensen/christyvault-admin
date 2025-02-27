<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;


class DeliveryTagController extends Controller
{
    public function view(Order $order)
    {
        if (!file_exists(public_path('images/form.jpeg'))) {
            abort(404, 'Form template not found');
        }

        // Format phone numbers before passing to view
        $contact = $order->location->preferredDeliveryContact;
        if ($contact) {
            $contact->formatted_phone = $contact->phone ? PhoneInput::format($contact->phone, PhoneInputNumberType::NATIONAL) : null;
            $contact->formatted_mobile = $contact->mobile_phone ? PhoneInput::format($contact->mobile_phone, PhoneInputNumberType::NATIONAL) : null;
        }

        if ($order->location->phone) {
            $order->location->formatted_phone = PhoneInput::format($order->location->phone, PhoneInputNumberType::NATIONAL);
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
}
