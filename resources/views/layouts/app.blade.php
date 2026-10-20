<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>

        <style>
            :root {
                color-scheme: light;
                --bg: #f6f5f1;
                --surface: #ffffff;
                --ink: #1f2933;
                --muted: #667085;
                --line: #d9d6cf;
                --accent: #2f5d50;
                --accent-ink: #ffffff;
                --danger: #b42318;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                background: var(--bg);
                color: var(--ink);
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
                line-height: 1.5;
            }

            a { color: var(--accent); }

            .site-header {
                align-items: center;
                border-bottom: 1px solid var(--line);
                display: flex;
                gap: 1.5rem;
                justify-content: space-between;
                padding: 1rem clamp(1rem, 4vw, 2.5rem);
            }

            .brand { color: var(--ink); font-weight: 700; text-decoration: none; }

            .site-nav { align-items: center; display: flex; flex-wrap: wrap; gap: 1rem; }
            .site-nav form { margin: 0; }
            .site-nav .email { color: var(--muted); font-size: .875rem; }

            main { margin: 0 auto; max-width: 60rem; padding: clamp(1.5rem, 5vw, 3rem) 1rem; }

            h1 { font-size: clamp(1.6rem, 4vw, 2.25rem); line-height: 1.15; margin: 0 0 .5rem; }
            h2 { font-size: 1.15rem; margin: 0 0 .75rem; }
            p { margin: 0 0 1rem; }

            .muted { color: var(--muted); }
            .eyebrow { color: var(--accent); font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }

            .card {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: .75rem;
                padding: clamp(1.25rem, 4vw, 2rem);
            }

            .narrow { margin: 0 auto; max-width: 26rem; }

            .stack { display: grid; gap: 1rem; }

            .field { display: grid; gap: .35rem; }
            .field span { font-size: .9rem; font-weight: 600; }

            input[type="email"], input[type="password"], input[type="text"] {
                background: #fff;
                border: 1px solid var(--line);
                border-radius: .5rem;
                color: var(--ink);
                font: inherit;
                padding: .65rem .75rem;
                width: 100%;
            }

            input:focus-visible, button:focus-visible, a:focus-visible {
                outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent);
                outline-offset: 2px;
            }

            .checkbox { align-items: center; display: flex; gap: .5rem; font-size: .9rem; }

            .button {
                background: var(--accent);
                border: 1px solid var(--accent);
                border-radius: .5rem;
                color: var(--accent-ink);
                cursor: pointer;
                font: inherit;
                font-weight: 600;
                padding: .7rem 1rem;
                width: 100%;
            }

            .button-link {
                background: none;
                border: 0;
                color: var(--accent);
                cursor: pointer;
                font: inherit;
                padding: 0;
            }

            .error { color: var(--danger); font-size: .875rem; margin: 0; }

            .church-list { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }

            .church-card {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: .75rem;
                color: var(--ink);
                display: grid;
                gap: .25rem;
                padding: 1.25rem;
                text-decoration: none;
            }

            .church-card:hover { border-color: var(--accent); }

            .meta { color: var(--muted); font-size: .9rem; }

            .notice {
                background: color-mix(in srgb, var(--accent) 10%, white);
                border: 1px solid color-mix(in srgb, var(--accent) 25%, white);
                border-radius: .5rem;
                color: var(--accent);
                padding: .75rem 1rem;
            }
        </style>
    </head>
    <body>
        <header class="site-header">
            <a class="brand" href="{{ url('/') }}">{{ config('app.name') }}</a>

            <nav class="site-nav">
                @auth
                    <a href="{{ route('dashboard') }}">Iglesias</a>
                    <span class="email">{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="button-link" type="submit">Salir</button>
                    </form>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}">Iniciar sesión</a>
                    @endif
                @endauth
            </nav>
        </header>

        <main>
            @yield('content')
        </main>
    </body>
</html>
