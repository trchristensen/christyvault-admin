<div class="relative ml-auto">
    <x-filament::dropdown placement="bottom-end" width="md">
        <x-slot name="trigger">
            <div class="relative">
                <button
                    class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-500/5 focus:bg-gray-500/5"
                    aria-label="Notifications"
                >
                    <x-heroicon-o-bell class="w-5 h-5 text-gray-500" />
                    @if($this->unreadCount)
                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 text-xs font-medium text-white bg-danger-600 rounded-full">
                            {{ $this->unreadCount }}
                        </span>
                    @endif
                </button>
            </div>
        </x-slot>

        <div class="fi-dropdown-list w-[32rem] max-h-[32rem] overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800 shadow-lg rounded-xl">
            <div class="fi-dropdown-header px-4 py-3 flex items-center justify-between bg-gray-50/50 dark:bg-gray-700/50">
                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                    Notifications
                </span>
            </div>

            <div>
                @forelse($notifications as $notification)
                    <div class="relative hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
                        <div class="fi-dropdown-item flex items-start p-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $notification->data['inventory_item_name'] }}
                                    </p>
                                    <button 
                                        wire:click="markAsRead('{{ $notification->id }}')"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                    >
                                        <x-heroicon-m-x-mark class="w-5 h-5 text-gray-400 hover:text-danger-500" />
                                    </button>
                                </div>
                                <div class="mt-1">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Bin: <span class="font-medium">{{ $notification->data['bin_number'] }}</span>
                                        (<span class="text-gray-500 dark:text-gray-400">{{ $notification->data['bin_location'] }}</span>)
                                    </p>
                                    <div class="mt-1 flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ $notification->created_at->diffForHumans() }}</span>
                                        <span>&bull;</span>
                                        <span>by {{ $notification->data['scanned_by'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="fi-dropdown-item p-8 text-sm text-center text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-bell-slash class="w-8 h-8 mx-auto mb-2 text-gray-400 dark:text-gray-500" />
                        <p>No unread notifications</p>
                    </div>
                @endforelse
            </div>

            @if($this->unreadCount > 5)
                <div class="fi-dropdown-footer px-4 py-3 text-center bg-gray-50/50 dark:bg-gray-700/50">
                    <a 
                        href="{{ route('operations.notifications') }}" 
                        class="inline-flex items-center space-x-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        <span>View all {{ $this->unreadCount }} notifications</span>
                        <x-heroicon-m-arrow-right class="w-4 h-4" />
                    </a>
                </div>
            @endif
        </div>
    </x-filament::dropdown>
</div>