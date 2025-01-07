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
            <div class="max-w-xl mb-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Card Type:</label>
                        <select id="cardType" class="block w-full h-8 mt-1 border-gray-300 rounded-md">
                            <option value="storage" {{ request('type') === 'storage' ? 'selected' : '' }}>
                                Storage Card (Bin)
                            </option>
                            <option value="movement" {{ request('type') === 'movement' ? 'selected' : '' }}>
                                Movement Card (Reorder)
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Card Size:</label>
                        <select id="cardSize" class="block w-full h-8 mt-1 border-gray-300 rounded-md">
                            <option value="standard"
                                {{ !request('size') || request('size') === 'standard' ? 'selected' : '' }}>
                                Standard (5" x 7")
                            </option>
                            <option value="large" {{ request('size') === 'large' ? 'selected' : '' }}>
                                Large (8.5" x 11")
                            </option>
                            <option value="small" {{ request('size') === 'small' ? 'selected' : '' }}>
                                Small (3" x 5")
                            </option>
                        </select>
                    </div>
                </div>
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

        @if (!request('size'))
            <x-printable-kanban-card :kanbanCard="$kanbanCard" size="standard" />
        @else
            <x-printable-kanban-card :kanbanCard="$kanbanCard" :size="request('size')" />
        @endif
    </div>

    <script>
        ['cardSize', 'cardType'].forEach(selectId => {
            document.getElementById(selectId).addEventListener('change', function() {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set(selectId === 'cardSize' ? 'size' : 'type', this.value);
                window.location.href = currentUrl.toString();
            });
        });
    </script>
</body>

</html>
