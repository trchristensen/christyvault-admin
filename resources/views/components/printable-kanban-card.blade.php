<div class="mx-auto bg-white kanban-card {{ $getSizeClasses() }}" id="printable-card">
    <div class="flex flex-col h-full">
        {{-- Header with QR Code --}}
        <div class="flex justify-center mb-4">
            {!! $kanbanCard->generateMainQrCode() !!}
        </div>

        {{-- Item Name & SKU --}}
        <div class="mb-4 text-center">
            @if ($kanbanCard->inventoryItem->name)
                <h2 class="font-bold leading-tight text-[1.5em]">{{ $kanbanCard->inventoryItem->name }}</h2>
            @endif
            @if ($kanbanCard->inventoryItem->sku)
                <p class="text-gray-600 text-[0.8em]">SKU: {{ $kanbanCard->inventoryItem->sku }}</p>
            @endif
        </div>

        {{-- Location Info --}}
        @if ($kanbanCard->bin_number || $kanbanCard->bin_location)
            <div class="grid grid-cols-2 gap-4 mb-4">
                @if ($kanbanCard->bin_number)
                    <div class="text-center">
                        <p class="text-gray-600 text-[0.7em] uppercase">Bin Number</p>
                        <p class="font-bold text-[1.2em]">{{ $kanbanCard->bin_number }}</p>
                    </div>
                @endif
                @if ($kanbanCard->bin_location)
                    <div class="text-center">
                        <p class="text-gray-600 text-[0.7em] uppercase">Location</p>
                        <p class="font-bold text-[1.2em]">{{ $kanbanCard->bin_location }}</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Reorder Info --}}
        @if ($kanbanCard->reorder_point)
            <div class="mt-auto text-center">
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-gray-600 text-[0.8em] uppercase">Reorder Point</p>
                    <p class="font-bold text-[1.8em]">
                        {{ $kanbanCard->reorder_point }}
                        @if ($kanbanCard->inventoryItem->unit_of_measure)
                            {{ $kanbanCard->inventoryItem->unit_of_measure }}
                        @endif
                    </p>
                </div>
            </div>
        @endif
    </div>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            #printable-card,
            #printable-card * {
                visibility: visible;
            }

            #printable-card {
                position: absolute;
                left: 0;
                top: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</div>
