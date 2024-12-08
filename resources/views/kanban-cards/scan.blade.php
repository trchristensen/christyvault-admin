<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban Card Scan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="max-w-lg px-4 mx-auto my-8">
        <div class="p-6 bg-white rounded-lg shadow-lg">
            <h1 class="mb-4 text-2xl font-bold">Kanban Card Scan</h1>

            @if (isset($error))
                <div class="px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded">
                    {{ $error }}
                </div>
            @endif

            @if (isset($success))
                <div class="px-4 py-3 mb-4 text-green-700 bg-green-100 border border-green-400 rounded">
                    {{ $success }}
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label class="font-medium">Item</label>
                    <p>{{ $kanbanCard->inventoryItem->name }}</p>
                </div>

                <div>
                    <label class="font-medium">Department</label>
                    <p>{{ $kanbanCard->department }}</p>
                </div>

                @if ($kanbanCard->bin_number)
                    <div>
                        <label class="font-medium">Bin Number</label>
                        <p>{{ $kanbanCard->bin_number }}</p>
                    </div>
                @endif

                @if (isset($showQuantityForm))
                    <form method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block font-medium text-gray-700">
                                Remaining Quantity (Optional)
                            </label>
                            <input type="number" name="remaining_quantity" step="0.01"
                                class="block w-full px-3 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter remaining quantity">
                            <p class="mt-1 text-sm text-gray-500">
                                Leave blank if you don't want to report the quantity
                            </p>
                        </div>
                        <button type="submit"
                            class="w-full px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Submit Scan
                        </button>
                    </form>
                @else
                    <div>
                        <label class="font-medium">Status</label>
                        <p>{{ ucfirst($kanbanCard->status) }}</p>
                    </div>

                    @if ($kanbanCard->last_scanned_at)
                        <div>
                            <label class="font-medium">Last Scanned</label>
                            <p>{{ $kanbanCard->last_scanned_at->diffForHumans() }}</p>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</body>

</html>
