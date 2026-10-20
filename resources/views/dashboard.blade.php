@extends('layouts.app')

@section('title', 'Mis iglesias')

@section('content')
    <p class="eyebrow">Panel</p>
    <h1>Mis iglesias</h1>
    <p class="muted">Selecciona la iglesia con la que quieres trabajar. La verificación de acceso se repite en cada petición.</p>

    @if ($memberships->isEmpty())
        <div class="notice">
            No tienes membresías activas. Pide a un administrador que te invite a una iglesia.
        </div>
    @else
        <div class="church-list">
            @foreach ($memberships as $membership)
                <a class="church-card" href="{{ route('churches.show', $membership->church) }}">
                    <strong>{{ $membership->church->name }}</strong>
                    <span class="meta">Rol: {{ $membership->role }}</span>
                    <span class="meta">{{ $membership->church->timezone ?? 'Zona horaria pendiente' }}</span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
