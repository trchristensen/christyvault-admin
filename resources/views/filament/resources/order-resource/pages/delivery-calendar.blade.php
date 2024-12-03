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

        /* Reset and force visibility */
        .fc-multiMonthYear-view .fc-daygrid-day-events {
            position: static !important;
            min-height: auto !important;
            margin: 0 !important;
            padding: 2px !important;
        }

        .fc-multiMonthYear-view .fc-daygrid-event-harness {
            position: static !important;
            visibility: visible !important;
            height: auto !important;
            margin: 2px 0 !important;
        }

        .fc-multiMonthYear-view .fc-daygrid-day-frame {
            min-height: 150px !important;
            height: auto !important;
            display: flex !important;
            flex-direction: column !important;
        }

        /* Force absolute positioning elements to be visible */
        .fc-daygrid-event-harness-abs {
            position: static !important;
            visibility: visible !important;
            left: auto !important;
            right: auto !important;
            top: auto !important;
        }

        /* Ensure events stack properly */
        .fc-daygrid-day-events>* {
            position: static !important;
            transform: none !important;
        }

        /* Remove any hidden overflow */
        .fc-daygrid-day,
        .fc-daygrid-day-frame,
        .fc-daygrid-day-events {
            overflow: visible !important;
        }

        /* Reset any problematic margins/padding */
        .fc-multimonth-daygrid {
            margin: 0 !important;
        }

        .fc-multimonth-header {
            margin: 0 !important;
        }
    </style>

    @livewire(\App\Filament\Resources\OrderResource\Widgets\CalendarWidget::class)
</x-filament-panels::page>
