<x-filament-panels::page>
    <style>
        .fc-event-line {
            font-size: 0.9em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-left: 2px;
        }

        .fc-daygrid-event {
            white-space: normal !important;
            padding: 4px;
        }

        .fc-event-title {
            font-weight: bold;
            /* margin-bottom: 0.2em; */
        }
    </style>

    @livewire(\App\Filament\Resources\OrderResource\Widgets\CalendarWidget::class)
</x-filament-panels::page>
