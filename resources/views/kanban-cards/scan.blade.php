<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban Card Scan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-lg mx-auto my-8 px-4">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-4">Kanban Card Scan</h1>
            
            @if(isset($error))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ $error }}
                </div>
            @endif

            @if(isset($success))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ $success }}
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label class="font-medium">Item</label>
                    <p>{{ $kanbanCard->inventoryItem->name }}</p>
                </div>

                <div>
                    <label class="font-medium">Location</label>
                    <p>{{ $kanbanCard->bin_location }}</p>
                </div>

                <div>
                    <label class="font-medium">Bin Number</label>
                    <p>{{ $kanbanCard->bin_number }}</p>
                </div>

                <div>
                    <label class="font-medium">Status</label>
                    <p>{{ ucfirst($kanbanCard->status) }}</p>
                </div>

                @if($kanbanCard->last_scanned_at)
                    <div>
                        <label class="font-medium">Last Scanned</label>
                        <p>{{ $kanbanCard->last_scanned_at->diffForHumans() }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html> 