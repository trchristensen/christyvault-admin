<div class="relative ml-auto">
    <x-filament::dropdown placement="bottom-end" width="md" class="fi-dropdown">
        <x-slot name="trigger">
            <div class="relative">
                <button
                    class="fi-icon-btn fi-icon-btn-size-md relative flex items-center justify-center rounded-full hover:bg-gray-500/5 focus:bg-gray-500/5"
                    aria-label="Notifications"
                >
                    <x-heroicon-o-bell class="fi-icon-btn-icon h-5 w-5 text-gray-500" />
                    @if($this->unreadCount)
                        <span class="fi-badge absolute -end-1 -top-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-medium text-white">
                            {{ $this->unreadCount }}
                        </span>
                    @endif
                </button>
            </div>
        </x-slot>

        <div class="fi-dropdown-panel w-[32rem] max-h-[32rem] overflow-y-auto divide-y divide-gray-200 bg-white shadow-lg rounded-xl">
            <div class="fi-dropdown-header flex items-center justify-between px-4 py-3 bg-gray-50">
                <span class="fi-dropdown-header-label text-sm font-semibold text-gray-900">
                    Notifications
                </span>
            </div>

            <div class="fi-dropdown-list">
                @forelse($notifications as $notification)
                    <div class="fi-dropdown-list-item group relative hover:bg-gray-50">
                        <div class="flex items-start p-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <p class="fi-dropdown-list-item-label text-sm font-medium text-gray-900">
                                        {{ $notification->data['inventory_item_name'] }}
                                    </p>
                                    <button 
                                        wire:click="markAsRead('{{ $notification->id }}')"
                                        class="fi-icon-btn opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                    >
                                        <x-heroicon-m-x-mark class="h-5 w-5 text-gray-400 hover:text-red-500" />
                                    </button>
                                </div>
                                <div class="mt-1">
                                    <p class="text-sm text-gray-600">
                                        Bin: <span class="font-medium">{{ $notification->data['bin_number'] }}</span>
                                        (<span class="text-gray-500">{{ $notification->data['bin_location'] }}</span>)
                                    </p>
                                    <div class="mt-1 flex items-center space-x-2 text-xs text-gray-500">
                                        <span>{{ $notification->created_at->diffForHumans() }}</span>
                                        <span>&bull;</span>
                                        <span>by {{ $notification->data['scanned_by'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="fi-dropdown-list-item p-8 text-sm text-center text-gray-500">
                        <x-heroicon-o-bell-slash class="mx-auto mb-2 h-8 w-8 text-gray-400" />
                        <p>No unread notifications</p>
                    </div>
                @endforelse
            </div>

            @if($this->unreadCount > 5)
                <div class="fi-dropdown-footer px-4 py-3 text-center bg-gray-50">
                    <a 
                        href="{{ route('operations.notifications') }}" 
                        class="fi-link inline-flex items-center space-x-1 text-sm font-medium text-primary-600 hover:text-primary-500"
                    >
                        <span>View all {{ $this->unreadCount }} notifications</span>
                        <x-heroicon-m-arrow-right class="h-4 w-4" />
                    </a>
                </div>
            @endif
        </div>
    </x-filament::dropdown>
</div> 