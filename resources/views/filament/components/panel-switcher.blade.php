@php
    $isSidebarCollapsible = filament()->isSidebarCollapsibleOnDesktop();
    $currentPanelId = filament()->getCurrentPanel()->getId();
    $currentPanel = \App\Support\PanelSwitcher::current($currentPanelId);
    $panels = \App\Support\PanelSwitcher::options($currentPanelId);
    $totalUnread = collect($panels)->sum('unread');
@endphp

@if ($panels !== [])
    <div
        class="fi-panel-switcher"
        style="margin-right: -0.5rem; margin-bottom: -1rem; margin-left: -0.5rem; width: calc(100% + 1rem);"
    >
        <x-filament::dropdown
            placement="bottom-start"
            width="sm"
            style="width: 100%;"
        >
            <x-slot name="trigger" style="width: 100%;">
                <x-filament::input.wrapper
                    :prefix-icon="$currentPanel['icon']"
                    inline-prefix
                    style="width: 100%;"
                >
                    <div class="fi-select-input">
                        <button
                            type="button"
                            class="fi-select-input-btn items-center gap-x-2"
                            aria-label="Switch panels from {{ $currentPanel['label'] }}"
                        >
                            <span
                                @if ($isSidebarCollapsible)
                                    x-show="$store.sidebar.isOpen"
                                    x-transition:enter="fi-transition-enter"
                                    x-transition:enter-start="fi-transition-enter-start"
                                x-transition:enter-end="fi-transition-enter-end"
                            @endif
                            class="min-w-0 flex-1 truncate"
                            style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                        >
                                {{ $currentPanel['label'] }}
                            </span>

                            @if ($totalUnread > 0)
                                <span
                                    @if ($isSidebarCollapsible)
                                        x-show="$store.sidebar.isOpen"
                                    @endif
                                    class="me-1"
                                >
                                    <x-filament::badge color="danger">
                                        {{ $totalUnread }}
                                    </x-filament::badge>
                                </span>
                            @endif
                        </button>
                    </div>
                </x-filament::input.wrapper>
            </x-slot>

            <x-filament::dropdown.header>
                Switch panels
            </x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($panels as $panel)
                    <x-filament::dropdown.list.item
                        :badge="$panel['unread'] ?: null"
                        badge-color="danger"
                        :href="$panel['url']"
                        :icon="$panel['icon']"
                        tag="a"
                    >
                        {{ $panel['label'] }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    </div>
@endif
