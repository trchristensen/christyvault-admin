<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Rack Label - {{ $kanbanCard->inventoryItem->name }}</title>
    <style>
        body {
            margin: 0;
            padding: 8pt;
            background: #f3f4f6;
        }

        .controls {
            margin-bottom: 16pt;
        }

        select {
            display: block;
            width: 200pt;
            height: 24pt;
            margin-top: 4pt;
            margin-bottom: 8pt;
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

        /* Label styles */
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

        .rack-label .details {
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

        .rack-label .qr-code {
            width: 60pt;  /* ~0.7 inch */
            height: 60pt;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rack-label .qr-code svg {
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
    </style>
</head>
<body>
    <div class="controls">
        <label>Label Size:</label>
        <select id="labelSize">
            <option value="large" {{ request('size') === 'large' ? 'selected' : '' }}>1" x 3"</option>
            <option value="small" {{ request('size') === 'small' ? 'selected' : '' }}>1" x 2"</option>
        </select>

        <button class="print-btn" onclick="window.print()">Print Label</button>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>

    <div class="rack-label size-{{ request('size', 'large') }}" id="printable-label">
        <div class="content">
            <div class="info">
                <div class="name">{{ $kanbanCard->inventoryItem->name }}</div>
                <div class="sku">{{ $kanbanCard->inventoryItem->sku }}</div>
                <div class="description">
                    {{ $kanbanCard->inventoryItem->description }}
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
                <div class="qr-code">
                    {!! $kanbanCard->generateQrCode('small') !!}
                </div>
            @endif
        </div>
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