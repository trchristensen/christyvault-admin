<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\KanbanCard;

class PrintableKanbanCard extends Component
{
    public function __construct(
        public KanbanCard $kanbanCard
    ) {}

    public function render()
    {
        return view('components.printable-kanban-card');
    }
}
