@extends('layouts.app')

@section('title', 'Acceso Profesor / Anfitrión — AVILA CALL')

@section('content')
<div style="flex:1; display:flex; align-items:center; justify-content:center; padding:2rem 1rem;">
    <div class="card" style="max-width:440px; width:100%;">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); margin-bottom:0.3rem;">
                Acceso Anfitrión
            </h1>
            <p style="font-size:0.9rem; color:var(--text-secondary);">
                Inicia sesión para transmitir pantalla, gestionar códigos y moderar tu clase.
            </p>
        </div>

        @if(session('error'))
            <div class="alert alert-error">
                <span>⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                <span>⚠️</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form action="{{ route('host.login.submit') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input" 
                    required 
                    autocomplete="email"
                    value="{{ old('email') }}"
                    placeholder="profesor@ejemplo.com"
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    required 
                    autocomplete="current-password"
                    placeholder="••••••••"
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:0.8rem; margin-top:0.5rem;">
                Ingresar al Panel
            </button>
        </form>

        <div style="margin-top:1.5rem; text-align:center;">
            <a href="{{ route('student.login') }}" style="color:var(--color-accent); font-size:0.85rem; text-decoration:none; font-weight:600;">
                ← Volver al ingreso de alumnos
            </a>
        </div>
    </div>
</div>
@endsection
