<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        /* .page-container {
    position: relative;
    width: 100%;
    height: 1056px; /* Adjust this to match your form height exactly */
        page-break-after: avoid;
        }

        */ @page {
            size: letter;
            margin: 0;
        }


        body {
            min-height: 965px;
            position: relative;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-size: 20px;
            line-height: 18px;
            /* font-family: Arial, sans-serif; */
            font-family: 'Monaco-Bold', 'Monaco';
            font-weight: bold;
            /* font-family: 'Courier New', Courier, monospace; */
            color: #000;
            /* font-weight: 700; */
        }

        /* Keep your existing positioning styles */
        .customer-info {
            position: absolute;
            top: 216px;
            left: 80px;
            width: 1000px;
        }

        .order-info {
            position: absolute;
            top: 220px;
            right: 25px;
            text-align: left;
            /* background: #ccc; */
            width: 220px;
            padding-left: 10px;
        }

        .invoice-date {
            padding-left: 15px;
        }

        /* Existing display block styles */
        .customer-name,
        .customer-address,
        .customer-phone,
        .invoice-date,
        .order-date,
        .order-number {
            display: block;
        }

        .customer-name,
        .customer-address,
        .invoice-date,
        .order-number,
        .order-date {
            margin-bottom: 25px;
        }


        /* Updated items section */
        .items {
            position: absolute;
            top: 380px;
            left: 30px;
            width: calc(100% - 60px);
        }

        .item {
            margin-bottom: 23px;
            /* Increased for better spacing */
            position: relative;
            width: 1050px;
            /* background: #ccc; */
        }

        .item-quantity {
            width: 40px;
            text-align: center;
            position: absolute;
            left: 0;
            font-size: 20px;
            font-weight: bold;
        }

        .item-details {
            margin-left: 55px;
        }

        .item-price {
            position: absolute;
            right: 165px;
            top: 4px;
        }

        .item-amount {
            position: absolute;
            right: 35px;
            top: 4px;
        }

        .item-location {
            position: absolute;
            left: 460px;
            top: 2px;
            font-size: 16px;

        }

        .item-sku {
            font-size: 20px;
            font-weight: bold;
        }

        .item-name {
            font-size: 16px;
            margin-left: 0px;
            /* font-weight: lighter; */
        }

        .item-notes {
            margin-left: 5px;
            margin-top: 15px;
            font-size: 18px;
            line-height: 1.4;
        }

        /* Keep existing delivery info styles */
        .delivery-info {
            position: absolute;
            top: 820px;
            left: 30px;
            min-width: 1025px;
        }

        .delivery-time {
            position: absolute;
            left: 220px;
            top: 0;
            bottom: 0;
        }

        .service-date {
            position: absolute;
            left: 585px;
            top: 0;
            bottom: 0;
        }

        .service-time {
            position: absolute;
            left: 907px;
            top: 0;
            bottom: 0;
        }

        .delivery-date {
            font-weight: bold;
            position: absolute;
            left: 60px;
            top: 7px;
            bottom: 0;
        }

        .delivery-time {
            position: absolute;
            left: 400px;
            top: 0;
            bottom: 0;
        }

        .instructions {
            position: absolute;
            top: 850px;
            left: 30px;
            width: calc(100% - 80px);
            min-width: 760px;
            text-indent: 100px;
            /* height: 118px; */
            height: 60px;
            overflow: hidden;
            line-height: 30px;
            /* border: 1px solid red; */
        }

        .cemetery-time {
            position: absolute;
            top: 910px;
            left: 30px;
            width: 970px;
            min-width: 760px;
            height: 30px;
            overflow: hidden;
            line-height: 30px;
            /* border: 1px solid red; */
        }

        .cemetery-time span {
            margin-right: 300px;
            margin-right: 150px;
        }



        .template {
            opacity: 1;
            /* opacity: 0; */
        }
    </style>
</head>

<body>
    <img class="template" src="file://{{ public_path('images/form.jpeg') }}" style="width: 100%; object-fit: contain; object-position: top left; position: absolute; top: 0; left: 0; z-index: 0;">
    <article style="position: relative; z-index: 1;">
        {{-- Customer Info Section --}}
        <div class="customer-info">
            <span class="customer-name">{{ $order->location->name }}</span>
            <span class="customer-address">{{ $order->location->full_address }}</span>
            <span class="customer-phone">
                @php
                    use Propaganistas\LaravelPhone\PhoneNumber;

                    // Format preferred contact's phone if available
