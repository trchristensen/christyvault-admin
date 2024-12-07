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
        return match ($this->size) {
            'large' => 'w-[215.9mm] h-[279.4mm] p-8 text-2xl',     // Letter size - large text
            'small' => 'w-[76.2mm] h-[127mm] p-2 text-xs',         // 3" x 5" - small text
            default => 'w-[127mm] h-[177.8mm] p-4 text-base',      // 5" x 7" - medium text
        };
    }

    public function render()
    {
        return view('components.printable-kanban-card');
    }
}
