@extends('layouts.app')

@section('title', $liveClass->title . ' — AVILA CALL')

@section('content')
<div style="max-width:1100px; width:100%; margin:0 auto; padding:2rem 1.5rem;">
    <!-- Back breadcrumb -->
    <div style="margin-bottom:1rem;">
        <a href="{{ route('admin.classes.index') }}" style="color:var(--text-secondary); text-decoration:none; font-size:0.85rem; display:inline-flex; align-items:center; gap:0.3rem;">
            ← Volver a lista de clases
        </a>
    </div>

    <!-- Header & Status -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
            <div>
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.4rem;">
                    <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary);">{{ $liveClass->title }}</h1>
                    @if($liveClass->status === 'live')
                        <span class="badge badge-live">En Vivo</span>
                    @elseif($liveClass->status === 'scheduled')
                        <span class="badge badge-scheduled">Programada</span>
                    @else
                        <span class="badge badge-ended">Finalizada</span>
                    @endif
                </div>
                <p style="color:var(--text-secondary); font-size:0.9rem;">
                    {{ $liveClass->description ?: 'Sin descripción detallada.' }}
                </p>
                <div style="margin-top:0.75rem; font-size:0.82rem; color:var(--text-muted); display:flex; gap:1.5rem; flex-wrap:wrap;">
                    <span>👨‍🏫 Anfitrión: <strong>{{ $liveClass->host->name }}</strong></span>
                    <span>📡 Proveedor: <code>{{ $liveClass->media_provider }}</code></span>
                    <span>🛡️ Grabación: <strong>Desactivada (Solo en Vivo)</strong></span>
                </div>
            </div>

            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a href="{{ route('host.studio', $liveClass->id) }}" class="btn btn-primary">
                    🎥 Ir al Estudio de Transmisión
                </a>

                @if($liveClass->status === 'scheduled')
                    <form action="{{ route('admin.classes.start', $liveClass->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Iniciar Clase</button>
                    </form>
                @elseif($liveClass->status === 'live')
                    <form action="{{ route('admin.classes.end', $liveClass->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas finalizar la clase? Esto cerrará todas las sesiones activas.')">
                        @csrf
                        <button type="submit" class="btn btn-danger">Finalizar Clase</button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Metric counters -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid var(--border-subtle);">
            <div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Conectados Ahora</div>
                <div style="font-size:1.6rem; font-weight:800; color:var(--color-accent);">{{ $connectedCount }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Códigos Disponibles</div>
                <div style="font-size:1.6rem; font-weight:800; color:var(--text-primary);">{{ $availableCount }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Revocados / Bloqueados</div>
                <div style="font-size:1.6rem; font-weight:800; color:var(--status-danger-text);">{{ $revokedCount }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Pico de Asistentes</div>
                <div style="font-size:1.6rem; font-weight:800; color:var(--text-secondary);">{{ $liveClass->peak_viewers_count }}</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <span>✓</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Alert for Newly Generated Raw Codes (shown once for security) -->
    @if(session('generated_codes'))
        <div class="card" style="margin-bottom:1.5rem; border-left:4px solid var(--color-accent); background-color:var(--bg-surface-elevated);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <h3 style="font-size:1.1rem; font-weight:700; color:var(--text-primary);">
                    🎉 Códigos Generados (Copia y entrega a tus alumnos)
                </h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('codes-raw-area').value); alert('Códigos copiados al portapapeles');">
                    Copiar Todos
                </button>
            </div>
            <p style="font-size:0.82rem; color:var(--text-secondary); margin-bottom:0.75rem;">
                Por seguridad, los códigos completos no se guardan en texto plano en la base de datos (se usa hash criptográfico SHA-256). Asegúrate de copiarlos ahora.
            </p>
            <textarea id="codes-raw-area" class="form-textarea" rows="5" readonly style="font-family:monospace; font-size:0.85rem;">@foreach(session('generated_codes') as $gc){{ $gc['student_identifier'] }}: {{ $gc['raw_code'] }} ({{ $gc['role'] }})&#10;@endforeach</textarea>
        </div>
    @endif

    <!-- Codes Management Section -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem;">
            <div>
                <h2 style="font-size:1.25rem; font-weight:800; color:var(--text-primary);">Códigos Individuales de Acceso</h2>
                <p style="color:var(--text-secondary); font-size:0.85rem;">
                    Cada código admite una sola sesión activa simultánea con tolerancia a reconexiones.
                </p>
            </div>

            <!-- Generate Codes Form -->
            <form action="{{ route('admin.classes.codes.generate', $liveClass->id) }}" method="POST" style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                @csrf
                <input type="number" name="quantity" min="1" max="1000" value="5" class="form-input" style="width:75px; padding:0.5rem;" title="Cantidad de códigos">
                <input type="text" name="prefix_label" placeholder="Nombre / Alias" class="form-input" style="width:140px; padding:0.5rem;">
                <select name="role" class="form-select" style="width:120px; padding:0.5rem;">
                    <option value="student">Alumno</option>
                    <option value="moderator">Moderador</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:0.55rem 1rem;">
                    ＋ Generar Códigos
                </button>
            </form>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.88rem;">
                <thead>
                    <tr style="background-color:var(--bg-surface-muted); border-bottom:1px solid var(--border-subtle); color:var(--text-secondary); font-size:0.75rem; text-transform:uppercase;">
                        <th style="padding:0.75rem 1rem;">Alumno / Identificador</th>
                        <th style="padding:0.75rem 1rem;">Prefijo Hash</th>
                        <th style="padding:0.75rem 1rem;">Rol</th>
                        <th style="padding:0.75rem 1rem;">Estado</th>
                        <th style="padding:0.75rem 1rem;">Último Uso</th>
                        <th style="padding:0.75rem 1rem; text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($liveClass->accessCodes as $code)
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:0.75rem 1rem; font-weight:600; color:var(--text-primary);">
                                {{ $code->student_identifier ?: 'Sin alias' }}
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                <code>{{ $code->code_prefix }}</code>
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                @if($code->role === 'moderator')
                                    <span class="badge badge-role-mod">Moderador</span>
                                @else
                                    <span class="badge badge-role-student">Alumno</span>
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                @if($code->status === 'connected')
                                    <span class="badge badge-live">Conectado</span>
                                @elseif($code->status === 'available')
                                    <span class="badge badge-scheduled">Disponible</span>
                                @elseif($code->status === 'banned')
                                    <span class="badge" style="background-color:var(--status-danger-bg); color:var(--status-danger-text);">Bloqueado</span>
                                @else
                                    <span class="badge badge-ended">Revocado</span>
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem; color:var(--text-muted); font-size:0.8rem;">
                                {{ $code->last_used_at ? $code->last_used_at->format('d/m H:i') : 'Nunca' }}
                            </td>
                            <td style="padding:0.75rem 1rem; text-align:right;">
                                @if($code->status !== 'revoked' && $code->status !== 'banned')
                                    <form action="{{ route('admin.classes.codes.revoke', [$liveClass->id, $code->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('¿Revocar este código? El alumno perderá acceso inmediato.')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding:2px 8px; font-size:0.75rem;">
                                            Revocar
                                        </button>
                                    </form>
                                @else
                                    <span style="color:var(--text-muted); font-size:0.75rem;">Inactivo</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:2rem; text-align:center; color:var(--text-muted);">
                                No se han generado códigos para esta clase aún. Usa el formulario superior para generar códigos individuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Moderation Logs Section -->
    <div class="card">
        <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-primary); margin-bottom:1rem;">
            Registro de Moderación y Auditoría
        </h3>
        @if($moderationLogs->isEmpty())
            <p style="color:var(--text-muted); font-size:0.85rem;">No se han registrado acciones de moderación.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.82rem; text-align:left;">
                    <thead>
                        <tr style="background-color:var(--bg-surface-muted); color:var(--text-secondary);">
                            <th style="padding:0.5rem 0.75rem;">Hora</th>
                            <th style="padding:0.5rem 0.75rem;">Moderador / Actor</th>
                            <th style="padding:0.5rem 0.75rem;">Acción</th>
                            <th style="padding:0.5rem 0.75rem;">Objetivo</th>
                            <th style="padding:0.5rem 0.75rem;">Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($moderationLogs as $log)
                            <tr style="border-bottom:1px solid var(--border-subtle);">
                                <td style="padding:0.5rem 0.75rem; color:var(--text-muted);">{{ $log->created_at->format('H:i:s') }}</td>
                                <td style="padding:0.5rem 0.75rem; font-weight:600;">{{ $log->actor_name }}</td>
                                <td style="padding:0.5rem 0.75rem;"><code>{{ $log->action }}</code></td>
                                <td style="padding:0.5rem 0.75rem;">{{ $log->target_name ?: '-' }}</td>
                                <td style="padding:0.5rem 0.75rem; color:var(--text-secondary);">{{ $log->details }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
