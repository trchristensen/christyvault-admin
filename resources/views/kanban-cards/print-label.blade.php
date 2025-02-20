<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Rack Label - {{ $kanbanCard->inventoryItem->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/rack-label.css') }}">
</head>
<body>
    <div class="controls">
        <label>Label Size:</label>
        <select id="labelSize">
            <option value="xl" {{ request('size') === 'xl' ? 'selected' : '' }}>2.5" x 5"</option>
            <option value="large" {{ request('size') === 'large' ? 'selected' : '' }}>1" x 3"</option>
            <option value="small" {{ request('size') === 'small' ? 'selected' : '' }}>1" x 2"</option>
        </select>

        <button class="print-btn" onclick="window.print()">Print Label</button>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>

    @include('kanban-cards.partials.rack-label', [
        'kanbanCard' => $kanbanCard,
        'size' => request('size', 'large')
    ])

    <script>
        document.getElementById('labelSize').addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('size', this.value);
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html> 