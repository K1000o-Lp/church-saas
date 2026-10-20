<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1f4d3f">

        <title inertia>{{ config('app.name') }}</title>

        <script>
            (function () {
                try {
                    const stored = localStorage.getItem('theme');
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const dark = stored ? stored === 'dark' : prefersDark;

                    document.documentElement.classList.toggle('dark', dark);
                } catch (error) {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
