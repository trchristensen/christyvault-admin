<x-filament-panels::page>
    @push('scripts')
        <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    @endpush

    @push('styles')
        <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.css' rel='stylesheet' />
        <style>
            /* Basic event styling */
            .fc-event {
                cursor: pointer;
                padding: 4px;
                margin-bottom: 2px;
            }

            /* Remove all height restrictions */
            .fc .fc-daygrid-day {
                height: auto !important;
            }

            .fc .fc-daygrid-day-frame {
                min-height: auto !important;
            }

            .fc .fc-daygrid-day-events {
                margin: 0 !important;
                padding: 2px !important;
            }

            /* Hide the more link */
            .fc-daygrid-more-link {
                display: none !important;
            }

            /* Ensure events stack properly */
            .fc-daygrid-event-harness {
                margin-bottom: 2px !important;
            }

            /* Remove default positioning that can cause issues */
            .fc-daygrid-day-events {
                position: relative !important;
            }

            .fc .fc-daygrid-body-balanced .fc-daygrid-day-events {
                position: relative !important;
            }
        </style>
    @endpush

    <livewire:test-calendar-component />
</x-filament-panels::page>
