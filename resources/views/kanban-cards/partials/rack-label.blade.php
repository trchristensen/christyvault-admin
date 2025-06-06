<div class="rack-label size-{{ $size ?? request('size', 'xl') }}">
    <div class="content">
        <div class="info">
            <div class="top-info">
                <div class="name">{{ $kanbanCard->inventoryItem->name }}</div>
                <div class="sku">{{ $kanbanCard->inventoryItem->sku }}</div>
                <div class="description">
                    <div class="detail-row">
                        {{ $kanbanCard->inventoryItem->description }}
                    </div>
                </div>
            </div>
            
            {{-- <div class="bottom-info">
                @if($kanbanCard->inventoryItem->storage_location)
                    <div class="detail-row">
                        <span class="label">Location:</span>
                        <span class="value">{{ $kanbanCard->inventoryItem->storage_location }}</span>
                    </div>
                @endif
                @if($kanbanCard->inventoryItem->bin_number)
                    <div class="detail-row">
                        <span class="label">Bin #:</span>
                        <span class="value">{{ $kanbanCard->inventoryItem->bin_number }}</span>
                    </div>
                @endif
                @if($kanbanCard->reorder_point)
                    <div class="detail-row reorder-point">
                        <span class="label">Reorder @:</span>
                        <span class="value">
                            {{ floor($kanbanCard->reorder_point) == $kanbanCard->reorder_point 
                                ? floor($kanbanCard->reorder_point) 
                                : $kanbanCard->reorder_point 
                            }} 
                            {{ Str::plural($kanbanCard->unit_of_measure, $kanbanCard->reorder_point) }}
                        </span>
                    </div>
                @endif
            </div> --}}
        </div>

        @if ($size !== 'small')
            @if ($size === 'xl')
                <div class="media-column">
                    @if ($kanbanCard->inventoryItem->image)
                        <div class="product-image">
                            <img src="{{ $kanbanCard->inventoryItem->image_url }}" 
                                 alt="{{ $kanbanCard->inventoryItem->name }}" />
                        </div>
                    @endif
                    
                    <div class="qr-code">
                        {!! $kanbanCard->generateQrCode('small') !!}
                    </div>
                </div>
            @else
                <div class="img">
                    @if ($kanbanCard->inventoryItem->image)
                        <img src="{{ $kanbanCard->inventoryItem->image_url }}" 
                             alt="{{ $kanbanCard->inventoryItem->name }}" />
                    @endif
                </div>
            @endif
        @endif
    </div>
</div> 