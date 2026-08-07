<x-filament-widgets::widget
    @class([
        'team-dashboard-deliveries-widget',
        'team-dashboard-deliveries-widget--driver-first' => auth()->user()?->shouldPrioritizeTeamDashboardDeliveries(),
    ])
>
    <div
        class="team-deliveries-carousel"
        data-initial-slide="{{ $initialSlide }}"
        x-data="{
            active: {{ $initialSlide }},
            slideLeft(index) {
                const track = this.$refs.track;
                const firstSlide = track?.children[0];
                const slide = track?.children[index];

                return track && firstSlide && slide
                    ? slide.offsetLeft - firstSlide.offsetLeft
                    : 0;
            },
            select(index, behavior = 'smooth') {
                const track = this.$refs.track;
                const slide = track?.children[index];

                if (! track || ! slide) return;

                this.active = index;
                track.scrollTo({ left: this.slideLeft(index), behavior });
            },
            syncFromScroll() {
                const track = this.$refs.track;

                if (! track) return;

                const slides = Array.from(track.children);
                const closest = slides.reduce((best, slide, index) => {
                    const distance = Math.abs(this.slideLeft(index) - track.scrollLeft);

                    return distance < best.distance ? { index, distance } : best;
                }, { index: 0, distance: Number.POSITIVE_INFINITY });

                this.active = closest.index;
            },
        }"
        x-init="$nextTick(() => select(active, 'auto'))"
        @keydown.left.prevent="select(Math.max(0, active - 1))"
        @keydown.right.prevent="select(Math.min({{ count($deliveryDays) - 1 }}, active + 1))"
    >
        <div class="team-deliveries-carousel-toolbar">
            <div class="team-deliveries-carousel-tabs" role="tablist" aria-label="Delivery day">
                @foreach ($deliveryDays as $index => $day)
                    <button
                        type="button"
                        role="tab"
                        class="team-deliveries-carousel-tab"
                        :class="{ 'is-active': active === {{ $index }} }"
                        :aria-selected="active === {{ $index }}"
                        @click="select({{ $index }})"
                    >
                        <span>{{ $day['label'] }}</span>
                        <span class="team-deliveries-carousel-count">{{ $day['total'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="team-deliveries-carousel-arrows" aria-label="Change delivery day">
                <button
                    type="button"
                    aria-label="Show previous day"
                    :disabled="active === 0"
                    @click="select(Math.max(0, active - 1))"
                >
                    <x-filament::icon icon="heroicon-m-chevron-left" />
                </button>
                <button
                    type="button"
                    aria-label="Show next day"
                    :disabled="active === {{ count($deliveryDays) - 1 }}"
                    @click="select(Math.min({{ count($deliveryDays) - 1 }}, active + 1))"
                >
                    <x-filament::icon icon="heroicon-m-chevron-right" />
                </button>
            </div>
        </div>

        <div
            class="team-deliveries-carousel-track"
            x-ref="track"
            tabindex="0"
            @scroll.debounce.100ms="syncFromScroll()"
        >
            @foreach ($deliveryDays as $index => $day)
                <div
                    class="team-deliveries-carousel-slide"
                    role="tabpanel"
                    :aria-hidden="active !== {{ $index }}"
                >
                    @include('filament.team.widgets.partials.delivery-day', [
                        'day' => $day,
                        'scheduleUrl' => $scheduleUrl,
                    ])
                </div>
            @endforeach
        </div>

        <p class="team-deliveries-carousel-hint">Swipe, scroll, or use the day buttons to change days.</p>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
