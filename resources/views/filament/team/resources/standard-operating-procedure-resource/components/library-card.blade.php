@php
    $procedure = $getRecord();
    $canManage = auth()->user()?->canManageProcedures() ?? false;
    $revision = $procedure->currentRevision;
    $category = \App\Filament\Team\Resources\StandardOperatingProcedureResource::libraryCategory($procedure);
    $categoryLabel = \App\Models\StandardOperatingProcedure::categoryOptions()[$category] ?? str($category)->headline();
    $categoryIcon = match ($category) {
        'safety' => 'heroicon-o-shield-check',
        'delivery' => 'heroicon-o-truck',
        'production' => 'heroicon-o-building-office-2',
        'operations' => 'heroicon-o-clipboard-document-list',
        'quality' => 'heroicon-o-check-badge',
        'equipment' => 'heroicon-o-wrench-screwdriver',
        'emergency' => 'heroicon-o-exclamation-triangle',
        'human_resources' => 'heroicon-o-user-group',
        default => 'heroicon-o-book-open',
    };
    $title = $canManage ? $procedure->title : ($revision?->title ?? $procedure->title);
    $summary = $canManage ? $procedure->summary : ($revision?->summary ?? $procedure->summary);
    $code = $canManage ? $procedure->code : ($revision?->code ?? $procedure->code);
    $documentType = $canManage ? $procedure->document_type : ($revision?->document_type ?? $procedure->document_type);
    $documentLabel = \App\Models\StandardOperatingProcedure::typeOptions()[$documentType] ?? 'Document';
    $status = $procedure->status_label;
    $statusColor = match ($status) {
        'Published' => 'success',
        'Published · changes pending' => 'warning',
        'Archived' => 'gray',
        default => 'info',
    };
    $plants = collect($procedure->plant_locations)
        ->map(fn (string $plant): string => \App\Models\StandardOperatingProcedure::plantOptions()[$plant] ?? str($plant)->headline())
        ->join(', ');
    $audience = \App\Models\StandardOperatingProcedure::audienceOptions()[$procedure->audience] ?? str($procedure->audience)->headline();
    $positionNames = $procedure->positions->pluck('display_name')->filter()->join(', ');
@endphp

<div class="procedure-library-card" data-category="{{ $category }}">
    <div class="procedure-card-header">
        <div class="procedure-card-identity">
            <span class="procedure-card-icon">
                <x-filament::icon :icon="$categoryIcon" />
            </span>
            <div class="procedure-card-identity-text">
                <div class="procedure-card-category">{{ $documentLabel }} · {{ $categoryLabel }}</div>
                <div class="procedure-card-code">{{ $code }}</div>
            </div>
        </div>

        @if ($canManage)
            <x-filament::badge :color="$statusColor" size="sm">{{ $status }}</x-filament::badge>
        @elseif ($revision)
            <x-filament::badge color="success" size="sm">v{{ $revision->version }}</x-filament::badge>
        @endif
    </div>

    <h3 class="procedure-card-title">{{ $title }}</h3>

    <p class="procedure-card-summary">
        {{ $summary ?: 'Open this document to read the complete information.' }}
    </p>

    <div class="procedure-card-scope">
        <div class="procedure-card-scope-line">
            <x-filament::icon icon="heroicon-o-user-group" />
            <span>
                {{ $audience }}
                @if ($procedure->audience === \App\Models\StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS && $positionNames)
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
            @if ($revision)
                <span>{{ $revision->version_label }} · Effective {{ $revision->effective_date->format('M j, Y') }}</span>
                @if ($revision->review_due_date)
                    <span @class(['is-overdue' => $revision->review_due_date->isPast()])>
                        Review {{ $revision->review_due_date->isPast() ? 'overdue' : 'due '.$revision->review_due_date->format('M j, Y') }}
                    </span>
                @endif
            @else
                <span>Not published yet</span>
            @endif
        </div>

        <span class="procedure-card-open">
            View {{ strtolower($documentLabel) }}
            <x-filament::icon icon="heroicon-m-arrow-right" />
        </span>
    </div>
</div>
