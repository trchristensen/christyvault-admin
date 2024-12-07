<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban Card - {{ $kanbanCard->inventoryItem->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="container py-6 mx-auto">
        <div class="mb-4 no-print">
            <button onclick="window.print()" class="px-4 py-2 text-white bg-blue-500 rounded">
                Print Kanban Card
            </button>
            <button onclick="window.close()" class="px-4 py-2 ml-2 text-white bg-gray-500 rounded">
                Close
            </button>
        </div>

        <x-printable-kanban-card :kanbanCard="$kanbanCard" />
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>

</html>
