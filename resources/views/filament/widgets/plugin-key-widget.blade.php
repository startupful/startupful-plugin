<x-filament::widget>
    <x-filament::card>
        <form wire:submit.prevent="verifyOrRemove">
            {{ $this->form }}

            <x-filament::button
                type="submit"
                :color="$isVerified ? 'danger' : 'primary'"
            >
                {{ $isVerified ? 'Remove Plugin Key' : 'Verify Plugin Key' }}
            </x-filament::button>
        </form>
    </x-filament::card>
</x-filament::widget>