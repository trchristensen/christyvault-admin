@php
    $program = $getRecord();
    $canManage = auth()->user()?->canManagePrograms() ?? false;
    $categoryLabel = \App\Models\EmployeeProgram::categoryOptions()[$program->category] ?? str($program->category)->headline();
    $categoryIcon = match ($program->category) {
        'orientation' => 'heroicon-o-sparkles',
        'safety' => 'heroicon-o-shield-check',
        'delivery' => 'heroicon-o-truck',
        'production' => 'heroicon-o-building-office-2',
        'operations' => 'heroicon-o-clipboard-document-list',
        'quality' => 'heroicon-o-check-badge',
        'equipment' => 'heroicon-o-wrench-screwdriver',
        'emergency' => 'heroicon-o-exclamation-triangle',
        'human_resources' => 'heroicon-o-user-group',
        default => 'heroicon-o-rectangle-stack',
    };
    $statusColor = match ($program->status) {
        \App\Models\EmployeeProgram::STATUS_PUBLISHED => 'success',
        \App\Models\EmployeeProgram::STATUS_ARCHIVED => 'gray',
        default => 'info',
    };
    $plants = collect($program->plant_locations)
        ->map(fn (string $plant): string => \App\Models\EmployeeProgram::plantOptions()[$plant] ?? str($plant)->headline())
        ->join(', ');
    $audience = \App\Models\EmployeeProgram::audienceOptions()[$program->audience] ?? str($program->audience)->headline();
    $positionNames = $program->positions->pluck('display_name')->filter()->join(', ');
    $visibleItems = $program->sections
        ->flatMap->items
        ->filter(fn (\App\Models\EmployeeProgramItem $item): bool => $item->isVisibleTo(auth()->user()));
@endphp

<div class="procedure-library-card" data-category="{{ $program->category }}">
    <div class="procedure-card-header">
        <div class="procedure-card-identity">
            <span class="procedure-card-icon">
                <x-filament::icon :icon="$categoryIcon" />
            </span>
            <div class="procedure-card-identity-text">
                <div class="procedure-card-category">{{ $categoryLabel }}</div>
                <div class="procedure-card-code">{{ $visibleItems->count() }} {{ str('resource')->plural($visibleItems->count()) }}</div>
            </div>
        </div>

        @if ($canManage)
            <x-filament::badge :color="$statusColor" size="sm">{{ str($program->status)->headline() }}</x-filament::badge>
        @endif
    </div>

    <h3 class="procedure-card-title">{{ $program->title }}</h3>

    <p class="procedure-card-summary">
        {{ $program->summary ?: 'Open this program to browse its procedures and related resources.' }}
    </p>

    <div class="procedure-card-scope">
        <div class="procedure-card-scope-line">
            <x-filament::icon icon="heroicon-o-user-group" />
            <span>
                {{ $audience }}
                @if ($program->audience === \App\Models\EmployeeProgram::AUDIENCE_SELECTED_POSITIONS && $positionNames)
                    · {{ $positionNames }}
                @endif
            </span>
        </div>
        <div class="procedure-card-scope-line">
            <x-filament::icon icon="heroicon-o-map-pin" />
            <span>{{ $plants ?: 'All plants' }}</span>
        </div>
    </div>

    <div class="procedure-card-footer">
        <div class="procedure-card-dates">
            @if ($program->published_at)
                <span>Published {{ $program->published_at->format('M j, Y') }}</span>
            @else
                <span>Not published yet</span>
            @endif
            <span>{{ $program->sections->count() }} {{ str('section')->plural($program->sections->count()) }}</span>
        </div>

        <span class="procedure-card-open">
            View program
            <x-filament::icon icon="heroicon-m-arrow-right" />
        </span>
    </div>
</div>
