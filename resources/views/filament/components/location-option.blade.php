<div class="py-1">
    <div class="font-medium">{{ $name }}</div>
    <div class="text-sm text-gray-500">{{ $address }}</div>
    @if ($contact)
        <div class="text-sm text-gray-500">Contact: {{ $contact }} {{ $phone ? "- $phone" : '' }}</div>
    @endif
</div>
