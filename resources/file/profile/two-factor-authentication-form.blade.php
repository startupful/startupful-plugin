<x-action-section>
    <x-slot name="title">
        {{ __('startupful::two-factor-auth.title') }}
    </x-slot>

    <x-slot name="description">
        {{ __('startupful::two-factor-auth.description') }}
    </x-slot>

    <x-slot name="content">
        <h3 class="text-lg font-medium text-color-basic">
            @if ($this->enabled)
                @if ($showingConfirmation)
                    {{ __('startupful::two-factor-auth.finish_enabling') }}
                @else
                    {{ __('startupful::two-factor-auth.enabled') }}
                @endif
            @else
                {{ __('startupful::two-factor-auth.not_enabled') }}
            @endif
        </h3>

        <div class="mt-3 max-w-xl text-sm text-color-sub">
            <p>
                {{ __('startupful::two-factor-auth.when_enabled') }}
            </p>
        </div>

        @if ($this->enabled)
            @if ($showingQrCode)
                <div class="mt-4 max-w-xl text-sm text-color-sub">
                    <p class="font-semibold">
                        @if ($showingConfirmation)
                            {{ __('startupful::two-factor-auth.scan_qr') }}
                        @else
                            {{ __('startupful::two-factor-auth.enabled_scan_qr') }}
                        @endif
                    </p>
                </div>

                <div class="mt-4 p-2 inline-block bg-white">
                    {!! $this->user->twoFactorQrCodeSvg() !!}
                </div>

                <div class="mt-4 max-w-xl text-sm text-color-sub">
                    <p class="font-semibold">
                        {{ __('startupful::two-factor-auth.setup_key') }}: {{ decrypt($this->user->two_factor_secret) }}
                    </p>
                </div>

                @if ($showingConfirmation)
                    <div class="mt-4">
                        <x-label for="code" value="{{ __('startupful::two-factor-auth.code') }}" />

                        <x-input id="code" type="text" name="code" class="block mt-1 w-1/2" inputmode="numeric" autofocus autocomplete="one-time-code"
                            wire:model="code"
                            wire:keydown.enter="confirmTwoFactorAuthentication" />

                        <x-input-error for="code" class="mt-2" />
                    </div>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <div class="mt-4 max-w-xl text-sm text-color-sub">
                    <p class="font-semibold">
                        {{ __('startupful::two-factor-auth.store_recovery') }}
                    </p>
                </div>

                <div class="grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-gray-100 rounded-lg">
                    @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="mt-5">
            @if (! $this->enabled)
                <x-confirms-password wire:then="enableTwoFactorAuthentication">
                    <x-button type="button" wire:loading.attr="disabled">
                        {{ __('startupful::two-factor-auth.enable') }}
                    </x-button>
                </x-confirms-password>
            @else
                @if ($showingRecoveryCodes)
                    <x-confirms-password wire:then="regenerateRecoveryCodes">
                        <x-secondary-button class="me-3">
                            {{ __('startupful::two-factor-auth.regenerate_codes') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @elseif ($showingConfirmation)
                    <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                        <x-button type="button" class="me-3" wire:loading.attr="disabled">
                            {{ __('startupful::two-factor-auth.confirm') }}
                        </x-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="showRecoveryCodes">
                        <x-secondary-button class="me-3">
                            {{ __('startupful::two-factor-auth.show_codes') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @endif

                @if ($showingConfirmation)
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-secondary-button wire:loading.attr="disabled">
                            {{ __('startupful::two-factor-auth.cancel') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-danger-button wire:loading.attr="disabled">
                            {{ __('startupful::two-factor-auth.disable') }}
                        </x-danger-button>
                    </x-confirms-password>
                @endif

            @endif
        </div>
    </x-slot>
</x-action-section>