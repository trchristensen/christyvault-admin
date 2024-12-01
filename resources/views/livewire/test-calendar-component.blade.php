<div>
    <div x-data="{
        calendar: null
    }" x-init="(() => {
        calendar = new FullCalendar.Calendar($refs.calendar, {
            initialView: 'multiMonth',
            initialDate: '{{ now()->format('Y-m-d') }}',
            duration: { months: 6 },
            {{-- validRange: {
                start: '2024-10-01', // Can't scroll earlier than 2 months ago
                end: '2025-03-31' // Can't scroll later than 4 months from now
            }, --}}
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'multiMonthYear,dayGridMonth'
            },
            displayEventTime: false,
            weekends: false,
            multiMonthMaxColumns: 1,
            showNonCurrentDates: true,
            fixedWeekCount: false,
            dayMaxEvents: false,
            dayMaxEventRows: 10,
            editable: true,
            eventDrop: function(info) {
                // Prevent locked events from being moved
                if (info.event.extendedProps.isLocked) {
                    info.revert();
                    return;
                }

                const orderId = info.event.id;
                const newDate = info.event.start.toISOString().split('T')[0];

                @this.updateOrderDate(orderId, newDate)
                    .then(() => {
                        // Success notification could go here
                    })
                    .catch(() => {
                        info.revert();
                        // Error notification could go here
                    });
            },
            events: {{ Js::from($events) }},
            eventDidMount: function(info) {
                const event = info.event;
                const el = info.el;
                const eventMainEl = el.querySelector('.fc-event-main');

                eventMainEl.style.padding = '12px';

                if (event.extendedProps.isLocked) {
                    el.style.cursor = 'not-allowed';
                    el.style.opacity = '0.8';
                }

                const titleEl = document.createElement('div');
                titleEl.style.fontSize = '14px';
                titleEl.style.fontWeight = '500';
                titleEl.style.marginBottom = '8px';
                titleEl.textContent = event.title;

                const infoContainer = document.createElement('div');
                infoContainer.style.fontSize = '12px';
                {{-- TODO: make the event title wrap --}}
                eventMainEl.style.overflow = 'hidden';
                infoContainer.style.color = 'rgba(255, 255, 255, 0.8)';

                // Basic info
                let infoHTML = `
                        <div>Requested: ${event.extendedProps.requestedDate}</div>
                        <div>Status: ${event.extendedProps.status}</div>
                    `;

                // Products info
                if (event.extendedProps.products && event.extendedProps.products.length > 0) {
                    infoHTML += '<hr class=\'my-2\' / > ';
                    event.extendedProps.products.forEach(product => {
                        const quantity = product.fill_load ? ['*', '(fill load)'] : [`${product.quantity}`, ' '];
                        infoHTML += `<div>${quantity[0]} <span class='text-xs text-opacity-10'>x</span> ${product.sku}</span> ${quantity[1]}</div>`;
                    });
                }

                infoContainer.innerHTML = infoHTML;

                eventMainEl.innerHTML = '';
                eventMainEl.appendChild(titleEl);
                eventMainEl.appendChild(infoContainer);
            },
            eventClick: function(info) {
                const orderId = info.event.id;
                @this.editOrder(orderId);
            }
        });

        calendar.render();
    })()">
        <div x-ref="calendar"></div>
    </div>

    {{-- Add the Filament modal --}}
    <x-filament::modal id="edit-order" width="7xl">
        <x-slot name="header">
            Edit Order {{ $editing?->order_number }}
        </x-slot>

        @if ($editing)
            <form wire:submit="saveOrder">
                {{ $this->form }}

                <div class="mt-4 flex justify-end gap-x-4">
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'edit-order' })">
                        Cancel
                    </x-filament::button>

                    <x-filament::button type="submit">
                        Save
                    </x-filament::button>
                </div>
            </form>
        @endif
    </x-filament::modal>
</div>
