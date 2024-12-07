<div class="mx-auto bg-white kanban-card {{ $getSizeClasses() }}" id="printable-card">
    <div class="flex flex-col h-full p-4">
        {{-- Header with QR Code --}}
        <div class="flex justify-center mb-2">
            <div
                class="{{ match ($size) {
                    'large' => 'w-[4.5in] h-[4.5in]', // Reduced from 6in
                    'small' => 'w-[2in] h-[2in]', // Reduced from 2.5in
                    default => 'w-[3in] h-[3in]', // Reduced from 4in
                } }}">
                <div class="w-full h-full">
                    <svg viewBox="0 0 {{ match ($size) {
                        'large' => '1500 1500',
                        'small' => '800 800',
                        default => '1200 1200',
                    } }}"
                        class="w-full h-full">
                        {!! $kanbanCard->generateQrCode($size) !!}
                    </svg>
                </div>
            </div>
        </div>

        {{-- Item Name & SKU --}}
        <div class="mb-2 text-center">
            @if ($kanbanCard->inventoryItem->name)
                <h2
                    class="{{ match ($size) {
                        'large' => 'text-4xl',
                        'small' => 'text-xl',
                        default => 'text-2xl',
                    } }} font-bold leading-tight">
                    {{ $kanbanCard->inventoryItem->name }}</h2>
            @endif
            @if ($kanbanCard->inventoryItem->sku)
                <p
                    class="{{ match ($size) {
                        'large' => 'text-xl',
                        'small' => 'text-sm',
                        default => 'text-base',
                    } }} text-gray-600">
                    Item #: {{ $kanbanCard->inventoryItem->sku }}</p>
            @endif
        </div>

        {{-- Location Info --}}
        <div class="grid {{ $kanbanCard->bin_number ? 'grid-cols-2' : 'grid-cols-1' }} gap-4 mb-4">
            <div class="text-center">
                <p class="text-gray-600 text-[0.7em] uppercase">Department</p>
                <p class="font-bold text-[1.2em]">{{ $kanbanCard->department }}</p>
            </div>
            @if ($kanbanCard->bin_number)
                <div class="text-center">
                    <p class="text-gray-600 text-[0.7em] uppercase">Bin Number</p>
                    <p class="font-bold text-[1.2em]">{{ $kanbanCard->bin_number }}</p>
                </div>
            @endif
        </div>

        @if ($kanbanCard->description)
            <div class="mb-4 text-center">
                <p class="text-gray-600 text-[0.7em] uppercase">Description</p>
                <p class="text-[0.9em]">{{ $kanbanCard->description }}</p>
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
