<x-filament::section
    :aside="true"
    heading="Employment and contact"
    description="Keep your contact information current. The office manages your plant, positions, and employment dates."
>
    <form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}

        <div class="text-right">
            <x-filament::button type="submit">
                Save contact information
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
