<div class="py-1">
    <div class="font-medium">{{ $orderNumber }}</div>
    @if ($location_line1 || $location_line2)
        <div class="text-sm text-gray-500">
            @if ($location_line1)
                {{ $location_line1 }}<br>
            @endif
            @if ($location_line2)
                {{ $location_line2 }}
            @endif
        </div>
    @endif
    <div class="text-sm text-gray-500">
        @if ($requestedDeliveryDate)
            Requested: {{ $requestedDeliveryDate }}
        @endif
        @if ($assignedDeliveryDate)
            | Assigned: {{ $assignedDeliveryDate }}
        @endif
    </div>
    <div class="text-sm text-gray-500">Status: {{ str($status)->headline() }}</div>
</div>
