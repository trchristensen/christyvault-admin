<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Planning opportunities</x-slot>

        <x-slot name="description">
            Upcoming schedule gaps and trips whose saved load plan may have room for more work.
        </x-slot>

        @if ($opportunities === [])
            <div class="om-empty-state">
                <x-filament::icon icon="heroicon-o-magnifying-glass" />
                <div>
                    <strong>No clear opportunities found.</strong>
                    <span>The dashboard will surface schedule gaps and usable load capacity as they appear.</span>
                </div>
            </div>
        @else
            <div class="om-opportunity-grid">
                @foreach ($opportunities as $opportunity)
                    <article class="om-opportunity-card is-{{ $opportunity['tone'] }}">
                        <div class="om-opportunity-heading">
                            <div class="om-attention-icon">
                                <x-filament::icon :icon="$opportunity['icon']" />
                            </div>
                            <div>
                                <span class="om-eyebrow">{{ $opportunity['type'] }}</span>
                                <h3>{{ $opportunity['title'] }}</h3>
                            </div>
                        </div>

                        <p>{{ $opportunity['detail'] }}</p>

                        @if ($opportunity['metrics'] !== [])
                            <div class="om-metric-pills">
                                @foreach ($opportunity['metrics'] as $metric)
                                    <span>{{ $metric }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if (! empty($opportunity['diagram']))
                            <x-load-planning.compact-diagram :diagram="$opportunity['diagram']" />
                        @endif

                        @if ($opportunity['candidates'] !== [])
                            <div class="om-candidate-block">
                                <span class="om-subsection-heading">Possible customers to contact</span>
                                @foreach ($opportunity['candidates'] as $candidate)
                                    <a href="{{ $candidate['url'] }}">
                                        <strong>{{ $candidate['name'] }}</strong>
                                        <span>{{ $candidate['timing'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <a href="{{ $opportunity['url'] }}" class="om-card-action" @if ($opportunity['type'] === 'Load opportunity') target="_blank" @endif>
                            {{ $opportunity['action'] }} →
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
