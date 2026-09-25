<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AVILA CALL — Clases de Trading en Vivo')</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <header class="app-header">
        <a href="{{ route('student.login') }}" class="brand-link">
            <span>AVILA CALL</span>
            <span class="brand-badge">TRADING LIVE</span>
        </a>

        <div class="header-actions">
            @auth
                @if(request()->routeIs('admin.*') || request()->routeIs('host.*'))
                    <a href="{{ route('admin.classes.index') }}" class="btn btn-secondary btn-sm">Mis Clases</a>
                    <form action="{{ route('host.logout') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">Cerrar Sesión</button>
                    </form>
                @endif
            @endauth

            <button type="button" class="theme-toggle-btn" aria-label="Cambiar a modo Oscuro" aria-pressed="false">
                <span class="theme-icon">🌙</span>
                <span class="theme-text">Modo Oscuro</span>
            </button>
        </div>
    </header>

    <main style="flex:1; display:flex; flex-direction:column;">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