if ($order->location->preferredDeliveryContact) {
    $contact = $order->location->preferredDeliveryContact;
    $phone = $contact->phone;
    $mobile = $contact->mobile_phone;

    // Format main phone
    try {
        $formattedPhone = (new PhoneNumber($phone, 'US'))->format('(###) ###-####');
    } catch (\Exception $e) {
        $formattedPhone = $phone;
    }

    // Format mobile phone
    try {
        $formattedMobile = $mobile
            ? (new PhoneNumber($mobile, 'US'))->format('(###) ###-####')
            : null;
    } catch (\Exception $e) {
        $formattedMobile = $mobile;
    }

    echo "Contact: {$contact->name}";
    if ($formattedPhone) {
        echo " - {$formattedPhone}";
        if ($contact->phone_extension) {
            echo " x{$contact->phone_extension}";
        }
    }
    if ($formattedMobile) {
        echo " • M: {$formattedMobile}";
    }
}
// Format location phone as fallback
elseif ($order->location->phone) {
    try {
        $formattedPhone = (new PhoneNumber($order->location->phone, 'US'))->format(
            '(###) ###-####',
                            );
                        } catch (\Exception $e) {
                            $formattedPhone = $order->location->phone;
                        }

                        echo $formattedPhone;
                        if ($order->location->phone_extension) {
                            echo " x{$order->location->phone_extension}";
                        }
                    }
                @endphp
            </span>
        </div>

        {{-- Order Info Section --}}
        <div class="order-info">
            <div class="invoice-date" style="height:18px;"></div>
            <div class="order-number" style="height:18px;"></div>
            <div class="order-date" style="height:18px;">
                {{ $order->order_date?->format('m/d/Y') ?? $order->created_at->format('m/d/Y') }}
            </div>
        </div>

        {{-- Items Section with Updated Format --}}
        <div class="items">
            @foreach ($order->orderProducts as $item)
                <div class="item">
                    @if (!$item->fill_load)
                        <span class="item-quantity">{{ $item->quantity }}</span>
                    @else
                        <span class="item-quantity">*</span>
                    @endif
                    <div class="item-details">
                        <span class="item-sku">{{ $item->product->sku }}</span>
                        <span class="item-name">{{ $item->product->name }}</span>
                        @if ($item->notes or $item->fill_load)
                            <div class="item-notes">
                                └ @if ($item->fill_load)
                                    <strong style="margin-right:12px;text-decoration:underline">FILL OUT LOAD</strong>
                                @endif{{ $item->notes }}
                            </div>
                        @endif
                    </div>
                    <div class="item-location">
                        {{ $item->location }}
                    </div>
                    {{-- <div class="item-price">
                        ${{ $item->price }}
                    </div> --}}
                    {{-- <div class="item-amount">
                        ${{ $item->price * $item->quantity }}
                    </div> --}}
                </div>
            @endforeach
        </div>

        {{-- Delivery Info --}}
        <div class="delivery-info" style="min-height: 30px;">
            <div class="delivery-date">
                {{ optional($order->assigned_delivery_date)->format('D m/d/Y') }}
            </div>
            @if ($order->delivery_time)
                <div class="delivery-time">
                    {{ date('g:i A', strtotime($order->delivery_time)) }}
                </div>
            @endif

            {{-- Service Date --}}
            @if ($order->service_date)
                <div class="service-date">
                    {{ $order->service_date->format('D m/d/Y') }}
                </div>

                <div class="service-time">
                    {{ $order->service_date->format('g:i A') }}
                </div>
            @endif

        </div>

        {{-- Instructions --}}
        @if ($order->special_instructions)
            <div class="instructions">
                {{-- if status is will call, show "Will Call in bold with underline" --}}
                @if ($order->status == 'will_call')
                    <strong style="margin-right:12px;text-decoration:underline">WILL CALL</strong>
                @endif
                {{ $order->special_instructions }}
            </div>
        @endif
        <div class="cemetery-time">
            <span>Arrive:</span>
            <span>Leave:</span>
            <span>Driver:</span>
        </div>


    </article>

    <script>
        window.status = 'ready';
    </script>
</body>

</html>
