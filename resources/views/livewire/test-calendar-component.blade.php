<div>
    {{-- Styles section --}}
    <div>
        <style>
            /* Hide default event content */
            .fc-event-main>div:not(.custom-event-content) {
                display: none !important;
            }

            .trip-event {
                border-left: 4px solid #1E40AF !important;
            }

            .nested-order {
                transition: all 0.2s ease;
            }

            .nested-order:hover {
                background: rgba(255, 255, 255, 0.2) !important;
            }
        </style>
    </div>

    {{-- Calendar section --}}
    <div wire:ignore>
        <div x-data="{ calendar: null }" x-init="(() => {
            if (!calendar) {
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
                    dateClick: function(info) {
                        // Call Livewire method to create new order
                        @this.createOrder(info.dateStr);
                    },
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

                        // Clear the content first
                        eventMainEl.innerHTML = '';

                        // Create wrapper with custom class
                        const wrapper = document.createElement('div');
                        wrapper.className = 'custom-event-content';
                        wrapper.style.padding = '6px';
                        wrapper.style.overflow = 'hidden';

                        // Create title element
                        const titleEl = document.createElement('div');
                        titleEl.style.fontSize = '14px';
                        titleEl.style.fontWeight = '500';
                        titleEl.style.marginBottom = '8px';
                        titleEl.innerHTML = event.title;
                        wrapper.appendChild(titleEl);

                        // Create info container
                        const infoContainer = document.createElement('div');
                        infoContainer.style.fontSize = '12px';
                        infoContainer.style.color = 'rgba(255, 255, 255, 0.8)';

                        if (event.extendedProps.type === 'trip') {
                            const tripOrdersDiv = document.createElement('div');
                            tripOrdersDiv.className = 'trip-orders';

                            if (event.extendedProps.orders) {
                                event.extendedProps.orders.forEach(order => {
                                    const orderDiv = document.createElement('div');
                                    orderDiv.className = 'nested-order';
                                    orderDiv.style.padding = '8px';
                                    orderDiv.style.background = 'rgba(255,255,255,0.1)';
                                    orderDiv.style.marginTop = '8px';
                                    orderDiv.style.borderRadius = '4px';

                                    const orderTitle = document.createElement('div');
                                    orderTitle.style.fontWeight = '500';
                                    orderTitle.textContent = order.title;
                                    orderDiv.appendChild(orderTitle);

                                    const orderStatus = document.createElement('div');
                                    orderStatus.textContent = 'Status: ' + order.status;
                                    orderDiv.appendChild(orderStatus);

                                    if (order.products && order.products.length > 0) {
                                        order.products.forEach(product => {
                                            const productDiv = document.createElement('div');
                                            productDiv.style.fontSize = '11px';
                                            const quantity = product.fill_load ? '*' : product.quantity;
                                            const fillLoadText = product.fill_load ? ' (fill load)' : '';
                                            productDiv.textContent = `${quantity} × ${product.sku}${fillLoadText}`;
                                            orderDiv.appendChild(productDiv);
                                        });
                                    }

                                    tripOrdersDiv.appendChild(orderDiv);
                                });
                            }
                            infoContainer.appendChild(tripOrdersDiv);
                        } else {
                            const requestedDate = document.createElement('div');
                            requestedDate.textContent = 'Requested: ' + event.extendedProps.requestedDate;
                            infoContainer.appendChild(requestedDate);

                            const status = document.createElement('div');
                            status.textContent = 'Status: ' + event.extendedProps.status;
                            infoContainer.appendChild(status);

                            if (event.extendedProps.products && event.extendedProps.products.length > 0) {
                                const hr = document.createElement('hr');
                                hr.style.margin = '8px 0';
                                hr.style.borderColor = 'rgba(255,255,255,0.2)';
                                infoContainer.appendChild(hr);

                                event.extendedProps.products.forEach(product => {
                                    const productDiv = document.createElement('div');
                                    const quantity = product.fill_load ? '*' : product.quantity;
                                    const fillLoadText = product.fill_load ? ' (fill load)' : '';
                                    productDiv.textContent = `${quantity} × ${product.sku}${fillLoadText}`;
                                    infoContainer.appendChild(productDiv);
                                });
                            }
                        }

                        wrapper.appendChild(infoContainer);
                        eventMainEl.appendChild(wrapper);
                    },
                    eventClick: function(info) {
                        const orderId = info.event.id;
                        @this.editOrder(orderId);
                    }
                });
                calendar.render();

                // Re-render calendar when Livewire updates
                Livewire.on('calendar-updated', () => {
                    calendar.refetchEvents();
                });
            }
        })()" wire:ignore>
            <div x-ref="calendar"></div>
        </div>
    </div>

    {{-- This is required for Filament actions to work --}}
    <x-filament-actions::modals />
</div>
