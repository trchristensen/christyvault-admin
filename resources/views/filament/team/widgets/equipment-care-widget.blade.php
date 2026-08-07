<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Equipment Care</x-slot>

        <x-slot name="description">Optional tasks for downtime. Do something useful, record it, and report anything that needs attention.</x-slot>

        <x-slot name="afterHeader">
            <button
                type="button"
                class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                wire:click="mountAction('submitEquipmentCare')"
                wire:loading.attr="disabled"
                wire:target="mountAction"
            >
                Start a care check →
            </button>
        </x-slot>

        @if ($recentChecks->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-7 text-center dark:border-gray-700">
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="mx-auto mb-2 size-8 text-gray-400" />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No optional care checks recorded yet.</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use this when you have time—not because the app says you are late.</p>
            </div>
        @else
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($recentChecks as $check)
                    @php($asset = $check->assets->first())
                    <div class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $asset?->display_name ?? 'Equipment' }}</p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                {{ $check->completed_at?->timezone('America/Los_Angeles')->format('M j, g:i A') }}
                                · {{ count(data_get($check->responses, 'completed_tasks', [])) }} {{ str('task')->plural(count(data_get($check->responses, 'completed_tasks', []))) }}
                            </p>
                        </div>

                        <span @class([
                            'shrink-0 text-xs font-semibold',
                            'text-success-600 dark:text-success-400' => $check->safe_to_operate,
                            'text-warning-600 dark:text-warning-400' => ! $check->safe_to_operate,
                        ])>
                            {{ $check->safe_to_operate ? 'Recorded' : 'Issue reported' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
