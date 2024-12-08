<div class="relative ml-auto">
    <x-filament::dropdown placement="bottom-end" width="md" class="fi-dropdown">
        <x-slot name="trigger">
            <div class="relative">
                <button
                    class="relative flex items-center justify-center rounded-full fi-icon-btn fi-icon-btn-size-md hover:bg-gray-500/5 focus:bg-gray-500/5"
                    aria-label="Notifications">
                    <x-heroicon-o-bell class="w-5 h-5 text-gray-500 fi-icon-btn-icon" />
                    @if ($this->unreadCount)
                        <span
                            style="background-color: #f43f5e; left: -6px; top: -7px; width: 18px; height: 18px; position: absolute; border: 1px solid #fff; font-size: 10px;"
                            class="absolute inline-flex items-center justify-center text-xs font-medium text-white rounded-full fi-badge bg-danger-600">
                            @if ($this->unreadCount > 9)
                                <span>9+</span>
                            @else
                                {{ $this->unreadCount }}
                            @endif
                        </span>
                    @endif
                </button>
            </div>
        </x-slot>

        <div
            class="fi-dropdown-panel w-[32rem] max-h-[32rem] overflow-y-auto divide-y divide-gray-200 bg-white shadow-lg rounded-xl">
            <div class="flex items-center justify-between px-4 py-3 fi-dropdown-header bg-gray-50">
                <span class="text-sm font-semibold text-gray-900 fi-dropdown-header-label">
                    Notifications
                </span>
            </div>

            <div class="fi-dropdown-list">
                @forelse($notifications as $notification)
                    <div class="relative fi-dropdown-list-item group hover:bg-gray-50">
                        <div class="flex items-start p-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">

                                    <p class="text-sm font-medium text-gray-900 fi-dropdown-list-item-label">
                                        {{ $notification->data['inventory_item_name'] }}
                                    </p>
                                    <button wire:click="markAsRead('{{ $notification->id }}')" class="close-button">
                                        <x-heroicon-o-x-mark class="w-5 h-5"
                                            style="width: 18px; height: 18px; color: #64748b;" />
                                    </button>
                                </div>
                                <div class="mt-1">
                                    {{-- department --}}
                                    @if (isset($notification->data['department']))
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">{{ $notification->data['department'] }}
                                                Department</span>
                                        </p>
                                    @endif
                                    {{-- bin --}}
                                    @if ($notification->data['bin_number'])
                                        <p class="text-sm text-gray-600">
                                            Bin: <span
                                                class="font-medium">{{ $notification->data['bin_number'] }}</span>
                                            (<span
                                                class="text-gray-500">{{ $notification->data['bin_location'] }}</span>)
                                        </p>
                                    @endif
                                    <div class="flex items-center mt-1 space-x-2 text-xs text-gray-500">
                                        <span>{{ $notification->created_at->diffForHumans() }}</span>
                                        {{-- <span>&bull;</span> --}}
                                        {{-- <span>by {{ $notification->data['scanned_by'] }}</span> --}}
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        @if (isset($notification->data['remaining_quantity']))
                                            <span class="font-medium">
                                                Remaining: {{ $notification->data['remaining_quantity'] }}
                                                {{ $notification->data['unit_of_measure'] }}
                                            </span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-sm text-center text-gray-500 min-h-30 fi-dropdown-list-item">
                        <x-heroicon-o-bell-slash class="w-8 h-8 mx-auto mb-2 text-gray-400" />
                        <p>No unread notifications</p>
                    </div>
                @endforelse
            </div>

            @if ($this->unreadCount > 5)
                <div class="px-4 py-3 text-center fi-dropdown-footer bg-gray-50">
                    {{-- <a href="{{ route('operations.notifications') }}" --}}
                    <a href="#"
                        class="inline-flex items-center space-x-1 text-sm font-medium fi-link text-primary-600 hover:text-primary-500">
                        <span>View all {{ $this->unreadCount }} notifications</span>
                        <x-heroicon-m-arrow-right class="w-4 h-4" />
                    </a>
                </div>
            @endif
        </div>
    </x-filament::dropdown>
</div>
