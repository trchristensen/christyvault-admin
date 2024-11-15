<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-size: 20px;
            line-height: 18px;
            /* font-family: Arial, sans-serif; */
            font-family: 'Monaco';
            color: #000;
            /* font-weight: bold; */
        }

        /* Keep your existing positioning styles */
        .customer-info {
            position: absolute;
            top: 210px;
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
            top: 375px;
            left: 30px;
            width: calc(100% - 60px);
        }

        .item {
            margin-bottom: 26px;
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
            top: 4px;
        }

        .item-sku {
            font-size: 20px;
            font-weight: bold;
        }

        .item-name {
            font-size: 20px;
            margin-left: 8px;
            /* font-weight: lighter; */
        }

        .item-notes {
            margin-left: 40px;
            margin-top: 15px;
            font-size: 18px;
            line-height: 1.4;
        }

        /* Keep existing delivery info styles */
        .delivery-info {
            position: absolute;
            bottom: 160px;
            left: 30px;
        }

        .delivery-date {
            font-weight: bold;
            position: absolute;
            left: 60px;
            top: 0;
            bottom: 0;
        }

        .instructions {
            position: absolute;
            bottom: 10px;
            left: 30px;
            width: calc(100% - 80px);
            text-indent: 100px;
            height: 122px;
            line-height: 30px;
        }
    </style>
</head>

<body>
    {{-- <img src="{{ public_path('images/form.jpeg') }}" style="width: 100%; object-fit: contain; object-position: top left;"> --}}

    <article>
        {{-- Customer Info Section --}}
        <div class="customer-info">
            <span class="customer-name">{{ $order->customer->name }}</span>
            <span class="customer-address">{{ $order->customer->address ?? null }}</span>
            <span class="customer-phone">{{ $order->customer->phone ?? null }}</span>
        </div>

        {{-- Order Info Section --}}
        <div class="order-info">
            <div class="invoice-date" style="height:18px;"></div>
            <div class="order-number" style="height:18px;">{{ $order->order_number }}</div>
            <div class="order-date" style="height:18px;">{{ $order->created_at->format('m/d/Y') }}</div>
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
        <div class="delivery-info">
            <span class="delivery-date">
                {{ $order->requested_delivery_date->format('m/d/Y') }}
            </span>
        </div>

        {{-- Instructions --}}
        @if ($order->special_instructions)
            <div class="instructions">
                {{ $order->special_instructions }}
            </div>
        @endif
    </article>

    <script>
        window.status = 'ready';
    </script>
</body>

</html>
