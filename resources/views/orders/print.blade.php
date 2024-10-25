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
            font-size: 18px;
        }


        .customer-info {
            position: absolute;
            top: 200px;
            left: 60px;
            width: 1000px;
        }

        .order-info {
            position: absolute;
            top: 230px;
            right: 90px;
            text-align: left;
        }

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

        .items {
            position: absolute;
            top: 365px;
            left: 30px;
            width: calc(100% - 60px);
        }

        .item {
            margin-bottom: 22px;
            position: relative;
        }

        .item-quantity {
            width: 40px;
            text-align: center;
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
        }

        .item-id {
            margin-left: 55px;
        }

        .delivery-info {
            position: absolute;
            bottom: 160px;
            left: 30px;
            background: #ccc;
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
    <img src="{{ public_path('images/form.jpeg') }}" style="width: 100%; object-fit: contain; object-position: top left;">


    <article>

        {{-- Customer Info Section --}}
        <div class="customer-info">
            <span class="customer-name">
                {{ $order->customer->name }}
            </span>

            <span class="customer-address">
                {{ $order->customer->address ?? null }}
            </span>
            <span class="customer-phone">
                {{ $order->customer->phone ?? null }}
            </span>
            {{-- {{ optional($order->customer->city) }}, {{ optional($order->customer->state) }} --}}
            {{-- {{ optional($order->customer->zip) }} --}}
        </div>

        {{-- Order Info Section --}}
        <div class="order-info">
            <span class="invoice-date">
            </span>
            <span class="order-number">
                {{ $order->order_number }}
            </span>
            <span class="order-date">
                {{ $order->created_at->format('m/d/Y') }}
            </span>

        </div>

        {{-- Items Section --}}
        <div class="items">
            @foreach ($order->orderProducts as $item)
                <div class="item">
                    <span class="item-quantity">
                        <strong>
                            {{ $item->quantity }}
                        </strong>
                    </span>
                    <span class="item-id">
                        <strong>{{ $item->product->sku }}</strong> ⋅ {{ $item->product->name }}
                    </span>
                    @if ($item->notes)
                        <p style="margin-left: 80px">
                            {{ $item->notes }}
                        </p>
                    @endif

                    {{-- ${{ number_format($item->price, 2) }} --}}
                    {{-- ${{ number_format($item->price * $item->quantity, 2) }} --}}
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
