<x-filament-panels::page>
    @php
        $assignment = $this->getRecord();
        $program = $assignment->program;
        $canManage = auth()->user()?->canManageTraining() ?? false;
        $isMine = $assignment->belongsToUser(auth()->user());
        $requiredRevisions = \App\Models\StandardOperatingProcedureRevision::query()
            ->with('procedure')
            ->whereKey($assignment->requiredPolicyRevisions())
            ->get();
        $acknowledgedRevisionIds = $assignment->employee->documentAcknowledgements()
            ->whereIn('standard_operating_procedure_revision_id', $assignment->requiredPolicyRevisions())
            ->pluck('standard_operating_procedure_revision_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $statusColor = match ($assignment->status) {
            \App\Models\TrainingAssignment::STATUS_COMPLETED => 'success',
            \App\Models\TrainingAssignment::STATUS_IN_PROGRESS => 'warning',
            default => 'gray',
        };
    @endphp

    <x-filament::section>
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span>Program version {{ $assignment->program_version }}</span>
                    @if ($assignment->due_date)
                        <span aria-hidden="true">·</span>
                        <span @class(['text-danger-600 dark:text-danger-400' => $assignment->status !== \App\Models\TrainingAssignment::STATUS_COMPLETED && $assignment->due_date->isPast()])>
                            Due {{ $assignment->due_date->format('M j, Y') }}
                        </span>
                    @endif
                    @if (data_get($assignment->content_snapshot, 'estimated_minutes'))
                        <span aria-hidden="true">·</span>
                        <span>About {{ data_get($assignment->content_snapshot, 'estimated_minutes') }} minutes</span>
                    @endif
                </div>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $program->title }}</h1>
                @if ($canManage)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Assigned to <strong>{{ $assignment->employee->name }}</strong></p>
                @elseif ($program->summary)
                    <p class="mt-2 max-w-4xl text-base leading-7 text-gray-600 dark:text-gray-300">{{ $program->summary }}</p>
                @endif
            </div>
            <x-filament::badge :color="$statusColor" size="lg">
                {{ \App\Models\TrainingAssignment::statusOptions()[$assignment->status] ?? str($assignment->status)->headline() }}
            </x-filament::badge>
        </div>
    </x-filament::section>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-filament::section icon="heroicon-o-rectangle-stack">
            <x-slot name="heading">1. Review the program</x-slot>
            <x-slot name="description">Read the assigned materials and watch any included videos.</x-slot>
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                The program contains {{ $program->sections->count() }} {{ str('section')->plural($program->sections->count()) }} of policies, procedures, files, videos, or links.
            </p>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-pencil-square">
            <x-slot name="heading">2. Required policies</x-slot>
            <x-slot name="description">Acknowledgments are recorded separately against exact policy revisions.</x-slot>

            @if ($requiredRevisions->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No policy acknowledgment is required for this assignment.</p>
            @else
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($requiredRevisions as $revision)
                        @php($acknowledged = in_array($revision->getKey(), $acknowledgedRevisionIds, true))
                        <div class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a
                                    href="{{ \App\Filament\Team\Resources\StandardOperatingProcedureResource::getUrl('view', ['record' => $revision->procedure]) }}"
                                    class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                >
                                    {{ $revision->title }}
                                </a>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $revision->code }} · {{ $revision->version_label }}</p>
                            </div>
                            <x-filament::badge :color="$acknowledged ? 'success' : 'warning'" size="sm">
                                {{ $acknowledged ? 'Acknowledged' : 'Required' }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-clipboard-document-check">
            <x-slot name="heading">3. Questionnaire</x-slot>
            <x-slot name="description">The exact questions and answers are preserved with every attempt.</x-slot>
            @if ($assignment->questionnaire() === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">This assignment does not include a questionnaire.</p>
            @else
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="text-3xl font-bold text-gray-950 dark:text-white">{{ count($assignment->questionnaire()) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ str('question')->plural(count($assignment->questionnaire())) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ data_get($assignment->content_snapshot, 'passing_score', 80) }}% to pass</div>
                        @if ($assignment->latest_score !== null)
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Latest: {{ $assignment->latest_score }}%</div>
                        @endif
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>

    @if ($assignment->status === \App\Models\TrainingAssignment::STATUS_COMPLETED)
        <x-filament::section icon="heroicon-o-check-badge" icon-color="success">
            <x-slot name="heading">Completion recorded</x-slot>
            <x-slot name="description">Completed {{ $assignment->completed_at->format('M j, Y g:i A') }} by {{ $assignment->employee->name }}.</x-slot>
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $assignment->completion_certification }}</p>
        </x-filament::section>
    @endif

    @if ($canManage && $assignment->attempts->isNotEmpty())
        <x-filament::section collapsible>
            <x-slot name="heading">Questionnaire attempts</x-slot>
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($assignment->attempts as $attempt)
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div>
                            <div class="font-semibold text-gray-950 dark:text-white">Attempt {{ $loop->remaining + 1 }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $attempt->submitted_at->format('M j, Y g:i A') }}</div>
                        </div>
                        <x-filament::badge :color="$attempt->passed ? 'success' : 'danger'">{{ $attempt->score }}% · {{ $attempt->passed ? 'Passed' : 'Not passed' }}</x-filament::badge>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
