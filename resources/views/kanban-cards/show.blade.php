<div class="mt-4">
    <h3>Print Options:</h3>
    <div class="space-x-4">
        <a href="{{ route('kanban-cards.print', ['id' => $kanbanCard->id, 'size' => 'large']) }}"
            class="text-blue-600 hover:underline" target="_blank">
            Print Large (8.5" x 11")
        </a>
        <a href="{{ route('kanban-cards.print', ['id' => $kanbanCard->id, 'size' => 'standard']) }}"
            class="text-blue-600 hover:underline" target="_blank">
            Print Standard (5" x 7")
        </a>
        <a href="{{ route('kanban-cards.print', ['id' => $kanbanCard->id, 'size' => 'small']) }}"
            class="text-blue-600 hover:underline" target="_blank">
            Print Small (3" x 5")
        </a>
    </div>
</div>
