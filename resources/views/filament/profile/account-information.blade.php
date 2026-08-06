<x-filament::section
    :aside="true"
    heading="Account"
    description="Manage how your name appears. Login addresses are controlled by an administrator."
>
    <form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}

        <div class="text-right">
            <x-filament::button type="submit">
                Save account information
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
