<x-filament-panels::page>
    @php
        $procedure = $this->getRecord();
        $revision = $procedure->currentRevision;
        $canManage = auth()->user()?->canManageProcedures() ?? false;
        $documentLabel = $revision
            ? (\App\Models\StandardOperatingProcedure::typeOptions()[$revision->document_type] ?? 'Document')
            : $procedure->document_label;
        $myAcknowledgement = ! $canManage && $revision
            ? $revision->acknowledgementFor(auth()->user()?->employee)
            : null;
    @endphp

    @if (! $revision)
        <x-filament::section icon="heroicon-o-pencil-square" icon-color="warning">
            <x-slot name="heading">This {{ strtolower($procedure->document_label) }} is still a draft</x-slot>
            <x-slot name="description">It is not visible to employees or through a QR code yet.</x-slot>

            @if ($canManage)
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Team\Resources\StandardOperatingProcedureResource::getUrl('edit', ['record' => $procedure])"
                >
                    Continue editing
                </x-filament::button>
            @endif
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $revision->code }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $revision->version_label }}</span>
                        <span aria-hidden="true">·</span>
                        <span>Effective {{ $revision->effective_date->format('M j, Y') }}</span>
                    </div>

                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $revision->title }}
                    </h1>

                    @if ($revision->summary)
                        <p class="mt-2 max-w-4xl text-base text-gray-600 dark:text-gray-300">
                            {{ $revision->summary }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-filament::badge color="primary">{{ $documentLabel }}</x-filament::badge>
                    <x-filament::badge color="success">Current published version</x-filament::badge>
                    <x-filament::badge color="gray">
                        {{ \App\Models\StandardOperatingProcedure::categoryOptions()[$revision->category] ?? str($revision->category)->headline() }}
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::section>

        @if ($canManage && $procedure->hasUnpublishedChanges())
            <x-filament::section compact icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <x-slot name="heading">Saved draft changes have not been published</x-slot>
                Employees are still seeing {{ $revision->version_label }}.
            </x-filament::section>
        @endif

        <x-filament::section>
            <div class="fi-prose max-w-none dark:prose-invert">
                {{ $revision->renderedContent() }}
            </div>
        </x-filament::section>

        @if ($revision->acknowledgement_required)
            <x-filament::section
                icon="{{ $myAcknowledgement ? 'heroicon-o-check-badge' : 'heroicon-o-pencil-square' }}"
                icon-color="{{ $myAcknowledgement ? 'success' : 'warning' }}"
            >
                <x-slot name="heading">Policy acknowledgment</x-slot>

                @if ($canManage)
                    <x-slot name="description">Acknowledgments always remain tied to {{ $revision->version_label }}.</x-slot>
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div class="text-3xl font-bold text-gray-950 dark:text-white">{{ $revision->acknowledgements()->count() }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">employee acknowledgments recorded</div>
                        </div>
                        <p class="max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $revision->acknowledgement_text }}</p>
                    </div>
                    @if ($revision->acknowledgements()->exists())
                        <div class="mt-5 divide-y divide-gray-200 border-t border-gray-200 pt-2 dark:divide-white/10 dark:border-white/10">
                            @foreach ($revision->acknowledgements()->with(['employee', 'recordedBy'])->latest('acknowledged_at')->get() as $acknowledgement)
                                <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="font-semibold text-gray-950 dark:text-white">{{ $acknowledgement->employee?->name ?? $acknowledgement->signed_name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $acknowledgement->acknowledged_at->format('M j, Y g:i A') }}
                                            · {{ $acknowledgement->method === \App\Models\DocumentAcknowledgement::METHOD_PAPER_IMPORT ? 'Paper acknowledgment' : 'Authenticated online acknowledgment' }}
                                            @if ($acknowledgement->recordedBy)
                                                · Recorded by {{ $acknowledgement->recordedBy->name }}
                                            @endif
                                        </div>
                                    </div>
                                    @if ($acknowledgement->evidence_file_path)
                                        <a
                                            href="{{ route('policy-acknowledgements.evidence.show', $acknowledgement) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                        >
                                            Open signed scan →
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @elseif ($myAcknowledgement)
                    <x-slot name="description">Your acknowledgment is recorded for this exact version.</x-slot>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Acknowledged as <strong>{{ $myAcknowledgement->signed_name }}</strong>
                        on {{ $myAcknowledgement->acknowledged_at->format('M j, Y g:i A') }}.
                    </p>
                @else
                    <x-slot name="description">Review the complete policy, then use “Acknowledge policy” above.</x-slot>
                    <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $revision->acknowledgement_text }}</p>
                @endif
            </x-filament::section>
        @endif

        @if ($revision->attachmentItems()->isNotEmpty())
            <x-filament::section icon="heroicon-o-paper-clip">
                <x-slot name="heading">Related material</x-slot>
                <x-slot name="description">Files published with {{ $revision->version_label }}.</x-slot>

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($revision->attachmentItems() as $attachment)
                        @php
                            $attachmentUrl = route('procedures.attachments.show', [
                                'procedure' => $procedure,
                                'attachment' => $attachment['token'],
                            ]);
                        @endphp

                        <article class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
                            @if ($attachment['media_type'] === 'image')
                                <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                                    <img
                                        src="{{ $attachmentUrl }}"
                                        alt="{{ $attachment['title'] }}"
                                        class="max-h-96 w-full bg-gray-100 object-contain dark:bg-black/20"
                                        loading="lazy"
                                    >
                                </a>
                            @elseif ($attachment['media_type'] === 'video')
                                <video class="max-h-96 w-full bg-black" controls preload="metadata">
                                    <source src="{{ $attachmentUrl }}" type="{{ $attachment['mime_type'] }}">
                                    Your browser cannot play this video.
                                </video>
                            @endif

                            <div class="flex items-start justify-between gap-4 p-4">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-950 dark:text-white">{{ $attachment['title'] }}</h3>
                                    @if ($attachment['description'])
                                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $attachment['description'] }}</p>
                                    @endif
                                </div>

                                <x-filament::button
                                    tag="a"
                                    :href="$attachmentUrl.'?download=1'"
                                    icon="heroicon-o-arrow-down-tray"
                                    color="gray"
                                    size="sm"
                                >
                                    Download
                                </x-filament::button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Document information</x-slot>

            <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Published</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ $revision->published_at->format('M j, Y g:i A') }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Published by</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ $revision->publisher?->name ?? 'Former user' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Review due</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ $revision->review_due_date?->format('M j, Y') ?? 'Not scheduled' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Public QR access</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ $procedure->public_qr_enabled ? 'Enabled' : 'Not enabled' }}</dd>
                </div>
            </dl>

            @if ($revision->change_summary)
                <div class="mt-5 border-t border-gray-200 pt-5 dark:border-white/10">
                    <div class="font-medium text-gray-500 dark:text-gray-400">Changes in this version</div>
                    <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $revision->change_summary }}</p>
                </div>
            @endif
        </x-filament::section>

        @if ($canManage && $procedure->revisions->count() > 1)
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">Revision history</x-slot>

                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($procedure->revisions as $pastRevision)
                        <div class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span class="font-medium text-gray-950 dark:text-white">{{ $pastRevision->version_label }}</span>
                                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{{ str($pastRevision->status)->headline() }}</span>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $pastRevision->published_at->format('M j, Y') }}
                                @if ($pastRevision->change_summary)
                                    · {{ $pastRevision->change_summary }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
