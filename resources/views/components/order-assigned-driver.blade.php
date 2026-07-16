@props(['order'])

@php
    $assignedDriver = $order->driver ?? $order->trip?->driver;
@endphp

<div>
    <p class="text-sm text-gray-600 dark:text-gray-400">Assigned Driver</p>
    <p class="font-medium">{{ $assignedDriver?->name ?? 'Unassigned' }}</p>
</div>
