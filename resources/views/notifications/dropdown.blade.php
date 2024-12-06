<div class="relative ml-auto">
    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            <button
                class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-500/5 focus:bg-gray-500/5"
                aria-label="Notifications"
            >
                <x-heroicon-o-bell class="w-5 h-5" />
                @if(auth()->user()->unreadNotifications->count())
                    <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                        {{ auth()->user()->unreadNotifications->count() }}
                    </span>
                @endif
            </button>
        </x-slot>

        <div class="w-80 max-h-96 overflow-y-auto">
            @forelse(auth()->user()->notifications()->latest()->take(5)->get() as $notification)
                <div @class([
                    'p-4 border-b last:border-0',
                    'bg-primary-50' => ! $notification->read_at,
                ])>
                    <div class="flex items-start space-x-3">
                        <div class="flex-1 space-y-1">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $notification->data['inventory_item_name'] }}
                            </p>
                            <p class="text-sm text-gray-500">
                                Bin: {{ $notification->data['bin_number'] }}
                                ({{ $notification->data['bin_location'] }})
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $notification->created_at->diffForHumans() }}
                                by {{ $notification->data['scanned_by'] }}
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-4 text-sm text-gray-500">
                    No notifications
                </div>
            @endforelse

            @if(auth()->user()->notifications->count() > 5)
                <div class="p-4 text-center border-t">
                    <a href="/notifications" class="text-sm text-primary-600 hover:text-primary-500">
                        View all notifications
                    </a>
                </div>
            @endif
        </div>
    </x-filament::dropdown>
</div> 