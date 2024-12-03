<div class="flex flex-col gap-1">
    <div class="flex items-center gap-2">
        <span class="font-medium">{{ $orderNumber }}</span>
        <span class="text-gray-500">{{ $customerName }}</span>
    </div>
    <div class="flex flex-col items-start text-sm text-gray-500">
        @if ($location_line1)
            <div>{{ $location_line1 }}</div>
        @endif
        @if ($location_line2)
            <div>{{ $location_line2 }}</div>
        @endif
        <div>Status: {{ $status }}</div>
        @if ($requestedDeliveryDate)
            <div>Requested: {{ $requestedDeliveryDate }}</div>
        @endif
        @if ($assignedDeliveryDate)
            <div>Assigned: {{ $assignedDeliveryDate }}</div>
        @endif
    </div>
</div>
