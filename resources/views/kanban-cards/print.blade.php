<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Kanban Card - {{ $kanbanCard->inventoryItem->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto">
        <div class="mb-4 no-print">
            <div class="max-w-xl mb-4 ">
                <label class="block text-sm font-medium text-gray-700">Select Card Size:</label>
                <select id="cardSize" class="block w-full h-8 mt-1 border-gray-300 rounded-md">
                    <option class="p-4" value="">Choose a size...</option>
                    <option class="p-4" value="large" {{ request('size') === 'large' ? 'selected' : '' }}>
                        Large (Letter Size - 8.5" x 11")
                    </option>
                    <option class="p-4" value="standard" {{ request('size') === 'standard' ? 'selected' : '' }}>
                        Standard (5" x 7")
                    </option>
                    <option class="p-4" value="small" {{ request('size') === 'small' ? 'selected' : '' }}>
                        Small (3" x 5")
                    </option>
                </select>
            </div>

            @if (request('size'))
                <button onclick="window.print()" class="px-4 py-2 text-white bg-blue-500 rounded">
                    Print Kanban Card
                </button>
            @endif

            <button onclick="window.close()" class="px-4 py-2 ml-2 text-white bg-gray-500 rounded">
                Close
            </button>
        </div>

        @if (request('size'))
            <x-printable-kanban-card :kanbanCard="$kanbanCard" :size="request('size')" />
        @else
            <div class="p-4 text-center text-gray-600">
                Please select a card size above to continue.
            </div>
        @endif
    </div>

    <script>
        document.getElementById('cardSize').addEventListener('change', function() {
            if (this.value) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('size', this.value);
                window.location.href = currentUrl.toString();
            }
        });

        // Only auto-print if size is selected and not the initial page load
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const size = urlParams.get('size');
            if (size && document.referrer) { // Only auto-print if coming from another page
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        };
    </script>
</body>

</html>
