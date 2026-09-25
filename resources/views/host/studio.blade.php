@extends('layouts.app')

@section('title', 'Estudio de Transmisión — ' . $liveClass->title)

@section('content')
<div class="room-container">
    <!-- Main Video Stage (Host Screen Broadcast) -->
    <div class="video-stage">
        <!-- Overlay Header -->
        <div class="video-stage-header">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="badge {{ $liveClass->status === 'live' ? 'badge-live' : 'badge-scheduled' }}" id="host-class-status-badge">
                    {{ $liveClass->status === 'live' ? 'En Vivo' : 'Programada' }}
                </span>
                <span style="font-weight:700; font-size:1.05rem;">{{ $liveClass->title }}</span>
            </div>

            <div style="display:flex; align-items:center; gap:1rem;">
                <span style="font-size:0.85rem; color:#A0B2C4;">
                    👥 Asistentes: <strong id="host-viewers-count" style="color:var(--color-accent);">{{ $liveClass->current_viewers_count }}</strong>
                </span>

                <a href="{{ route('admin.classes.show', $liveClass->id) }}" class="btn btn-secondary btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);">
                    ⚙️ Códigos & Ajustes
                </a>
            </div>
        </div>

        <!-- Video Stage Center -->
        <div class="video-canvas-wrapper">
            <video id="host-video-preview" class="video-player" playsinline autoplay muted></video>
            
            <div id="video-placeholder" class="video-placeholder">
                <div style="font-size:3rem; margin-bottom:1rem;">🖥️ ＋ 🎙️</div>
                <h3 style="font-size:1.3rem; margin-bottom:0.5rem; color:#FFFFFF;">Transmisión de Pantalla y Voz</h3>
                <p style="max-width:440px; font-size:0.9rem; line-height:1.45; margin-bottom:1.5rem;">
                    Por especificación de AVILA CALL, solo se transmite la pantalla y tu voz. Tu cámara web está desactivada por defecto y no se almacena ninguna grabación.
                </p>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; justify-content:center;">
                    <button type="button" id="start-screen-btn" class="btn btn-primary" style="padding:0.8rem 1.4rem; font-size:0.95rem;">
                        🖥️ Compartir Pantalla Real + Voz
                    </button>
                    @if($mediaConfig['provider'] === 'local_webrtc')
                    <button type="button" id="start-demo-btn" class="btn btn-secondary" style="padding:0.8rem 1.2rem; font-size:0.9rem; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.25);">
                        📊 Probar con Gráficos Simulados
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Control Bar Bottom -->
        <div style="background-color:rgba(16, 36, 58, 0.95); backdrop-filter:blur(8px); padding:0.85rem 1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; border-top:1px solid rgba(255,255,255,0.1); z-index:20;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <button type="button" id="stop-screen-btn" class="btn btn-secondary btn-sm" style="display:none;">
                    ⏹️ Detener Compartición
                </button>
                <span id="screen-status-indicator" style="font-size:0.85rem; color:#A0B2C4;">
                    Pantalla no compartida
                </span>
            </div>

            <div style="display:flex; align-items:center; gap:1rem;">
                <!-- Chat Toggle for Host -->
                <div style="display:flex; align-items:center; gap:0.5rem; background:rgba(255,255,255,0.08); padding:0.4rem 0.8rem; border-radius:var(--radius-full);">
                    <span style="font-size:0.8rem; color:#E2E8F0; font-weight:600;">Chat:</span>
                    <button type="button" id="toggle-chat-btn" class="btn btn-sm {{ $liveClass->chat_enabled ? 'btn-primary' : 'btn-secondary' }}" style="padding:2px 10px; font-size:0.75rem;">
                        {{ $liveClass->chat_enabled ? 'Abierto' : 'Cerrado' }}
                    </button>
                </div>

                @if($liveClass->status !== 'live')
                    <form action="{{ route('admin.classes.start', $liveClass->id) }}" method="POST" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            🔴 Iniciar Clase en Vivo
                        </button>
                    </form>
                @else
                    <form action="{{ route('admin.classes.end', $liveClass->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('¿Finalizar clase? Se cerrará la sesión de todos los alumnos.')">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">
                            ⏹️ Finalizar Clase
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Chat & Moderation Sidebar -->
    <aside class="chat-sidebar">
        <div class="chat-header">
            <div>
                <h2 style="font-size:1.05rem; font-weight:800; color:var(--text-primary);">Chat en Vivo</h2>
                <span style="font-size:0.75rem; color:var(--text-muted);" id="chat-status-text">
                    {{ $liveClass->chat_enabled ? 'Abierto para alumnos' : 'Cerrado para alumnos' }}
                </span>
            </div>
            <span class="badge badge-role-host">Profesor</span>
        </div>

        <div id="chat-messages-container" class="chat-messages">
            <!-- Messages rendered dynamically via JS -->
        </div>

        <div class="chat-input-bar">
            <form id="chat-form" style="display:flex; gap:0.5rem;">
                <input 
                    type="text" 
                    id="chat-input" 
                    class="form-input" 
                    placeholder="Escribe como profesor/anfitrión..." 
                    maxlength="350"
                    autocomplete="off"
                    style="flex:1; padding:0.6rem 0.8rem; font-size:0.9rem;"
                >
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1rem;">
                    Enviar
                </button>
            </form>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const { LocalMediaBroadcaster, LiveKitMediaBroadcaster, LiveChatClient } = window.AvilaCall || {};
        const classId = {{ $liveClass->id }};
        const mediaConfig = @json($mediaConfig);
        const videoEl = document.getElementById('host-video-preview');
        const placeholderEl = document.getElementById('video-placeholder');
        const startScreenBtn = document.getElementById('start-screen-btn');
        const stopScreenBtn = document.getElementById('stop-screen-btn');
        const screenStatusText = document.getElementById('screen-status-indicator');

        const resetStreamUi = () => {
            placeholderEl.style.display = 'flex';
            videoEl.style.display = 'none';
            stopScreenBtn.style.display = 'none';
            screenStatusText.textContent = 'Pantalla no compartida';
            screenStatusText.style.color = '#A0B2C4';
        };
        const broadcaster = mediaConfig.provider === 'livekit_sfu'
            ? new LiveKitMediaBroadcaster(videoEl, mediaConfig, { onStreamInactive: resetStreamUi })
            : new LocalMediaBroadcaster(videoEl, classId, { onStreamInactive: resetStreamUi });

        startScreenBtn.addEventListener('click', async () => {
            try {
                await broadcaster.startScreenAndAudio();
                placeholderEl.style.display = 'none';
                videoEl.style.display = 'block';
                stopScreenBtn.style.display = 'inline-flex';
                screenStatusText.textContent = '🟢 Compartiendo pantalla y voz';
                screenStatusText.style.color = '#34D399';
            } catch (err) {
                alert('No se pudo iniciar la compartición de pantalla: ' + (err.message || 'Permiso denegado'));
            }
        });

        const startDemoBtn = document.getElementById('start-demo-btn');
        if (startDemoBtn) {
            startDemoBtn.addEventListener('click', () => {
                broadcaster.startDemoTradingStream();
                placeholderEl.style.display = 'none';
                videoEl.style.display = 'block';
                stopScreenBtn.style.display = 'inline-flex';
                screenStatusText.textContent = '🟢 Emitiendo gráficos de trading en vivo';
                screenStatusText.style.color = '#34D399';
            });
        }

        stopScreenBtn.addEventListener('click', async () => {
            await broadcaster.stop();
            resetStreamUi();
        });

        window.addEventListener('beforeunload', () => broadcaster.destroy());

        // Initialize Chat Client for Host
        const chatClient = new LiveChatClient({
            classId: classId,
            userRole: 'host',
            containerEl: document.getElementById('chat-messages-container'),
            inputEl: document.getElementById('chat-input'),
            formEl: document.getElementById('chat-form'),
        });

        // Toggle Chat State Button
        const toggleChatBtn = document.getElementById('toggle-chat-btn');
        const chatStatusText = document.getElementById('chat-status-text');
        let isChatOpen = {{ $liveClass->chat_enabled ? 'true' : 'false' }};

        toggleChatBtn.addEventListener('click', async () => {
            const newState = !isChatOpen;
            try {
                const res = await fetch(`/api/class/${classId}/chat/toggle`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ enabled: newState }),
                });

                if (res.ok) {
                    isChatOpen = newState;
                    toggleChatBtn.textContent = isChatOpen ? 'Abierto' : 'Cerrado';
                    toggleChatBtn.className = isChatOpen ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary';
                    chatStatusText.textContent = isChatOpen ? 'Abierto para alumnos' : 'Cerrado para alumnos';
                }
            } catch (e) {
                alert('Error al cambiar estado del chat');
            }
        });
    });
</script>
@endpush
