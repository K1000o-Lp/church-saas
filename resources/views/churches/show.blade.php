@extends('layouts.app')

@section('title', $church->name)

@section('content')
    <p class="eyebrow">
        {{ $church->country_code ?? 'País pendiente' }}
        @if ($church->currency_code)
            · {{ $church->currency_code }}
        @endif
    </p>
    <h1>{{ $church->name }}</h1>
    <p class="muted">Sesión activa como {{ $membership->role }}.</p>

    <div class="notice">
        Contexto de iglesia resuelto para esta petición. Los datos de otras iglesias permanecen fuera del alcance.
    </div>
@endsection
