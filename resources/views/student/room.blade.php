@extends('layouts.app')

@section('title', $liveClass->title . ' — En Vivo AVILA CALL')

@section('content')
<div class="room-container">
    <!-- Student Stream Viewing Stage -->
    <div class="video-stage">
        <!-- Stage Header Overlay -->
        <div class="video-stage-header">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="badge {{ $liveClass->status === 'live' ? 'badge-live' : 'badge-scheduled' }}" id="student-class-status-badge">
                    {{ $liveClass->status === 'live' ? 'En Vivo' : 'En Espera' }}
                </span>
                <span style="font-weight:700; font-size:1rem;">{{ $liveClass->title }}</span>
            </div>

            <div style="display:flex; align-items:center; gap:1rem;">
                <span style="font-size:0.82rem; color:#A0B2C4;">
                    Profesor: <strong>{{ $liveClass->host->name }}</strong>
                </span>

                <button type="button" id="logout-student-btn" class="btn btn-secondary btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);">
                    Salir de la Clase
                </button>
            </div>
        </div>

        <!-- Video Player / Stage Display -->
        <div class="video-canvas-wrapper" id="player-container" style="position:relative;">
            <video id="student-video-player" class="video-player" playsinline autoplay muted controls style="display:none;"></video>

            <!-- Unmute Audio Button Overlay -->
            <button id="unmute-stream-btn" type="button" class="btn btn-secondary btn-sm" style="display:none; position:absolute; bottom:20px; left:20px; z-index:30; background:rgba(16,36,58,0.92); color:#20B8A5; border:1px solid #20B8A5; backdrop-filter:blur(6px); font-weight:700; border-radius:6px; padding:0.5rem 0.9rem; cursor:pointer; align-items:center; gap:0.5rem; box-shadow:0 4px 16px rgba(0,0,0,0.5);">
                <span>🔊</span>
                <span>Activar Audio</span>
            </button>

            <div id="student-waiting-placeholder" class="video-placeholder">
                <div style="font-size:3rem; margin-bottom:1rem;">📡</div>
                <h3 style="font-size:1.3rem; margin-bottom:0.4rem; color:#FFFFFF;">
                    @if($liveClass->status === 'live')
                        Conectando a la Transmisión en Vivo...
                    @else
                        Esperando que el profesor inicie la clase...
                    @endif
                </h3>
                <p style="font-size:0.88rem; color:#94A3B8; max-width:420px; line-height:1.45;">
                    La transmisión de pantalla y voz comenzará en directo en esta ventana. Recuerda que no habrá grabaciones posteriores.
                </p>
            </div>
        </div>

        <!-- Notification Bar (Notice of single session & no recording) -->
        <div style="background-color:rgba(16, 36, 58, 0.95); backdrop-filter:blur(8px); padding:0.6rem 1.25rem; display:flex; align-items:center; justify-content:space-between; font-size:0.8rem; color:#A0B2C4; border-top:1px solid rgba(255,255,255,0.1); z-index:20;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <span>🔒 Sesión activa: <strong>{{ $accessCode->student_identifier ?: 'Alumno' }}</strong></span>
                @if($accessCode->role === 'moderator')
                    <span class="badge badge-role-mod">Moderador</span>
                @endif
            </div>
            <div>
                <span>Solo en vivo • Sin repeticiones</span>
            </div>
        </div>
    </div>

    <!-- Live Chat Sidebar -->
    <aside class="chat-sidebar">
        <div class="chat-header">
            <div>
                <h2 style="font-size:1.05rem; font-weight:800; color:var(--text-primary);">Chat de la Clase</h2>
                <span id="chat-header-status" style="font-size:0.75rem; color:var(--text-muted);">
                    {{ $liveClass->chat_enabled ? 'Habilitado' : 'Cerrado temporalmente' }}
                </span>
            </div>

            <div>
                @if($accessCode->role === 'moderator')
                    <span class="badge badge-role-mod">Moderador</span>
                @else
                    <span class="badge badge-role-student">Alumno</span>
                @endif
            </div>
        </div>

        <div id="chat-messages-container" class="chat-messages">
            <!-- Messages rendered dynamically via JS -->
        </div>

        <!-- Chat Disabled Banner -->
        <div id="chat-disabled-banner" class="chat-disabled-banner" style="margin:0.5rem 1rem; {{ $liveClass->chat_enabled ? 'display:none;' : '' }}">
            <span>🔒</span>
            <span>El anfitrión ha cerrado el chat en este momento.</span>
        </div>

        <!-- Chat Input Form -->
        <div class="chat-input-bar">
            <form id="chat-form" style="display:flex; gap:0.5rem;">
                <input 
                    type="text" 
                    id="chat-input" 
                    class="form-input" 
                    placeholder="{{ $liveClass->chat_enabled ? 'Escribe una pregunta...' : 'Chat cerrado temporalmente' }}"
                    maxlength="350"
                    autocomplete="off"
                    {{ $liveClass->chat_enabled ? '' : 'disabled' }}
                    style="flex:1; padding:0.6rem 0.8rem; font-size:0.9rem;"
                >
                <button type="submit" id="chat-send-btn" class="btn btn-primary" style="padding:0.6rem 1rem;">
                    Enviar
                </button>
            </form>
        </div>
    </aside>
</div>

