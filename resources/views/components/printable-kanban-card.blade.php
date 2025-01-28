<div class="border border-gray-200 border-dotted mx-auto bg-white kanban-card {{ $getSizeClasses() }}" id="printable-card">
    <div class="flex flex-col h-full p-4">
        @if (request('type') === 'movement')
            {{-- Movement Card (Simplified for reordering) --}}
            <div class="mb-4 text-center">
                <div class="inline-block px-3 py-1 text-xs font-bold text-red-700 bg-red-100 rounded-full">
                    REORDER CARD
                </div>
            </div>
        @endif

        {{-- Item Name & SKU at the top --}}
        <div class="mb-4 text-center">
            @if ($kanbanCard->inventoryItem->name)
                <h2 class="{{ match ($size) {
                    'large' => 'text-4xl',
                    'small' => 'text-xl',
                    default => 'text-2xl',
                } }} font-bold leading-tight">
                    {{ $kanbanCard->inventoryItem->name }}</h2>
            @endif
            @if ($kanbanCard->inventoryItem->sku)
                <p class="{{ match ($size) {
                    'large' => 'text-xl',
                    'small' => 'text-sm',
                    default => 'text-base',
                } }} text-gray-600">
                    Item #: {{ $kanbanCard->inventoryItem->sku }}</p>
            @endif
        </div>

        {{-- QR Code and Image side by side --}}
        <div class="flex {{ $kanbanCard->inventoryItem->image ? 'justify-between' : 'justify-center' }} mb-4 space-x-4">
            {{-- QR Code --}}
            <div class="{{ match ($size) {
                'large' => 'w-[2.5in] h-[2.5in]',
                'small' => 'w-[1.25in] h-[1.25in]',
                default => 'w-[1.75in] h-[1.75in]',
            } }}">
                <svg viewBox="0 0 {{ match ($size) {
                    'large' => '1500 1500',
                    'small' => '800 800',
                    default => '1200 1200',
                } }}" class="w-full h-full">
                    {!! $kanbanCard->generateQrCode($size) !!}
                </svg>
            </div>

            {{-- Product Image (if available) --}}
            @if ($kanbanCard->inventoryItem->image)
                <div class="{{ match ($size) {
                    'large' => 'w-[2.5in] h-[2.5in]',
                    'small' => 'w-[1.25in] h-[1.25in]',
                    default => 'w-[1.75in] h-[1.75in]',
                } }} rounded flex items-center justify-center">
                    <img src="{{ Storage::url($kanbanCard->inventoryItem->image) }}" 
                         alt="{{ $kanbanCard->inventoryItem->name }}"
                         class="object-contain w-full h-full">
                </div>
            @endif
        </div>

        {{-- Location Info (Always show, but styled differently for movement cards) --}}
        <div
            class="grid {{ $kanbanCard->bin_number ? 'grid-cols-2' : 'grid-cols-1' }} gap-4 mb-4
            {{ request('type') === 'movement' ? 'bg-gray-50 p-3 rounded-lg' : '' }}">
            <div class="text-center">
                <p class="text-gray-600 text-[0.7em] uppercase">Department</p>
                <p class="font-bold text-[1.2em]">{{ $kanbanCard->inventoryItem->department }}</p>
            </div>
            @if ($kanbanCard->inventoryItem->storage_location)
                <div class="text-center">
                    <p class="text-gray-600 text-[0.7em] uppercase">Storage Location</p>
                    <p class="font-bold text-[1.2em]">{{ $kanbanCard->inventoryItem->storage_location }}</p>
                </div>
            @endif
            @if ($kanbanCard->bin_number)
                <div class="text-center">
                    <p class="text-gray-600 text-[0.7em] uppercase">Bin Number</p>
                    <p class="font-bold text-[1.2em]">{{ $kanbanCard->bin_number }}</p>
                </div>
            @endif
        </div>

        @if ($kanbanCard->description && request('type') !== 'movement')
            <div class="mb-4 text-center">
                <p class="text-gray-600 text-[0.7em] uppercase">Description</p>
                <p class="text-[0.9em]">{{ $kanbanCard->description }}</p>
            </div>
        @endif

        {{-- Reorder Info (Emphasized on Movement Card) --}}
        @if ($kanbanCard->reorder_point)
            <div class="mt-auto text-center">
                <div
                    class="pt-4 border-t border-gray-200 {{ request('type') === 'movement' ? 'bg-red-50 p-4 rounded-lg' : '' }}">
                    <p class="text-gray-600 text-[0.8em] uppercase">Reorder Point</p>
                    <p
                        class="font-bold {{ request('type') === 'movement' ? 'text-[2.2em] text-red-600' : 'text-[1.8em]' }}">
                        {{ number_format($kanbanCard->reorder_point, strpos($kanbanCard->reorder_point, '.00') !== false ? 0 : 2) }}
                        {{ Str::plural($kanbanCard->unit_of_measure, $kanbanCard->reorder_point) }}
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
