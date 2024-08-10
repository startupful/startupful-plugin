<x-action-section>
    <x-slot name="title">
        {{ __('connected-accounts.title') }}
    </x-slot>

    <x-slot name="description">
        {{ __('connected-accounts.description') }}
    </x-slot>

    <x-slot name="content">
        <div class="p-4 bg-red-500/10 text-red-500 border-l-4 border-red-600 rounded font-medium text-sm">
            {{ __('connected-accounts.warning') }}
        </div>

        <div class="space-y-6 mt-6">
            @foreach ($this->providers as $provider)
                @php
                    $account = null;
                    $account = $this->accounts->where('provider', $provider['id'])->first();
                @endphp

                <x-connected-account :provider="$provider" created-at="{{ $account?->created_at }}">
                    <x-slot name="action">
                        @if (! is_null($account))
                            <div class="flex items-center space-x-6">
                                @if (Laravel\Jetstream\Jetstream::managesProfilePhotos() && ! is_null($account->avatar_path))
                                    <button class="cursor-pointer ms-6 text-sm text-gray-500 hover:text-gray-700 focus:outline-none" wire:click="setAvatarAsProfilePhoto({{ $account->id }})">
                                        {{ __('connected-accounts.use_avatar') }}
                                    </button>
                                @endif

                                @if (($this->accounts->count() > 1 || ! is_null(auth()->user()->getAuthPassword())))
                                    <x-danger-button wire:click="confirmRemoveAccount({{ $account->id }})" wire:loading.attr="disabled">
                                        {{ __('connected-accounts.remove') }}
                                    </x-danger-button>
                                @endif
                            </div>
                        @else
                            <x-action-link href="{{ route('oauth.redirect', ['provider' => $provider['id']]) }}">
                                {{ __('connected-accounts.connect') }}
                            </x-action-link>
                        @endif
                    </x-slot>

                </x-connected-account>
            @endforeach
        </div>

        <!-- Logout Other Devices Confirmation Modal -->
        <x-dialog-modal wire:model="confirmingAccountRemoval">
            <x-slot name="title">
                {{ __('connected-accounts.confirm_removal_title') }}
            </x-slot>

            <x-slot name="content">
                {{ __('connected-accounts.confirm_removal_description') }}

                <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-input type="password" class="mt-1 block w-3/4"
                             autocomplete="current-password"
                             placeholder="{{ __('connected-accounts.password') }}"
                             x-ref="password"
                             wire:model="password"
                             wire:keydown.enter="removeConnectedAccount" />

                    <x-input-error for="password" class="mt-2" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingAccountRemoval')" wire:loading.attr="disabled">
                    {{ __('connected-accounts.cancel') }}
                </x-secondary-button>

                <x-danger-button class="ml-2" wire:click="removeConnectedAccount" wire:loading.attr="disabled">
                    {{ __('connected-accounts.remove_account') }}
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>