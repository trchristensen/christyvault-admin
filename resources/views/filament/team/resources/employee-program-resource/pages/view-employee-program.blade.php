<x-filament-panels::page>
    @php
        $program = $this->getRecord();
        $canManage = auth()->user()?->canManagePrograms() ?? false;
        $categoryLabel = \App\Models\EmployeeProgram::categoryOptions()[$program->category] ?? str($program->category)->headline();
        $sections = $program->sections
            ->map(function (\App\Models\EmployeeProgramSection $section) {
                $section->setRelation(
                    'items',
                    $section->items
                        ->filter(fn (\App\Models\EmployeeProgramItem $item): bool => $item->isVisibleTo(auth()->user()))
                        ->values(),
                );

                return $section;
            })
            ->filter(fn (\App\Models\EmployeeProgramSection $section): bool => $canManage || $section->items->isNotEmpty())
            ->values();
    @endphp

    @if ($program->status !== \App\Models\EmployeeProgram::STATUS_PUBLISHED)
        <x-filament::section icon="heroicon-o-eye-slash" icon-color="warning">
            <x-slot name="heading">This program is {{ $program->status }}</x-slot>
            <x-slot name="description">It is visible here to managers but not currently available to employees.</x-slot>
        </x-filament::section>
    @endif

    <x-filament::section>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-semibold text-primary-700 dark:text-primary-300">{{ $categoryLabel }}</span>
                    @if ($program->owner)
                        <span aria-hidden="true">·</span>
                        <span>Owned by {{ $program->owner->name }}</span>
                    @endif
                </div>

                <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $program->title }}
                </h1>

                @if ($program->summary)
                    <p class="mt-3 max-w-4xl text-base leading-7 text-gray-600 dark:text-gray-300">
                        {{ $program->summary }}
                    </p>
                @endif
            </div>

            @if ($program->status === \App\Models\EmployeeProgram::STATUS_PUBLISHED)
                <div class="flex flex-wrap gap-2">
                    <x-filament::badge color="success">Published program</x-filament::badge>
                    @if ($program->training_enabled)
                        <x-filament::badge color="primary">Can be assigned as training</x-filament::badge>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>

    @if ($introduction = $program->renderedIntroduction())
        <x-filament::section>
            <div class="fi-prose max-w-none dark:prose-invert">
                {{ $introduction }}
            </div>
        </x-filament::section>
    @endif

    @forelse ($sections as $sectionNumber => $section)
        <x-filament::section>
            <x-slot name="heading">{{ $section->title }}</x-slot>
            @if ($section->description)
                <x-slot name="description">{{ $section->description }}</x-slot>
            @endif

            @if ($section->items->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No resources have been added to this section.</p>
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($section->items as $item)
                        @php
                            $materialUrl = $item->type === \App\Models\EmployeeProgramItem::TYPE_FILE
                                ? route('programs.materials.show', $item)
                                : null;
                            $procedureUrl = $item->type === \App\Models\EmployeeProgramItem::TYPE_PROCEDURE && $item->procedure
                                ? \App\Filament\Team\Resources\StandardOperatingProcedureResource::getUrl('view', ['record' => $item->procedure])
                                : null;
                        @endphp

                        <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                            @if ($item->type === \App\Models\EmployeeProgramItem::TYPE_FILE && $item->media_type === 'image')
                                <a href="{{ $materialUrl }}" target="_blank" rel="noopener">
                                    <img
                                        src="{{ $materialUrl }}"
                                        alt="{{ $item->display_title }}"
                                        class="max-h-80 w-full bg-gray-100 object-contain dark:bg-black/20"
                                        loading="lazy"
                                    >
                                </a>
                            @elseif ($item->type === \App\Models\EmployeeProgramItem::TYPE_FILE && $item->media_type === 'video')
                                <video class="max-h-80 w-full bg-black" controls preload="metadata">
                                    <source src="{{ $materialUrl }}" type="{{ $item->mime_type }}">
                                    Your browser cannot play this video.
                                </video>
                            @endif

                            <div class="flex h-full flex-col p-4">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                        <x-filament::icon
                                            :icon="match ($item->type) {
                                                \App\Models\EmployeeProgramItem::TYPE_PROCEDURE => 'heroicon-o-book-open',
                                                \App\Models\EmployeeProgramItem::TYPE_LINK => 'heroicon-o-arrow-top-right-on-square',
                                                default => $item->media_type === 'video' ? 'heroicon-o-play' : 'heroicon-o-paper-clip',
                                            }"
                                            class="size-5"
                                        />
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ \App\Models\EmployeeProgramItem::typeOptions()[$item->type] ?? 'Resource' }}
                                            @if ($item->procedure?->currentRevision)
                                                · {{ $item->procedure->currentRevision->code }}
                                            @endif
                                        </div>
                                        <h3 class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $item->display_title }}</h3>
                                        @if ($item->description)
                                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $item->description }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-3 dark:border-white/10">
                                    @if ($procedureUrl)
                                        <a href="{{ $procedureUrl }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                            Open {{ strtolower($item->procedure->document_label) }}
                                            <x-filament::icon icon="heroicon-m-arrow-right" class="size-4" />
                                        </a>
                                    @elseif ($item->type === \App\Models\EmployeeProgramItem::TYPE_LINK)
                                        <a href="{{ $item->external_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                            Open resource
                                            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4" />
                                        </a>
                                    @elseif ($materialUrl)
                                        <a href="{{ $materialUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                            Open file
                                            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4" />
                                        </a>
                                        <a href="{{ $materialUrl }}?download=1" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white">
                                            Download
                                            <x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-4" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section icon="heroicon-o-rectangle-stack" icon-color="gray">
            <x-slot name="heading">No program resources yet</x-slot>
            <x-slot name="description">This program does not currently contain any resources available to you.</x-slot>
        </x-filament::section>
    @endforelse

    @if ($canManage)
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Program information</x-slot>

            <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ str($program->status)->headline() }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Published</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ $program->published_at?->format('M j, Y g:i A') ?? 'Not published' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Training</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">
                        {{ $program->training_enabled ? 'Enabled · '.count($program->trainingSnapshot()['questions']).' questions' : 'Reference only' }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Audience</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ \App\Models\EmployeeProgram::audienceOptions()[$program->audience] ?? str($program->audience)->headline() }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Plants</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">
                        {{ collect($program->plant_locations)->map(fn (string $plant): string => \App\Models\EmployeeProgram::plantOptions()[$plant] ?? str($plant)->headline())->join(', ') ?: 'All plants' }}
                    </dd>
                </div>
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
