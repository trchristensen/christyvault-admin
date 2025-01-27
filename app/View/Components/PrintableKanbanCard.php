<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\KanbanCard;

class PrintableKanbanCard extends Component
{
    public string $size;

    public function __construct(
        public KanbanCard $kanbanCard,
        string $size = 'standard' // 'standard', 'large', 'small'
    ) {
        $this->size = $size;
    }

    public function getSizeClasses(): string
    {
                // Convert inches to points (1 inch = 96px)
        return match ($this->size) {
            'large' => 'w-[8.5in] h-[11in] p-8 text-2xl',      // Letter size - 8.5" x 11"
            'small' => 'w-[3in] h-[5in] p-2 text-xs',          // 3" x 5"
            default => 'w-[4in] h-[6in] p-4 text-base',        // 4" x 6"
        };
    }

    public function render()
    {
        return view('components.printable-kanban-card');
    }
}
