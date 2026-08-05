<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Today and next workday</x-slot>

        <x-slot name="description">
            Dispatch readiness, employee availability, and company calendar days in one place.
        </x-slot>

        <div class="om-day-grid">
            @foreach ($days as $day)
                <article class="om-day-card">
                    <header class="om-day-header">
                        <div>
                            <span class="om-eyebrow">{{ $day['label'] }}</span>
                            <h3>{{ $day['date']->format('l, M j') }}</h3>
                        </div>
                        @if ($day['blocked'])
                            <span class="om-pill is-danger">{{ $day['blocking_reason'] ?? 'Blocked' }}</span>
                        @else
                            <a href="{{ $day['calendar_url'] }}" class="om-action-link">Open schedule →</a>
                        @endif
                    </header>

                    @if ($day['standalone_orders'] > 0)
                        <div class="om-inline-alert is-warning">
                            {{ trans_choice(':count delivery order still needs a trip|:count delivery orders still need trips', $day['standalone_orders'], ['count' => $day['standalone_orders']]) }}
                        </div>
                    @endif

                    <div class="om-subsection-heading">Delivery trips</div>

                    @if ($day['trips']->isEmpty())
                        <p class="om-muted-copy">No active delivery trips.</p>
                    @else
                        <div class="om-trip-list">
                            @foreach ($day['trips'] as $trip)
                                <div class="om-trip-row">
                                    <div class="om-trip-main">
                                        <div class="om-trip-title-row">
                                            <a href="{{ $trip['url'] }}">{{ $trip['number'] }}</a>
                                            <span class="om-pill {{ $trip['ready'] ? 'is-success' : 'is-warning' }}">
                                                {{ $trip['ready'] ? 'Ready' : 'Needs attention' }}
                                            </span>
                                        </div>
                                        <span>{{ trans_choice(':count stop|:count stops', $trip['stops'], ['count' => $trip['stops']]) }} · {{ $trip['driver'] }} · {{ $trip['vehicle'] }}</span>
                                        @if ($trip['locations'])
                                            <span>{{ $trip['locations'] }}{{ $trip['more_locations'] ? ' +'.$trip['more_locations'].' more' : '' }}</span>
                                        @endif
                                        @if (! $trip['ready'])
                                            <span class="om-trip-issues">{{ implode(' · ', $trip['issues']) }}</span>
                                        @endif
                                    </div>
                                    @if ($trip['load_url'])
                                        <a href="{{ $trip['load_url'] }}" target="_blank" class="om-secondary-link">Load summary</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="om-people-block">
                        <div class="om-subsection-heading">Employees off</div>
                        @if ($day['absences']->isEmpty())
                            <p class="om-muted-copy">No approved absences.</p>
                        @else
                            <div class="om-absence-list">
                                @foreach ($day['absences'] as $absence)
                                    <div>
                                        <strong>{{ $absence['employee'] }}</strong>
                                        <span>{{ $absence['type'] }}{{ $absence['positions'] ? ' · '.$absence['positions'] : '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="om-week-calendar">
            <div class="om-subsection-heading">Upcoming calendar days this week</div>

            @if ($calendarDays === [])
                <p class="om-muted-copy">No holidays, closures, special open days, or calendar notes are coming up this week.</p>
            @else
                <div class="om-calendar-day-list">
                    @foreach ($calendarDays as $calendarDay)
                        <a href="{{ $calendarDay['url'] }}" class="om-calendar-day-row">
                            <div class="om-calendar-date">
                                <strong>{{ $calendarDay['date']->format('D') }}</strong>
                                <span>{{ $calendarDay['date']->format('M j') }}</span>
                            </div>
                            <div class="om-calendar-day-copy">
                                <strong>{{ $calendarDay['name'] }}</strong>
                                <span>{{ $calendarDay['type'] }}{{ $calendarDay['notes'] ? ' · '.$calendarDay['notes'] : '' }}</span>
                            </div>
                            <span class="om-pill is-{{ $calendarDay['tone'] }}">{{ $calendarDay['status'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
