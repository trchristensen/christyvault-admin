<x-filament-panels::page>
    <div class="space-y-4">
        @if($notifications->isNotEmpty())
            <div class="flex justify-end">
                <x-filament::button
                    wire:click="markAllAsRead"
                    color="primary"
                >
                    Mark all as read
                </x-filament::button>
            </div>
        @endif

        @forelse($notifications as $notification)
            <div @class([
                'p-4 bg-white rounded-lg shadow transition hover:bg-gray-50',
                'border-l-4 border-primary-500' => !$notification->read_at,
            ])>
                <div class="flex items-start justify-between">
                    <div class="space-y-1 flex-grow cursor-pointer" 
                         onclick="window.location='{{ $notification->data['link'] ?? '#' }}'">
                        <div class="font-medium">
                            {{ $notification->data['inventory_item_name'] ?? 'Unknown Item' }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Bin: {{ $notification->data['bin_number'] ?? 'N/A' }}
                            ({{ $notification->data['bin_location'] ?? 'N/A' }})
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ $notification->created_at->diffForHumans() }}
                            by {{ $notification->data['scanned_by'] ?? 'Unknown' }}
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        @unless($notification->read_at)
                            <x-filament::button
                                wire:click="markAsRead('{{ $notification->id }}')"
                                color="primary"
                                size="sm"
                            >
                                Mark as read
                            </x-filament::button>
                        @endunless

                        <x-filament::button
                            wire:click="delete('{{ $notification->id }}')"
                            color="danger"
                            size="sm"
                        >
                            Delete
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">
                No notifications found
            </div>
        @endforelse

        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    </div>
</x-filament-panels::page> 