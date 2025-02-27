<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\Snappy\Facades\SnappyPdf;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;

class DeliveryTagController extends Controller
{
    public function view(Order $order)
    {
        if (!file_exists(public_path('images/form.jpeg'))) {
            abort(404, 'Form template not found');
        }

        // Format phone numbers before passing to view
        $phoneUtil = PhoneNumberUtil::getInstance();
        $contact = $order->location->preferredDeliveryContact;

        if ($contact) {
            try {
                if ($contact->phone) {
                    $phoneNumber = $phoneUtil->parse($contact->phone, 'US');
                    $contact->formatted_phone = $phoneUtil->format($phoneNumber, PhoneNumberFormat::NATIONAL);
                }
                if ($contact->mobile_phone) {
                    $mobileNumber = $phoneUtil->parse($contact->mobile_phone, 'US');
                    $contact->formatted_mobile = $phoneUtil->format($mobileNumber, PhoneNumberFormat::NATIONAL);
                }
            } catch (\Exception $e) {
                // Keep original numbers if parsing fails
                $contact->formatted_phone = $contact->phone;
                $contact->formatted_mobile = $contact->mobile_phone;
            }
        }

        if ($order->location->phone) {
            try {
                $locationNumber = $phoneUtil->parse($order->location->phone, 'US');
                $order->location->formatted_phone = $phoneUtil->format($locationNumber, PhoneNumberFormat::NATIONAL);
            } catch (\Exception $e) {
                $order->location->formatted_phone = $order->location->phone;
            }
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
            ->setOption('background', true)
            ->setOption('window-status', 'ready');

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="order-' . $order->order_number . '.pdf"');
    }
}
