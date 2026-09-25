@extends('layouts.app')

@section('title', 'Ingreso a Clase — AVILA CALL')

@section('content')
<div style="flex:1; display:flex; align-items:center; justify-content:center; padding:2rem 1rem;">
    <div class="card" style="max-width:480px; width:100%;">
        <div style="text-align:center; margin-bottom:1.75rem;">
            <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-primary); margin-bottom:0.4rem;">
                Ingresar a la Clase
            </h1>
            <p style="font-size:0.92rem; color:var(--text-secondary);">
                Introduce tu código individual para acceder a la transmisión en vivo.
            </p>
        </div>

        @if(session('error'))
            <div class="alert alert-error">
                <span>⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info">
                <span>ℹ️</span>
                <span>{{ session('info') }}</span>
            </div>
        @endif

        <form action="{{ route('student.auth') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="access_code" class="form-label">Código de Acceso Individual</label>
                <input 
                    type="text" 
                    id="access_code" 
                    name="access_code" 
                    class="form-input" 
                    placeholder="Ej. AC-XXXX-XXXX-XXXX" 
                    required 
                    autocomplete="off"
                    autocapitalize="characters"
                    style="font-family:monospace; font-size:1.1rem; letter-spacing:0.08em; text-align:center;"
                    value="{{ old('access_code') }}"
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:0.85rem; font-size:1rem; margin-top:0.5rem;">
                Entrar a la Clase en Vivo
            </button>
        </form>

        <div style="margin-top:1.75rem; padding-top:1.25rem; border-top:1px solid var(--border-subtle); font-size:0.82rem; color:var(--text-muted); line-height:1.45;">
            <p style="margin-bottom:0.6rem;">
                🔒 <strong>Condiciones de la clase en vivo:</strong>
            </p>
            <ul style="padding-left:1.25rem; display:flex; flex-direction:column; gap:0.35rem;">
                <li>El anfitrión transmite su pantalla y voz en directo.</li>
                <li><strong>No hay repeticiones ni grabaciones:</strong> el contenido solo se emite en vivo.</li>
                <li><strong>Dispositivo único:</strong> cada código admite una sola sesión activa. Si ingresas desde otro dispositivo, la sesión anterior se cerrará de inmediato.</li>
                <li>Los alumnos participan por chat cuando el anfitrión lo autorice; no se transmite cámara ni micrófono de alumnos.</li>
            </ul>
        </div>

        <div style="margin-top:1.25rem; text-align:center;">
            <a href="{{ route('host.login') }}" style="color:var(--text-secondary); font-size:0.82rem; text-decoration:none;">
                ¿Eres anfitrión o profesor? Inicia sesión aquí
            </a>
        </div>
    </div>
</div>
@endsection
