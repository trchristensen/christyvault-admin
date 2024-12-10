<div class="py-1">
    <div class="font-medium">{{ $name }}</div>
    <div class="text-sm text-gray-500">
        @if($location)
            {{ $location }}
        @endif
        @if($email || $phone)
            <br>{{ collect([$email, $phone])->filter()->join(' • ') }}
        @endif
    </div>
</div> 