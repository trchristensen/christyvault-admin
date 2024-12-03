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
            background-color: #1e293b;
        }

        .fc-event-title {
            font-weight: bold;
        }

        /* New styles */
        .fc-event-main {
            /* padding: 8px !important; */
        }

        .trip-title {
            font-weight: 500;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .order-container {
            background: #1e293b;
            padding: 6px;
            margin-top: 6px;
            border-radius: 4px;
        }

        .order-title {
            font-weight: 500;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .order-status {
            font-size: 0.9em;
            margin-bottom: 4px;
        }

        .product-item {
            font-size: 0.8em;
            background: rgba(255, 255, 255, 0.1);
            padding: 4px;
            margin-top: 4px;
            border-radius: 4px;
        }

        .trip-event {
            /* border-left: 4px solid #1E40AF !important; */
        }
    </style>

    @livewire(\App\Filament\Resources\OrderResource\Widgets\CalendarWidget::class)
</x-filament-panels::page>
