<x-filament-panels::page>
    @vite(['resources/css/app.css'])
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    

    <div class="flex gap-3">
        <div class="w-1/6 mt-[4.45rem]"
            id="unassigned-orders" 
        >
            <h3 class="font-bold mb-2">Unassigned Orders</h3>
            @foreach($unassignedOrders as $order)
                <div
                    class="fc-draggable-order"
                    data-order-id="{{ $order->id }}"
                    draggable="true"
                    wire:click="openOrderModal({{ $order->id }})"
                    style="margin-bottom: 4px; cursor: grab;"
                >
                    <div class="order-container status-{{ strtolower($order->status) }}">
                        <div class="order-title">{{ $order->location->name ?? $order->order_number }}</div>
                        @if($order->location)
                            <div class="order-address">{{ $order->location->city }}, {{ $order->location->state }}</div>
                        @endif
                        <div class="order-status">
                            <span>{{ \App\Enums\OrderStatus::tryFrom($order->status)?->label() ?? ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            @if($order->order_number)
                                <span class="order-number">#{{ ltrim($order->order_number, 'ORD-') }}</span>
                            @endif
                        </div>
                        <div class="order-status mt-2">Order Date: {{ $order->order_date->format('m/d') }}</div>
                        {{-- requested date --}}
                        <div class="order-status">Requested by: {{ $order->order_date->format('m/d') }}</div>
                        
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex-1" wire:ignore>
            <div id="calendar"></div>
        </div>
    </div>

    <!-- Include the separate OrderModal component -->
    @livewire('order-modal')

    @push('scripts')
    <script>
    // Global variable to track the event being dragged from calendar
    let draggedCalendarEvent = null;
    let calendar; // Store calendar reference globally

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Make existing sidebar orders draggable individually
        initializeSidebarDragging();

        // 2. Initialize FullCalendar
        var calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridWeek',
            weekends: false,
            editable: true,
            droppable: true,
            events: '/calendar-events',
            eventDrop: handleEventReceive,
            eventReceive: handleEventReceive,
            eventDragStart: handleEventDragStart,
            eventDragStop: handleEventDragStop,
            dateClick: handleDateClick,
            eventClick: handleEventClick,
            eventOrder: ['sort_order'], // Use explicit sort order from backend
            timeZone: 'local',
            allDaySlot: true,
            allDayMaintainDuration: true,
            eventDidMount: function(info) {
                const event = info.event;
                const el = info.el;
                const eventMainEl = el.querySelector('.fc-event-main');
                
                // Build the order content
                const props = event.extendedProps;
                const content = document.createElement('div');
                content.innerHTML = `
                    <div class="order-container status-${(props.status_raw || props.status || '').toLowerCase()}">
                        <div class="order-title">${event.title}</div>
                        ${props.location_line2 ? `<div class="order-address">${props.location_line2}</div>` : ''}
                        <div class="order-status">
                            <span>${props.status || ''}</span>
                            ${props.order_number ? `<span class="order-number">#${props.order_number.replace(/^ORD-/, '')}</span>` : ''}
                        </div>
                    </div>
                `;
                eventMainEl.replaceChildren(content);
                
                // Add group start label if this is the start of a group
                if (event.extendedProps?.is_group_start) {
                    const groupStartLabel = document.createElement('div');
                    groupStartLabel.style.margin = '12px 0 6px 0';
                    groupStartLabel.style.padding = '0';
                    groupStartLabel.style.fontSize = '1em';
                    groupStartLabel.style.fontWeight = 'bold';
                    groupStartLabel.style.color = '#111';
                    groupStartLabel.style.background = 'none';
                    groupStartLabel.style.border = 'none';
                    groupStartLabel.style.letterSpacing = '0.5px';
                    groupStartLabel.style.textAlign = 'left';
                    
                    const plantLocationMap = {
                        'colma_main': 'Colma',
                        'colma_locals': 'Locals',
                        'tulare_plant': 'Tulare',
                    };
                    
                    const plantLocation = event.extendedProps.plant_location || 'colma_main';
                    const groupLabel = plantLocationMap[plantLocation] || plantLocation.charAt(0).toUpperCase() + plantLocation.slice(1);
                    groupStartLabel.textContent = groupLabel;
                    
                    console.log(`Adding group header: ${groupLabel} for order ${props.order_number}`);
                    eventMainEl.insertBefore(groupStartLabel, eventMainEl.firstChild);
                }
            }
        });
        calendar.render();

        // 3. Add escape key listener to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                // Check if the OrderModal is visible
                const modalElement = document.querySelector('[wire\\:click="closeModal"]');
                
                if (modalElement) {
                    // Find the Livewire component and call closeModal
                    const livewireEl = modalElement.closest('[wire\\:id]');
                    if (livewireEl) {
                        const componentId = livewireEl.getAttribute('wire:id');
                        if (window.Livewire && componentId) {
                            const component = Livewire.find(componentId);
                            if (component) {
                                component.call('closeModal');
                            }
                        }
                    }
                }
            }
        });

        // 4. Listen for calendar refresh events
        document.addEventListener('livewire:init', () => {
            Livewire.on('refresh-calendar', () => {
                console.log('Refreshing calendar after order creation');
                if (calendar) {
                    calendar.refetchEvents();
                }
            });
        });
    });

    // Function to initialize dragging for all sidebar orders
    function initializeSidebarDragging() {
        const orderElements = document.querySelectorAll('.fc-draggable-order');
        orderElements.forEach(orderEl => {
            if (window.FullCalendar && FullCalendar.Draggable) {
                new FullCalendar.Draggable(orderEl, {
                    eventData: {
                        id: orderEl.dataset.orderId,
                        title: orderEl.textContent.trim(),
                    }
                });
            }
        });
    }

    function handleEventDragStart(info) {
        console.log('Event drag started:', info.event.id);
        // Store reference to the event being dragged
        draggedCalendarEvent = {
            id: info.event.id,
            title: info.event.title,
            extendedProps: info.event.extendedProps,
            eventObj: info.event
        };
    }

    function handleEventDragStop(info) {
        console.log('Event drag stopped:', info.event.id);
        
        // Check if the event was dropped on the sidebar
        const sidebarEl = document.getElementById('unassigned-orders');
        const rect = sidebarEl.getBoundingClientRect();
        const mouseX = info.jsEvent.clientX;
        const mouseY = info.jsEvent.clientY;
        
        // Check if mouse position is within sidebar bounds
        if (mouseX >= rect.left && mouseX <= rect.right && 
            mouseY >= rect.top && mouseY <= rect.bottom) {
            
            console.log('Event dropped on sidebar!');
            
            // Call backend to unassign the order
            fetch('/orders/unassign-date', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    order_id: draggedCalendarEvent.id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove event from calendar
                    info.event.remove();
                    
                    // Create new order element in sidebar
                    const orderElement = createOrderElement(data.order);
                    sidebarEl.appendChild(orderElement);
                    
                    // Re-initialize dragging for the new element
                    if (window.FullCalendar && FullCalendar.Draggable) {
                        new FullCalendar.Draggable(orderElement, {
                            eventData: {
                                id: data.order.id,
                                title: data.order.location_name || data.order.order_number,
                            }
                        });
                    }
                    
                    console.log('Successfully moved event to sidebar');
                    
                    // Refresh the calendar to recalculate group headers for remaining events
                    setTimeout(() => {
                        calendar.refetchEvents();
                    }, 100);
                } else {
                    console.error('Failed to unassign order');
                    alert('Failed to unassign order!');
                }
            })
            .catch(error => {
                console.error('Error unassigning order:', error);
                alert('Failed to unassign order!');
            })
            .finally(() => {
                // Clear the dragged event reference
                draggedCalendarEvent = null;
            });
        } else {
            // Event was dropped elsewhere, clear reference
            draggedCalendarEvent = null;
        }
    }

    function handleEventReceive(info) {
        const localDate = new Date(info.event.start);
        const year = localDate.getFullYear();
        const month = String(localDate.getMonth() + 1).padStart(2, '0');
        const day = String(localDate.getDate()).padStart(2, '0');
        const formattedDate = `${year}-${month}-${day}`;

        fetch('/orders/assign-date', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                order_id: info.event.id,
                assigned_delivery_date: formattedDate
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove from sidebar
                if (info.draggedEl && info.draggedEl.parentNode) {
                    info.draggedEl.parentNode.removeChild(info.draggedEl);
                }
                
                // Remove the event and refresh calendar to get proper grouping
                info.event.remove();
                
                // Refresh the calendar to recalculate all group headers
                setTimeout(() => {
                    calendar.refetchEvents();
                }, 100);
            } else {
                info.revert();
                alert('Failed to assign order!');
            }
        })
        .catch(() => {
            info.revert();
            alert('Failed to assign order!');
        });
    }

    // Helper function to create order element for sidebar
    function createOrderElement(order) {
        const orderDiv = document.createElement('div');
        orderDiv.className = 'fc-draggable-order fc-event';
        orderDiv.setAttribute('data-order-id', order.id);
        orderDiv.setAttribute('draggable', 'true');
        orderDiv.style.marginBottom = '4px';
        orderDiv.style.cursor = 'grab';
        orderDiv.onclick = function() {
            @this.call('openOrderModal', order.id);
        };

        const containerDiv = document.createElement('div');
        containerDiv.className = `order-container status-${(order.status_raw || order.status).toLowerCase()}`;

        const titleDiv = document.createElement('div');
        titleDiv.className = 'order-title';
        titleDiv.textContent = order.location_name || order.order_number;

        const addressDiv = document.createElement('div');
        addressDiv.className = 'order-address';
        if (order.location_city && order.location_state) {
            addressDiv.textContent = `${order.location_city}, ${order.location_state}`;
        }

        const statusDiv = document.createElement('div');
        statusDiv.className = 'order-status';
        statusDiv.innerHTML = `
            <span>${order.status}</span>
            ${order.order_number ? `<span>#${order.order_number.replace(/^ORD-/, '')}</span>` : ''}
        `;

        containerDiv.appendChild(titleDiv);
        if (order.location_city && order.location_state) {
            containerDiv.appendChild(addressDiv);
        }
        containerDiv.appendChild(statusDiv);
        orderDiv.appendChild(containerDiv);

        return orderDiv;
    }

    function handleDateClick(info) {
        console.log('Date clicked:', info.dateStr);
        
        // Dispatch Livewire event to open create order modal
        @this.call('openCreateOrderModal', info.dateStr);
    }

    function handleEventClick(info) {
        // info.event.id is the order ID
        console.log('Calendar event clicked, order ID:', info.event.id);
        @this.call('openOrderModal', info.event.id);
    }
    </script>
    @endpush
    {{-- @livewire(\App\Filament\Resources\OrderResource\Widgets\CalendarWidget::class) --}}
    
</x-filament-panels::page>
