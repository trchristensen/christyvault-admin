<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Print Rack Labels</title>
    <link rel="stylesheet" href="{{ asset('css/rack-label.css') }}?v={{ uniqid() }}">
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
            display: block;
            margin: 0 auto;
        }

        .labels-row {
            display: grid;
            grid-gap: 0;
            margin-bottom: 0;
            page-break-inside: avoid;
        }

        .labels-row.size-large {
            grid-template-columns: repeat(2, 216pt);
        }

        .labels-row.size-small {
            grid-template-columns: repeat(3, 144pt);
        }

        .rack-label {
            background: white;
            border: 1px solid #000;
            box-sizing: border-box;
            height: 72pt !important;
            margin: 0;
            padding: 4pt;
        }

        .rack-label.size-large {
            width: 216pt !important;
        }

        .rack-label.size-small {
            width: 144pt !important;
        }

        .rack-label .content {
            height: 100%;
            display: flex;
            /* align-items: center; */
            align-items: start;
            justify-content: space-between;
            gap: 6pt;
        }

        .rack-label .info {
            flex: 1;
            min-width: 0;
        }

        .rack-label .name {
            font-weight: bold;
            font-size: 12pt;
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
            font-size: 8pt;
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
            color: #000;
            flex-shrink: 0;
        }

        .rack-label .value {
            color: #000;
            font-weight: bold;
        }

        .rack-label .img {
            width: 60pt;
            height: 60pt;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rack-label .img img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
            object-position: center;
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
            .labels-row {
                break-inside: avoid;
            }
            .labels-row:nth-child(10) {
                page-break-after: always;
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
        <label>
            Label Size:
            <select id="labelSize">
                <option value="small" {{ $size === 'small' ? 'selected' : '' }}>Small (2" x 1")</option>
                <option value="large" {{ $size === 'large' ? 'selected' : '' }}>Large (3" x 1")</option>
                <option value="xl" {{ $size === 'xl' ? 'selected' : '' }}>Extra Large (5" x 2.5")</option>
            </select>
        </label>
        <button class="print-btn" onclick="window.print()">Print Labels</button>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>

    <div class="labels-grid">
        @foreach($kanbanCards->chunk($size === 'small' ? 3 : ($size === 'large' ? 2 : 1)) as $row)
            <div class="labels-row size-{{ $size }}">
                @foreach($row as $kanbanCard)
                    @include('kanban-cards.partials.rack-label', ['kanbanCard' => $kanbanCard, 'size' => $size])
                @endforeach
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