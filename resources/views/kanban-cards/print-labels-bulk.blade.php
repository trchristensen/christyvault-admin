<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Rack Labels</title>
    <style>
        @page {
            margin: 0.25in;
            size: letter;
        }

        body {
            margin: 0;
            padding: 8pt;
            background: #f3f4f6;
        }

        .controls {
            margin-bottom: 16pt;
        }

        button {
            padding: 6pt 12pt;
            border: none;
            border-radius: 4pt;
            cursor: pointer;
        }

        .print-btn {
            background: #3b82f6;
            color: white;
        }

        .close-btn {
            background: #6b7280;
            color: white;
            margin-left: 8pt;
        }

        .labels-grid {
            display: grid;
            grid-gap: 0;
            margin: 0 auto;
        }

        /* For 3" x 1" labels */
        .labels-grid.size-large {
            grid-template-columns: repeat(2, 216pt); /* Two 3-inch columns */
            grid-template-rows: repeat(5, 72pt);     /* Five 1-inch rows */
        }

        /* For 2" x 1" labels */
        .labels-grid.size-small {
            grid-template-columns: repeat(3, 144pt); /* Three 2-inch columns */
            grid-template-rows: repeat(5, 72pt);     /* Five 1-inch rows */
        }

        /* Label styles - exactly matching single label */
        .rack-label {
            background: white;
            border: 1px solid #000;
            box-sizing: border-box;
            height: 72pt !important; /* 1 inch */
            margin: 0;
            padding: 4pt;
        }

        .rack-label.size-large {
            width: 216pt !important; /* 3 inches */
        }

        .rack-label.size-small {
            width: 144pt !important; /* 2 inches */
        }

        .rack-label .content {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6pt;
        }

        .rack-label .info {
            flex: 1;
            min-width: 0;
        }

        .rack-label .name {
            font-weight: bold;
            font-size: 11pt;
            line-height: 1;
            margin-bottom: 1pt;
        }

        .rack-label .sku {
            font-size: 8pt;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2pt;
            font-weight: bold;
        }

        .rack-label .details, .rack-label .description {
            font-size: 6.5pt;
            line-height: 1.2;
        }

        .rack-label .detail-row {
            display: flex;
            align-items: center;
            gap: 2pt;
            margin-bottom: 1pt;
        }

        .rack-label .label {
            font-weight: bold;
            color: #666;
            flex-shrink: 0;
        }

        .rack-label .value {
            color: #000;
            font-weight: bold;
        }

        .rack-label .img {
            width: 60pt;  /* ~0.7 inch */
            height: 60pt;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            object-fit: contain;
        }

        .rack-label .img img {
            width: 100%;
            height: 100%;
            display: block;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            .controls {
                display: none;
            }
        }

        select {
            display: block;
            width: 200pt;
            height: 24pt;
            margin-top: 4pt;
            margin-bottom: 8pt;
        }
    </style>
</head>
<body>
    <div class="controls">
        <label>Label Size:</label>
        <select id="labelSize">
            <option value="large" {{ request('size') === 'large' ? 'selected' : '' }}>1" x 3"</option>
            <option value="small" {{ request('size') === 'small' ? 'selected' : '' }}>1" x 2"</option>
        </select>

        <button class="print-btn" onclick="window.print()">Print Labels</button>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>

    <div class="labels-grid size-{{ request('size', 'large') }}">
        @foreach($kanbanCards as $kanbanCard)
            <div class="rack-label size-{{ request('size', 'large') }}">
                <div class="content">
                    <div class="info">
                        <div class="name">{{ $kanbanCard->inventoryItem->name }}</div>
                        <div class="sku">{{ $kanbanCard->inventoryItem->sku }}</div>
                        <div class="description">
                            <div class="detail-row">
                                {{ $kanbanCard->inventoryItem->description }}
                            </div>
                        </div>
                        <div class="details">
                            <div class="detail-row">
                                <span class="label">Dept:</span>
                                <span class="value">{{ $kanbanCard->inventoryItem->department }}</span>
                            </div>
                            @if($kanbanCard->inventoryItem->storage_location)
                                <div class="detail-row">
                                    <span class="label">Location:</span>
                                    <span class="value">{{ $kanbanCard->inventoryItem->storage_location }}</span>
                                </div>
                            @endif
                            <div class="detail-row">
                                <span class="label">Reorder Point:</span>
                                <span class="value">{{ $kanbanCard->reorder_point }} {{ $kanbanCard->unit_of_measure }}</span>
                            </div>
                        </div>
                    </div>
                    
                    @if (request('size', 'large') === 'large')
                        <div class="img">
                            @if ($kanbanCard->inventoryItem->image)
                                <img src="{{ Storage::url($kanbanCard->inventoryItem->image) }}" 
                                     alt="{{ $kanbanCard->inventoryItem->name }}"
                                     class="object-contain w-full h-full" />
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <script>
        document.getElementById('labelSize').addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('size', this.value);
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html> 