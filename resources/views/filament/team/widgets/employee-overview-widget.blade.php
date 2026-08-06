<x-filament-widgets::widget>
    <div class="team-employee-overview-grid">
        <x-filament::section>
            <x-slot name="heading">Upcoming Company Dates</x-slot>

            <x-slot name="description">Holidays, closures, and other calendar notices for the next 90 days.</x-slot>

            @if ($calendarDays->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-7 text-center dark:border-gray-700">
                    <x-filament::icon
                        icon="heroicon-o-calendar-days"
                        class="mx-auto mb-2 size-8 text-gray-400"
                    />
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No company dates have been posted.</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($calendarDays as $calendarDay)
                        <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex w-12 shrink-0 flex-col items-center overflow-hidden rounded-lg border border-gray-200 bg-white text-center shadow-sm dark:border-white/10 dark:bg-white/5">
                                <span class="w-full bg-primary-600 px-1 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                                    {{ $calendarDay->date->format('M') }}
                                </span>
                                <span class="py-1 text-base font-bold text-gray-950 dark:text-white">
                                    {{ $calendarDay->date->format('j') }}
                                </span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $calendarDay->name }}</p>
                                    <x-filament::badge
                                        :color="match ($calendarDay->type) {
                                            \App\Models\CalendarDay::TYPE_HOLIDAY => 'success',
                                            \App\Models\CalendarDay::TYPE_CLOSURE => 'danger',
                                            \App\Models\CalendarDay::TYPE_SPECIAL_OPEN_DAY => 'info',
                                            default => 'gray',
                                        }"
                                    >
                                        {{ $calendarDay->type_label }}
                                    </x-filament::badge>
                                </div>

                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $calendarDay->date->format('l, F j') }}
                                    @if ($calendarDay->date->isToday())
                                        · Today
                                    @elseif ($calendarDay->date->isTomorrow())
                                        · Tomorrow
                                    @endif
                                </p>

                                @if (filled($calendarDay->notes))
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $calendarDay->notes }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">My Time Off</x-slot>

            <x-slot name="description">Your upcoming approved and pending requests.</x-slot>

            @if (! $employee)
                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-7 text-center dark:border-gray-700">
                    <x-filament::icon
                        icon="heroicon-o-identification"
                        class="mx-auto mb-2 size-8 text-gray-400"
                    />
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No employee profile is linked to this login.</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Contact the office if your time-off information should appear here.</p>
                </div>
            @elseif ($leaveRequests->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-7 text-center dark:border-gray-700">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="mx-auto mb-2 size-8 text-success-500"
                    />
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No upcoming time off.</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Approved or pending requests will appear here.</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($leaveRequests as $leaveRequest)
                        <div class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-950 dark:text-white">
                                    {{ str($leaveRequest->type)->headline() }}
                                </p>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($leaveRequest->start_date->isSameDay($leaveRequest->end_date))
                                        {{ $leaveRequest->start_date->format('l, F j, Y') }}
                                    @else
                                        {{ $leaveRequest->start_date->format('M j') }}–{{ $leaveRequest->end_date->format('M j, Y') }}
                                    @endif
                                </p>
                            </div>

                            <x-filament::badge :color="$leaveRequest->status === 'approved' ? 'success' : 'warning'">
                                {{ str($leaveRequest->status)->headline() }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
