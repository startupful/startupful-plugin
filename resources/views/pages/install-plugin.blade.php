<x-filament::page>
    {{ $this->form }}

    @if($installationStatus)
        <div class="mt-4 p-4 bg-blue-100 text-blue-700 rounded">
            {{ $installationStatus }}
        </div>
    @endif

    @if($plugins->isNotEmpty())
        <div class="space-y-4 mt-4">
            @foreach($plugins as $plugin)
                <div class="bg-gray-100 dark:bg-white/5 overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6 flex flex-row justify-between items-center">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ $plugin['name'] }}
                            </h3>
                            <div class="mt-2 max-w-xl text-sm text-gray-500">
                                <p>{{ $plugin['description'] }}</p>
                            </div>
                        </div>
                        <div class="mt-5">
                            @if(!($plugin['installed'] ?? false))
                            <x-filament::button
                                wire:click="installPlugin('{{ $plugin['name'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="installPlugin('{{ $plugin['name'] }}')"
                            >
                                <span wire:loading.remove wire:target="installPlugin('{{ $plugin['name'] }}')">
                                    Install
                                </span>
                                <span wire:loading wire:target="installPlugin('{{ $plugin['name'] }}')">
                                    Installing...
                                </span>
                            </x-filament::button>
                            @else
                                <x-filament::button
                                    wire:click="uninstallPlugin('{{ $plugin['name'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="uninstallPlugin('{{ $plugin['name'] }}')"
                                    color="danger"
                                >
                                    <span wire:loading.remove wire:target="uninstallPlugin('{{ $plugin['name'] }}')">
                                        Uninstall
                                    </span>
                                    <span wire:loading wire:target="uninstallPlugin('{{ $plugin['name'] }}')">
                                        Uninstalling...
                                    </span>
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-4">
            No plugins found. Try searching for a Startupful plugin.
        </div>
    @endif
</x-filament::page>