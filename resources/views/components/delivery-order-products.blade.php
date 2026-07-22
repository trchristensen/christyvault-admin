@props([
    'order',
])

@if (! $order->deliveryProductLinesAreVisibleTo(auth()->user()))
    <div class="delivery-order-products-locked" role="status">
        <x-heroicon-o-printer />

        <div>
            <div class="delivery-order-products-locked-title">Waiting for delivery tag</div>
            {{-- <div class="delivery-order-products-locked-message">
                Product lines are hidden until the delivery tag is printed. Do not pull or load materials until you receive the printed tag.
            </div> --}}
        </div>
    </div>
@elseif ($order->orderProducts->isNotEmpty())
    <div class="delivery-order-products">
        <table>
            @foreach ($order->orderProducts as $orderProduct)
                @php
                    $productSku = $orderProduct->is_custom_product
                        ? 'CUSTOM'
                        : ($orderProduct->product?->sku ?? 'Unknown');
                    $productName = $orderProduct->is_custom_product
                        ? ($orderProduct->custom_description ?? 'Custom Product')
                        : ($orderProduct->product?->name ?? 'Unknown product');
                    $hasOperationalDetails = filled($orderProduct->location)
                        || filled($orderProduct->notes)
                        || ($orderProduct->quantity_delivered !== null && $orderProduct->quantity_delivered !== '');
                @endphp

                <tr>
                    <td class="delivery-order-product-quantity">
                        @if ($orderProduct->fill_load)
                            <strong>*</strong>
                        @else
                            {{ $orderProduct->quantity }}
                        @endif
                    </td>
                    <td class="delivery-order-product-details">
                        <div class="delivery-order-product-content">
                            <div class="delivery-order-product-primary">
                                <span class="delivery-order-product-sku">{{ $productSku }}</span>
                                <span class="delivery-order-product-name">{{ $productName }}</span>
                            </div>

                            @if ($orderProduct->fill_load)
                                <span class="delivery-order-product-fill">└ FILL OUT LOAD</span>
                            @endif

                            @if ($hasOperationalDetails)
                                <div class="delivery-order-product-meta">
                                    @if ($orderProduct->location)
                                        <div class="delivery-order-product-meta-item">
                                            <span class="delivery-order-product-meta-label">Location</span>
                                            <span class="delivery-order-product-meta-value">{{ $orderProduct->location }}</span>
                                        </div>
                                    @endif

                                    @if ($orderProduct->notes)
                                        <div class="delivery-order-product-meta-item">
                                            <span class="delivery-order-product-meta-label">Notes</span>
                                            <span class="delivery-order-product-meta-value">{{ $orderProduct->notes }}</span>
                                        </div>
                                    @endif

                                    @if ($orderProduct->quantity_delivered !== null && $orderProduct->quantity_delivered !== '')
                                        <div class="delivery-order-product-meta-item">
                                            <span class="delivery-order-product-meta-label">Shipped</span>
                                            <span class="delivery-order-product-meta-value">{{ $orderProduct->quantity_delivered }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