<!-- Modal: Session Replaced or Kicked (Teardown overlay) -->
<div id="session-terminated-modal" class="modal-overlay" style="display:none;">
    <div class="modal-card" style="text-align:center;">
        <div style="font-size:3rem; margin-bottom:1rem;">⚠️</div>
        <h2 style="font-size:1.4rem; font-weight:800; color:var(--text-primary); margin-bottom:0.75rem;" id="modal-term-title">
            Sesión Finalizada
        </h2>
        <p style="font-size:0.95rem; color:var(--text-secondary); line-height:1.5; margin-bottom:1.5rem;" id="modal-term-message">
            Tu sesión se cerró porque este código ingresó desde otro dispositivo.
        </p>
        <a href="{{ route('student.login') }}" class="btn btn-primary" style="width:100%; padding:0.75rem;">
            Entendido / Volver al Inicio
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const { SessionHeartbeatClient, LiveChatClient, LocalMediaSubscriber, LiveKitMediaSubscriber } = window.AvilaCall || {};
        const classId = {{ $liveClass->id }};
        const sessionToken = @json($sessionToken);
        const userRole = @json($accessCode->role);
        const mediaConfig = @json($mediaConfig);

        const videoEl = document.getElementById('student-video-player');
        const placeholderEl = document.getElementById('student-waiting-placeholder');
        const termModal = document.getElementById('session-terminated-modal');
        const modalTitle = document.getElementById('modal-term-title');
        const modalMsg = document.getElementById('modal-term-message');
        const chatHeaderStatus = document.getElementById('chat-header-status');
        const chatBanner = document.getElementById('chat-disabled-banner');
        const chatInput = document.getElementById('chat-input');
        const unmuteBtn = document.getElementById('unmute-stream-btn');

        if (unmuteBtn) {
            unmuteBtn.addEventListener('click', () => {
                if (videoEl) {
                    videoEl.muted = false;
                    if (typeof mediaSubscriber.resumeAudio === 'function') {
                        mediaSubscriber.resumeAudio().catch(() => {});
                    }
                    unmuteBtn.style.display = 'none';
                }
            });
        }

        if (videoEl) {
            videoEl.addEventListener('volumechange', () => {
                if (unmuteBtn && !videoEl.muted) {
                    unmuteBtn.style.display = 'none';
                }
            });
        }

        // 1. Initialize Media Subscriber
        const SubscriberClass = mediaConfig.provider === 'livekit_sfu'
            ? LiveKitMediaSubscriber
            : LocalMediaSubscriber;
        const mediaSubscriber = new SubscriberClass(videoEl, mediaConfig.provider === 'livekit_sfu' ? mediaConfig : classId, {
            sessionToken: sessionToken,
            peerId: @json('student_'.$session->id),
            onStreamActive: (stream) => {
                if (placeholderEl) placeholderEl.style.display = 'none';
                if (videoEl) videoEl.style.display = 'block';
                if (unmuteBtn && videoEl && videoEl.muted) {
                    unmuteBtn.style.display = 'inline-flex';
                }
                const badge = document.getElementById('student-class-status-badge');
                if (badge) {
                    badge.textContent = 'En Vivo';
                    badge.className = 'badge badge-live';
                }
            },
            onStreamInactive: () => {
                if (placeholderEl) placeholderEl.style.display = 'flex';
                if (videoEl) videoEl.style.display = 'none';
                if (unmuteBtn) unmuteBtn.style.display = 'none';
                const badge = document.getElementById('student-class-status-badge');
                if (badge) {
                    badge.textContent = 'En Espera';
                    badge.className = 'badge badge-scheduled';
                }
            }
        });

        // 2. Initialize Real-Time Chat
        const chatClient = new LiveChatClient({
            classId: classId,
            sessionToken: sessionToken,
            userRole: userRole,
            containerEl: document.getElementById('chat-messages-container'),
            inputEl: chatInput,
            formEl: document.getElementById('chat-form'),
            statusBannerEl: chatBanner,
        });

        // 3. Initialize Heartbeat Lease Client (Atomic Exclusivity Enforcer)
        const heartbeatClient = new SessionHeartbeatClient({
            classId: classId,
            sessionToken: sessionToken,
            intervalMs: 10000,
            onRevoked: (data) => {
                // IMMEDIATE TEARDOWN: Student loses access to stream and chat immediately
                if (videoEl) {
                    videoEl.pause();
                    videoEl.srcObject = null;
                    videoEl.remove();
                }
                chatClient.destroy();
                mediaSubscriber.destroy();

                modalTitle.textContent = 'Acceso Revocado';
                modalMsg.textContent = data.message || 'Tu sesión ha sido cerrada.';
                termModal.style.display = 'flex';
            },
            onStatusUpdate: (data) => {
                // Update chat state in real-time
                if (data.chat_enabled !== undefined) {
                    chatClient.updateChatState(data.chat_enabled);
                    chatHeaderStatus.textContent = data.chat_enabled ? 'Habilitado' : 'Cerrado temporalmente';
                }
                if (data.class_status === 'ended') {
                    heartbeatClient.stop();
                    alert('La clase ha finalizado.');
                    window.location.href = '/';
                }
            }
        });

        // 4. Student Logout (JS-driven to avoid stale CSRF tokens → 419 errors)
        const logoutBtn = document.getElementById('logout-student-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                try {
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
                    const res = await fetch('{{ route("student.logout") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Session-Token': sessionToken,
                        },
                        body: JSON.stringify({ session_token: sessionToken }),
                    });
                    // Regardless of response, redirect to login
                    window.location.href = '/';
                } catch (e) {
                    window.location.href = '/';
                }
            });
        }

        heartbeatClient.start();
    });
</script>
@endpush
