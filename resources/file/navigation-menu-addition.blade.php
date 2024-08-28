<div class="relative mr-3">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                    <button class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        @switch(app()->getLocale())
                            @case('ko')
                                한국어
                                @break
                            @case('en')
                                English
                                @break
                            @case('zh')
                                中文
                                @break
                            @case('ja')
                                日本語
                                @break
                            @case('de')
                                Deutsch
                                @break
                            @case('fr')
                                Français
                                @break
                            @case('pt')
                                Português
                                @break
                            @case('hi')
                                हिंदी
                                @break
                            @case('tl')
                                Filipino
                                @break
                            @case('th')
                                ภาษาไทย
                                @break
                            @default
                                {{ strtoupper(app()->getLocale()) }}
                        @endswitch
                        <div class="ml-1">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link href="{{ route('language.switch', 'ko') }}">
                            한국어
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'en') }}">
                            English
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'zh') }}">
                            中文
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'ja') }}">
                            日本語
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'de') }}">
                            Deutsch
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'fr') }}">
                            Français
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'pt') }}">
                            Português
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'hi') }}">
                            हिंदी
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'tl') }}">
                            Filipino
                        </x-dropdown-link>
                        <x-dropdown-link href="{{ route('language.switch', 'th') }}">
                            ภาษาไทย
                        </x-dropdown-link>
                    </x-slot>

                </x-dropdown>
            </div>

            <div class="mr-3" x-data="{ darkMode: document.documentElement.classList.contains('dark') }">
                <button @click="darkMode = window.toggleDarkMode()" 
                        id="theme-toggle"
                        class="w-6 h-6 rounded-lg flex items-center justify-center">
                    <x-heroicon-o-moon x-show="!darkMode" class="w-5 h-5 text-gray-500" />
                    <x-heroicon-o-sun x-show="darkMode" class="w-5 h-5 text-yellow-500" />
                </button>
            </div>