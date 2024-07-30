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
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ $plugin['name'] }}
                        </h3>
                        <div class="mt-2 max-w-xl text-sm text-gray-500">
                            <p>{{ $plugin['description'] }}</p>
                        </div>
                        <div class="mt-3 text-sm">
                            <span class="text-gray-500">Stars: {{ $plugin['stars'] }}</span>
                        </div>
                        <div class="mt-5">
                            <x-filament::button
                                tag="a"
                                href="{{ $plugin['url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View on GitHub
                            </x-filament::button>
                            <x-filament::button
    wire:click="installPlugin('{{ json_encode($plugin) }}')"
    wire:loading.attr="disabled"
    wire:target="installPlugin('{{ json_encode($plugin) }}')"
>
    Install
</x-filament::button>
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