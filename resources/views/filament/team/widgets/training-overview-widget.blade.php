<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ $canManage ? 'Training assignments' : 'My training' }}</x-slot>
        <x-slot name="description">
            {{ $canManage ? 'Open and overdue employee training that needs attention.' : 'Programs assigned to you, including required policy acknowledgments and questionnaires.' }}
        </x-slot>
        <x-slot name="afterHeader">
            <a href="{{ $trainingUrl }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                View training →
            </a>
        </x-slot>

        @if ($assignments->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-7 text-center dark:border-gray-700">
                <x-filament::icon icon="heroicon-o-check-badge" class="mx-auto mb-2 size-8 text-success-500" />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                    {{ $canManage ? 'No open training assignments.' : 'You are caught up.' }}
                </p>
            </div>
        @else
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($assignments as $assignment)
                    @php($overdue = $assignment->due_date?->isPast() && $assignment->status !== \App\Models\TrainingAssignment::STATUS_COMPLETED)
                    <a
                        href="{{ \App\Filament\Team\Resources\TrainingAssignmentResource::getUrl('view', ['record' => $assignment]) }}"
                        class="group rounded-xl border border-gray-200 p-4 transition hover:border-primary-400 hover:bg-primary-50/40 dark:border-white/10 dark:hover:border-primary-500/50 dark:hover:bg-primary-500/5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $canManage ? ($assignment->employee?->name ?? 'Former employee') : (\App\Models\TrainingAssignment::statusOptions()[$assignment->status] ?? str($assignment->status)->headline()) }}
                                </div>
                                <h3 class="mt-1 font-semibold text-gray-950 group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">{{ $assignment->program->title }}</h3>
                            </div>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="size-5 shrink-0 text-gray-400 group-hover:text-primary-600" />
                        </div>
                        <div @class([
                            'mt-3 text-sm font-medium',
                            'text-danger-600 dark:text-danger-400' => $overdue,
                            'text-gray-500 dark:text-gray-400' => ! $overdue,
                        ])>
                            @if ($assignment->due_date)
                                {{ $overdue ? 'Overdue' : 'Due' }} {{ $assignment->due_date->format('M j, Y') }}
                            @else
                                No due date
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
