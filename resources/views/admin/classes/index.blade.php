@extends('layouts.app')

@section('title', 'Gestión de Clases — AVILA CALL')

@section('content')
<div style="max-width:1100px; width:100%; margin:0 auto; padding:2rem 1.5rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <h1 style="font-size:1.8rem; font-weight:800; color:var(--text-primary); margin-bottom:0.25rem;">
                Clases de Trading en Vivo
            </h1>
            <p style="color:var(--text-secondary); font-size:0.95rem;">
                Administra tus transmisiones, genera códigos individuales y gestiona accesos.
            </p>
        </div>

        <button type="button" class="btn btn-primary" onclick="document.getElementById('new-class-modal').style.display='flex'">
            <span>＋</span> Nueva Clase
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info">
            <span>ℹ️</span>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <div class="card" style="padding:0; overflow:hidden;">
        @if($classes->isEmpty())
            <div style="text-align:center; padding:3rem 1.5rem; color:var(--text-muted);">
                <p style="font-size:1.1rem; margin-bottom:1rem;">No tienes clases creadas aún.</p>
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('new-class-modal').style.display='flex'">
                    Crear primera clase
                </button>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left;">
                    <thead>
                        <tr style="background-color:var(--bg-surface-muted); border-bottom:1px solid var(--border-subtle); color:var(--text-secondary); font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em;">
                            <th style="padding:1rem 1.25rem;">Título</th>
                            <th style="padding:1rem 1.25rem;">Estado</th>
                            <th style="padding:1rem 1.25rem;">Espectadores</th>
                            <th style="padding:1rem 1.25rem;">Proveedor Medios</th>
                            <th style="padding:1rem 1.25rem;">Fecha Creación</th>
                            <th style="padding:1rem 1.25rem; text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($classes as $c)
                            <tr style="border-bottom:1px solid var(--border-subtle);">
                                <td style="padding:1rem 1.25rem;">
                                    <div style="font-weight:700; color:var(--text-primary);">{{ $c->title }}</div>
                                    @if($c->description)
                                        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">{{ Str::limit($c->description, 60) }}</div>
                                    @endif
                                </td>
                                <td style="padding:1rem 1.25rem;">
                                    @if($c->status === 'live')
                                        <span class="badge badge-live">En Vivo</span>
                                    @elseif($c->status === 'scheduled')
                                        <span class="badge badge-scheduled">Programada</span>
                                    @else
                                        <span class="badge badge-ended">Finalizada</span>
                                    @endif
                                </td>
                                <td style="padding:1rem 1.25rem; font-size:0.9rem;">
                                    <span style="font-weight:700; color:var(--color-accent);">{{ $c->current_viewers_count }}</span>
                                    <span style="color:var(--text-muted); font-size:0.8rem;">(Pico: {{ $c->peak_viewers_count }})</span>
                                </td>
                                <td style="padding:1rem 1.25rem; font-size:0.85rem; color:var(--text-secondary);">
                                    <code>{{ $c->media_provider }}</code>
                                </td>
                                <td style="padding:1rem 1.25rem; font-size:0.85rem; color:var(--text-muted);">
                                    {{ $c->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td style="padding:1rem 1.25rem; text-align:right;">
                                    <div style="display:inline-flex; gap:0.5rem;">
                                        <a href="{{ route('host.studio', $c->id) }}" class="btn btn-primary btn-sm">
                                            Estudio
                                        </a>
                                        <a href="{{ route('admin.classes.show', $c->id) }}" class="btn btn-secondary btn-sm">
                                            Códigos
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($classes->hasPages())
                <div style="padding:1rem 1.25rem; border-top:1px solid var(--border-subtle);">
                    {{ $classes->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

<!-- Modal: Nueva Clase -->
<div id="new-class-modal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h2 style="font-size:1.3rem; font-weight:800; color:var(--text-primary);">Crear Nueva Clase</h2>
            <button type="button" onclick="document.getElementById('new-class-modal').style.display='none'" style="background:none; border:none; font-size:1.3rem; cursor:pointer; color:var(--text-muted);">✕</button>
        </div>

        <form action="{{ route('admin.classes.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="title" class="form-label">Título de la Clase *</label>
                <input type="text" id="title" name="title" class="form-input" required placeholder="Ej. Análisis Técnico Diario - Forex y Futuros">
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Descripción / Temario (opcional)</label>
                <textarea id="description" name="description" class="form-textarea" rows="3" placeholder="Resumen de lo que se verá en la sesión..."></textarea>
            </div>

            <div class="form-group">
                <label for="media_provider" class="form-label">Adaptador de Transmisión</label>
                <select id="media_provider" name="media_provider" class="form-select">
                    <option value="local_webrtc">Local WebRTC (Pruebas / Demo Local)</option>
                    <option value="livekit_sfu" {{ $liveKitConfigured ? '' : 'disabled' }}>
                        LiveKit SFU (Producción; requiere despliegue y prueba de carga){{ $liveKitConfigured ? '' : ' - No configurado' }}
                    </option>
                </select>
                <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">
                    WebRTC local es solo para desarrollo. La capacidad superior a 1.000 asistentes no se afirma sin una prueba real del SFU elegido.
                </small>
                @error('media_provider')
                    <small style="color:var(--color-danger); display:block; margin-top:4px;">{{ $message }}</small>
                @enderror
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('new-class-modal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Clase</button>
            </div>
        </form>
    </div>
</div>
@endsection
