<div class="max-w-sm p-6 mx-auto bg-white kanban-card" id="printable-card">
    {{-- QR Code --}}
    <div class="flex justify-center mb-4">
        {!! $kanbanCard->generateQrCode() !!}
    </div>

    {{-- Item Details --}}
    <div class="mb-4 text-center">
        <h2 class="text-xl font-bold">{{ $kanbanCard->inventoryItem->name }}</h2>
        <p class="text-gray-600">SKU: {{ $kanbanCard->inventoryItem->sku }}</p>
    </div>

    {{-- Storage Info --}}
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="text-center">
            <p class="text-sm text-gray-500">Bin Number</p>
            <p class="font-semibold">{{ $kanbanCard->bin_number }}</p>
        </div>
        <div class="text-center">
            <p class="text-sm text-gray-500">Location</p>
            <p class="font-semibold">{{ $kanbanCard->bin_location }}</p>
        </div>
    </div>

    {{-- Reorder Info --}}
    <div class="mt-4 text-sm text-gray-600">
        <p>Reorder Point: {{ $kanbanCard->reorder_point }}</p>
        <p>Unit: {{ $kanbanCard->inventoryItem->unit_of_measure }}</p>
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
                width: 100mm;
                height: 60mm;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</div>
