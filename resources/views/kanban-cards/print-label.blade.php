<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Print Rack Label - {{ $kanbanCard->inventoryItem->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/rack-label.css') }}?v={{ uniqid() }}">
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
        <button class="print-btn" onclick="window.print()">Print Label</button>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>

    @include('kanban-cards.partials.rack-label', ['size' => $size])

    <script>
        document.getElementById('labelSize').addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('size', this.value);
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html> 