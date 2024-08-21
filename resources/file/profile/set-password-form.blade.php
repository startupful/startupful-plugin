<x-form-section submit="setPassword">
    <x-slot name="title">
        {{ __('set-password.title') }}
    </x-slot>

    <x-slot name="description">
        {{ __('set-password.description') }}
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6 sm:col-span-4">
            <x-label for="password" value="{{ __('set-password.new_password') }}" />
            <x-input id="password" type="password" class="mt-1 block w-full" wire:model.defer="state.password" autocomplete="new-password" />
            <x-input-error for="password" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="password_confirmation" value="{{ __('set-password.confirm_password') }}" />
            <x-input id="password_confirmation" type="password" class="mt-1 block w-full" wire:model.defer="state.password_confirmation" autocomplete="new-password" />
            <x-input-error for="password_confirmation" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-3" on="saved">
            {{ __('set-password.password_saved') }}
        </x-action-message>

        <x-button>
            {{ __('set-password.save') }}
        </x-button>
    </x-slot>
</x-form-section>