@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
    <section class="card narrow">
        <p class="eyebrow">Acceso</p>
        <h1>Iniciar sesión</h1>
        <p class="muted">Accede a la gestión de tus iglesias.</p>

        <form class="stack" method="POST" action="{{ route('login.store') }}">
            @csrf

            <label class="field">
                <span>Correo electrónico</span>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    autofocus
                    required
                >
            </label>
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror

            <label class="field">
                <span>Contraseña</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror

            <label class="checkbox">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Recordarme en este dispositivo
            </label>

            <button class="button" type="submit">Entrar</button>
        </form>
    </section>
@endsection
