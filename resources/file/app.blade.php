<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {!! SEOMeta::generate() !!}
        {!! OpenGraph::generate() !!}
        {!! Twitter::generate() !!}
        {!! JsonLd::generate() !!}
        <ANTARTIFACTLINK identifier="updated-layout-head" type="application/vnd.ant.code" language="html" title="Updated layout <head isClosed="true" />
        
        <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        <script>
        // Immediately invoked function to set the theme before page load
        var isDarkMode;
        (function() {
            const theme = localStorage.getItem('theme') || 
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                
            isDarkMode = theme === 'dark';
                
            if (isDarkMode) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
            window.dispatchEvent(new CustomEvent('themeChanged', {
                detail: { isDarkMode: isDarkMode }
            }));
                
            // Prevent flash by hiding the body until the DOM is fully loaded
            document.documentElement.style.visibility = 'hidden';
        })();

            document.addEventListener('DOMContentLoaded', function() {
                document.documentElement.style.visibility = '';
            });

            // Function to update isDarkMode when theme changes
            function updateDarkMode() {
                isDarkMode = document.documentElement.classList.contains('dark');
            }

            // Global function to toggle dark mode
            window.toggleDarkMode = function() {
                const isDarkMode = !document.documentElement.classList.contains('dark');
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
                window.dispatchEvent(new CustomEvent('themeChanged', {
                    detail: { isDarkMode: isDarkMode }
                }));
            };

            // Event listener for theme changes
            window.addEventListener('themeChanged', function(event) {
                console.log('Theme changed event triggered:', event.detail.isDarkMode);
                if (typeof updateUIList === 'function' && lastFetchedUIs && lastFetchedUIs.length > 0) {
                    updateUIList(lastFetchedUIs, event.detail.isDarkMode);
                }
            });
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased" x-data="darkModeData">
        <x-banner />

        <div class="min-h-screen bg-basic">
            @livewire('navigation-menu')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-basic shadow">
                    <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="pt-20">
                @yield('content')
            </main>
        </div>

        @livewireScripts
    </body>
</html>
