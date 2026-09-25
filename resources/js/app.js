/**
 * AVILA CALL - Core Client Application
 * HTML5, CSS3 & JavaScript for Live Trading Broadcasts & Real-Time Chat
 */

let liveKitModulePromise = null;

function loadLiveKit() {
    liveKitModulePromise ??= import('livekit-client');
    return liveKitModulePromise;
}

// 1. Accessible Theme Manager (Claro / Oscuro)
export const ThemeManager = {
    STORAGE_KEY: 'avila_theme_preference',

    init() {
        const savedTheme = localStorage.getItem(this.STORAGE_KEY);
        if (savedTheme === 'dark' || savedTheme === 'light') {
            this.setTheme(savedTheme, false);
        } else {
            // Respect operating system preference initially
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.setTheme(prefersDark ? 'dark' : 'light', false);
        }

        // Listen for OS theme changes if user hasn't chosen manually
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem(this.STORAGE_KEY)) {
                    this.setTheme(e.matches ? 'dark' : 'light', false);
                }
            });
        }

        // Attach click listeners to all theme toggle buttons
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => this.toggleTheme());
        });
    },

    getTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    },

    setTheme(theme, save = true) {
        document.documentElement.setAttribute('data-theme', theme);
        if (save) {
            localStorage.setItem(this.STORAGE_KEY, theme);
        }
        this.updateButtons(theme);
    },

    toggleTheme() {
        const nextTheme = this.getTheme() === 'dark' ? 'light' : 'dark';
        this.setTheme(nextTheme, true);
    },

    updateButtons(theme) {
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            const isDark = theme === 'dark';
            btn.setAttribute('aria-label', isDark ? 'Cambiar a modo Claro' : 'Cambiar a modo Oscuro');
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            const icon = btn.querySelector('.theme-icon');
            const text = btn.querySelector('.theme-text');
            if (icon) icon.textContent = isDark ? '☀️' : '🌙';
            if (text) text.textContent = isDark ? 'Modo Claro' : 'Modo Oscuro';
        });
    }
};

// 2. Student Session Heartbeat Client (Atomic Exclusivity & Lease Monitoring)
export class SessionHeartbeatClient {
    constructor(options = {}) {
        this.classId = options.classId;
        this.sessionToken = options.sessionToken;
        this.heartbeatIntervalMs = options.intervalMs || 10000;
        this.onRevoked = options.onRevoked || this.defaultRevokedHandler;
        this.onStatusUpdate = options.onStatusUpdate || (() => {});
        this.timer = null;
    }

    start() {
        if (!this.sessionToken) return;
        this.timer = setInterval(() => this.sendHeartbeat(), this.heartbeatIntervalMs);
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    async sendHeartbeat() {
        try {
            const response = await fetch('/student/heartbeat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                    'X-Session-Token': this.sessionToken,
                },
                body: JSON.stringify({ session_token: this.sessionToken }),
            });

            const data = await response.json();

            if (!response.ok || data.status === 'revoked') {
                this.stop();
                this.onRevoked(data);
                return;
            }

            this.onStatusUpdate(data);
        } catch (err) {
            console.warn('Fallo temporal en heartbeat de red:', err);
        }
    }

    defaultRevokedHandler(data) {
        const msg = data.message || 'Tu sesión ha sido revocada.';
        alert(msg);
        window.location.href = '/';
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
}

// 3. Real-Time Chat Client
export class LiveChatClient {
    constructor(options = {}) {
        this.classId = options.classId;
        this.sessionToken = options.sessionToken || null;
        this.userRole = options.userRole || 'student';
        this.pollIntervalMs = options.pollIntervalMs || 2500;
        this.containerEl = options.containerEl;
        this.inputEl = options.inputEl;
        this.formEl = options.formEl;
        this.statusBannerEl = options.statusBannerEl;
        this.lastMessageId = 0;
        this.pollTimer = null;
        this.isChatOpen = true;

        this.init();
    }

    init() {
        if (this.formEl) {
            this.formEl.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitMessage();
            });
        }

        this.fetchMessages();
        this.pollTimer = setInterval(() => this.fetchMessages(), this.pollIntervalMs);
    }

    destroy() {
        if (this.pollTimer) clearInterval(this.pollTimer);
    }

    async fetchMessages() {
        try {
            const url = `/api/class/${this.classId}/chat/messages${this.lastMessageId ? `?after_id=${this.lastMessageId}` : ''}`;
            const headers = { 'Accept': 'application/json' };
            if (this.sessionToken) headers['X-Session-Token'] = this.sessionToken;

            const res = await fetch(url, { headers });
            if (!res.ok) return;

            const data = await res.json();
            if (data.success) {
                this.updateChatState(data.chat_enabled);
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => this.renderMessage(msg));
                    this.lastMessageId = data.messages[data.messages.length - 1].id;
                    this.scrollToBottom();
                }
            }
        } catch (e) {
            console.warn('Error sincronizando mensajes de chat:', e);
        }
    }

    async submitMessage() {
        if (!this.inputEl) return;
        const text = this.inputEl.value.trim();
        if (!text) return;

        if (!this.isChatOpen && this.userRole === 'student') {
            alert('El chat está cerrado en este momento por el anfitrión.');
            return;
        }

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.getCsrfToken(),
        };
        if (this.sessionToken) headers['X-Session-Token'] = this.sessionToken;

        try {
            this.inputEl.disabled = true;
            const res = await fetch(`/api/class/${this.classId}/chat/messages`, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    message: text,
                    session_token: this.sessionToken,
                }),
            });

            const data = await responseJsonSafe(res);
            if (res.ok && data.success) {
                this.inputEl.value = '';
                this.renderMessage(data.message);
                this.lastMessageId = Math.max(this.lastMessageId, data.message.id);
                this.scrollToBottom();
            } else {
                alert(data.error || 'No se pudo enviar el mensaje.');
            }
        } catch (err) {
            alert('Error de conexión al enviar mensaje.');
        } finally {
            this.inputEl.disabled = false;
            this.inputEl.focus();
        }
    }

    updateChatState(isOpen) {
        this.isChatOpen = isOpen;
        if (this.statusBannerEl) {
            this.statusBannerEl.style.display = isOpen ? 'none' : 'flex';
        }
        if (this.inputEl && this.userRole === 'student') {
            this.inputEl.disabled = !isOpen;
            this.inputEl.placeholder = isOpen ? 'Escribe tu consulta...' : 'Chat cerrado temporalmente por el anfitrión.';
        }
    }

    renderMessage(msg) {
        if (!this.containerEl) return;
        // Avoid duplicate rendering
        if (this.containerEl.querySelector(`[data-msg-id="${msg.id}"]`)) return;

        const item = document.createElement('div');
        item.className = 'chat-msg-item';
        item.setAttribute('data-msg-id', msg.id);

        let badgeHtml = '';
        if (msg.sender_role === 'host') {
            badgeHtml = '<span class="badge badge-role-host">Profesor</span>';
        } else if (msg.sender_role === 'moderator') {
            badgeHtml = '<span class="badge badge-role-mod">Mod</span>';
        }

        let modActionsHtml = '';
        if (this.userRole === 'host' || this.userRole === 'moderator') {
            modActionsHtml = `
                <button type="button" class="btn btn-danger btn-sm delete-msg-btn" data-msg-id="${msg.id}" style="padding:2px 6px; font-size:11px;" title="Eliminar mensaje">✕</button>
            `;
        }

        item.innerHTML = `
            <div class="chat-msg-header">
                <span class="chat-msg-author">
                    ${escapeHtml(msg.sender_name)} ${badgeHtml}
                </span>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span class="chat-msg-time">${msg.created_at}</span>
                    ${modActionsHtml}
                </div>
            </div>
            <div class="chat-msg-text">${escapeHtml(msg.message)}</div>
        `;

        if (modActionsHtml) {
            const delBtn = item.querySelector('.delete-msg-btn');
            if (delBtn) {
                delBtn.addEventListener('click', () => this.deleteMessage(msg.id, item));
            }
        }

        this.containerEl.appendChild(item);
    }

    async deleteMessage(msgId, itemEl) {
        if (!confirm('¿Deseas eliminar este mensaje para toda la clase?')) return;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.getCsrfToken(),
        };
        if (this.sessionToken) headers['X-Session-Token'] = this.sessionToken;

        try {
            const res = await fetch(`/api/class/${this.classId}/chat/delete-message`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ message_id: msgId }),
            });
            if (res.ok) {
                itemEl.remove();
            }
        } catch (e) {
            console.error(e);
        }
    }

    scrollToBottom() {
        if (this.containerEl) {
            this.containerEl.scrollTop = this.containerEl.scrollHeight;
        }
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
}

// Helper functions for SDP and ICE normalization
function normalizeSdp(sdp) {
    if (!sdp || typeof sdp !== 'string') return sdp;
    // Standardize CRLF line endings and strip blank/empty lines (Chrome rejects empty SDP lines)
    const lines = sdp
        .replace(/\r\n/g, '\n')
        .replace(/\r/g, '\n')
        .split('\n')
        .map(l => l.trimEnd())
        .filter(l => l.length > 0);
    return lines.join('\r\n') + '\r\n';
}

function createSessionDescription(payload) {
    if (!payload) return null;
    let type = payload.type;
    let sdp = payload.sdp;
    if (typeof payload === 'string') {
        try {
            const parsed = JSON.parse(payload);
            type = parsed.type;
            sdp = parsed.sdp;
        } catch(e) {
            return null;
        }
    }
    if (!type || !sdp) return null;
    try {
        return new RTCSessionDescription({
            type: type,
            sdp: normalizeSdp(sdp)
        });
    } catch(err) {
        console.warn('Error constructing RTCSessionDescription:', err);
        return null;
    }
}

function createIceCandidate(payload) {
    if (!payload) return null;
    let cand = payload;
    if (typeof payload === 'string') {
        try { cand = JSON.parse(payload); } catch(e) { return null; }
    }
    if (!cand || typeof cand.candidate !== 'string' || cand.candidate.trim() === '') return null;
    try {
        return new RTCIceCandidate(cand);
    } catch(e) {
        return null;
    }
}

// 4. Local WebRTC / Media Broadcast & Receiver
export class LocalMediaBroadcaster {
    constructor(videoElement, classId, options = {}) {
        this.videoElement = videoElement;
        this.classId = classId;
        this.options = options;
        this.stream = null;
        this.peers = new Map(); // studentPeerId -> { pc, pendingCandidates }
        this.rtcConfig = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
        this.signalPollTimer = null;
        this.processedSignalIds = new Set();
        this.setupSignaling();
    }

    setupSignaling() {
        this.signalPollTimer = setInterval(() => this.pollServerSignals(), 1500);
    }

    async pollServerSignals() {
        if (!this.stream) return;
        try {
            const res = await fetch(`/api/class/${this.classId}/stream/signal?peer_id=host`);
            if (!res.ok) return;
            const data = await res.json();
            if (data.signals && data.signals.length > 0) {
                for (const sig of data.signals) {
                    if (sig.id && this.processedSignalIds.has(sig.id)) continue;
                    if (sig.id) this.processedSignalIds.add(sig.id);
                    await this.handleIncomingSignal({
                        classId: this.classId,
                        type: sig.type,
                        from_peer_id: sig.from_peer_id,
                        target_peer_id: sig.target_peer_id,
                        payload: sig.payload
                    });
                }
            }
        } catch (e) {
            console.warn('Error polling host stream signals:', e);
        }
    }

    async sendSignal(type, targetPeerId, payload = null) {
        const msg = {
            classId: this.classId,
            from_peer_id: 'host',
            target_peer_id: targetPeerId,
            type: type,
            payload: payload
        };

        try {
            await fetch(`/api/class/${this.classId}/stream/signal`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify(msg)
            });
        } catch (e) {
            console.warn('Error sending host signal:', type, e);
        }
    }

    async handleIncomingSignal(data) {
        if (!this.stream) return;
        const { type, from_peer_id, payload } = data;

        if (type === 'request_stream') {
            await this.createPeerConnectionForStudent(from_peer_id);
        } else if (type === 'stream_answer') {
            const peerObj = this.peers.get(from_peer_id);
            if (peerObj && peerObj.pc) {
                const desc = createSessionDescription(payload);
                if (desc) {
                    try {
                        await peerObj.pc.setRemoteDescription(desc);
                        // Process queued ICE candidates
                        while (peerObj.pendingCandidates.length > 0) {
                            const c = peerObj.pendingCandidates.shift();
                            await peerObj.pc.addIceCandidate(c);
                        }
                    } catch (err) {
                        console.warn('Error applying student SDP answer:', err);
                    }
                }
            }
        } else if (type === 'ice_candidate') {
            const peerObj = this.peers.get(from_peer_id);
            if (peerObj) {
                const cand = createIceCandidate(payload);
                if (cand) {
                    if (peerObj.pc && peerObj.pc.remoteDescription && peerObj.pc.remoteDescription.type) {
                        try {
                            await peerObj.pc.addIceCandidate(cand);
                        } catch (err) {
                            console.warn('Error adding ICE candidate:', err);
                        }
                    } else {
                        peerObj.pendingCandidates.push(cand);
                    }
                }
            }
        }
    }

    async createPeerConnectionForStudent(studentPeerId) {
        if (!this.stream) return;

        if (this.peers.has(studentPeerId)) {
            const existing = this.peers.get(studentPeerId);
            if (existing && existing.pc && existing.pc.connectionState === 'connected') {
                return; // Peer already healthy and connected, avoid disruption
            }
            try { existing.pc.close(); } catch(e) {}
        }

        const pc = new RTCPeerConnection(this.rtcConfig);
        const peerObj = { pc, pendingCandidates: [] };
        this.peers.set(studentPeerId, peerObj);

        // Add all audio and video tracks
        this.stream.getTracks().forEach(track => {
            pc.addTrack(track, this.stream);
        });

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('ice_candidate', studentPeerId, {
                    candidate: event.candidate.candidate,
                    sdpMid: event.candidate.sdpMid,
                    sdpMLineIndex: event.candidate.sdpMLineIndex,
                    usernameFragment: event.candidate.usernameFragment
                });
            }
        };

        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed') {
                this.peers.delete(studentPeerId);
            }
        };

        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            this.sendSignal('stream_offer', studentPeerId, {
                type: offer.type,
                sdp: offer.sdp
            });
        } catch (err) {
            console.error('Error generating host WebRTC offer:', err);
        }
    }

    async startScreenAndAudio() {
        try {
            let screenStream;
            try {
                // Request screen share with audio
                screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: { cursor: 'always', frameRate: 30 },
                    audio: true,
                });
            } catch (errWithAudio) {
                console.info('getDisplayMedia with audio failed, falling back to video only:', errWithAudio);
                screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: { cursor: 'always', frameRate: 30 },
                });
            }

            let combinedStream = screenStream;
            let audioTracks = screenStream.getAudioTracks();

            // Optional microphone audio for voice explanations
            try {
                const micStream = await navigator.mediaDevices.getUserMedia({
                    audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
                    video: false,
                });

                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    const audioCtx = new AudioCtx();
                    const dest = audioCtx.createMediaStreamDestination();

                    if (screenStream.getAudioTracks().length > 0) {
                        const screenSource = audioCtx.createMediaStreamSource(screenStream);
                        screenSource.connect(dest);
                    }

                    if (micStream.getAudioTracks().length > 0) {
                        const micSource = audioCtx.createMediaStreamSource(micStream);
                        micSource.connect(dest);
                    }

                    const mixedTracks = dest.stream.getAudioTracks();
                    if (mixedTracks.length > 0) {
                        audioTracks = [mixedTracks[0]];
                    }
                }
            } catch (micErr) {
                console.info('Micrófono omitido o no disponible, emitiendo audio de pantalla.', micErr);
            }

            // If still no audio tracks, create a silent track to guarantee stable WebRTC A/V pipeline
            if (audioTracks.length === 0) {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (AudioCtx) {
                        const audioCtx = new AudioCtx();
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();
                        gain.gain.value = 0.0001;
                        const dest = audioCtx.createMediaStreamDestination();
                        osc.connect(gain);
                        gain.connect(dest);
                        osc.start();
                        audioTracks = dest.stream.getAudioTracks();
                    }
                } catch (e) {}
            }

            combinedStream = new MediaStream([
                ...screenStream.getVideoTracks(),
                ...audioTracks
            ]);

            this.stream = combinedStream;
            this.videoElement.srcObject = combinedStream;
            this.videoElement.muted = true; // Mute locally to avoid feedback
            await this.videoElement.play();

            this.sendSignal('stream_started', 'all');

            screenStream.getVideoTracks()[0].addEventListener('ended', () => {
                this.stop();
                if (this.options && typeof this.options.onStreamInactive === 'function') {
                    this.options.onStreamInactive();
                }
            });

            return true;
        } catch (err) {
            console.error('Error iniciando pantalla y voz:', err);
            throw err;
        }
    }

    startDemoTradingStream() {
        const canvas = document.createElement('canvas');
        canvas.width = 1280;
        canvas.height = 720;
        const ctx = canvas.getContext('2d');
        let frame = 0;

        this.demoStreamActive = true;

        const renderDemoChart = () => {
            if (!this.demoStreamActive) return;
            frame++;
            ctx.fillStyle = '#0B1622';
            ctx.fillRect(0, 0, 1280, 720);

            // Title & ticker
            ctx.fillStyle = '#20B8A5';
            ctx.font = 'bold 28px sans-serif';
            ctx.fillText('AVILA CALL — Gráficos de Trading en Vivo (Demostración)', 40, 50);

            ctx.fillStyle = '#A0B2C4';
            ctx.font = '16px monospace';
            ctx.fillText('EUR/USD • M15 • Transmisión en Directo WebRTC: ' + new Date().toLocaleTimeString(), 40, 80);

            // Draw grid
            ctx.strokeStyle = '#162F4A';
            ctx.lineWidth = 1;
            for (let y = 120; y < 680; y += 60) {
                ctx.beginPath();
                ctx.moveTo(40, y);
                ctx.lineTo(1240, y);
                ctx.stroke();
            }

            // Draw dynamic animated candlesticks
            for (let i = 0; i < 28; i++) {
                const x = 60 + i * 42;
                const wave = Math.sin((frame * 0.05) + i * 0.4) * 60;
                const yBase = 400 + wave;
                const h = 25 + Math.abs(Math.cos(i + frame * 0.03) * 50);
                const isGreen = wave > 0;

                ctx.strokeStyle = isGreen ? '#20B8A5' : '#EF4444';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(x + 12, yBase - h - 18);
                ctx.lineTo(x + 12, yBase + 18);
                ctx.stroke();

                ctx.fillStyle = isGreen ? '#20B8A5' : '#EF4444';
                ctx.fillRect(x, yBase - h, 24, h);
            }
        };

        renderDemoChart();

        // Use setInterval (25 FPS) so Chrome does NOT freeze frames when the teacher tab is in the background!
        if (this.demoRenderTimer) clearInterval(this.demoRenderTimer);
        this.demoRenderTimer = setInterval(renderDemoChart, 40);

        const canvasStream = canvas.captureStream(25);

        // Add silent audio track for seamless WebRTC A/V sync in Chromium
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                const audioCtx = new AudioCtx();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                gain.gain.value = 0.0001;
                const dest = audioCtx.createMediaStreamDestination();
                osc.connect(gain);
                gain.connect(dest);
                osc.start();
                dest.stream.getAudioTracks().forEach(t => canvasStream.addTrack(t));
            }
        } catch (e) {
            console.warn('Audio track setup for canvas demo skipped:', e);
        }

        this.stream = canvasStream;
        this.videoElement.srcObject = canvasStream;
        this.videoElement.muted = true;
        this.videoElement.play();

        this.sendSignal('stream_started', 'all');
        return true;
    }

    stop() {
        this.demoStreamActive = false;
        if (this.demoRenderTimer) {
            clearInterval(this.demoRenderTimer);
            this.demoRenderTimer = null;
        }
        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
            this.stream = null;
        }
        if (this.videoElement) {
            this.videoElement.srcObject = null;
        }

        this.peers.forEach(p => {
            try { p.pc.close(); } catch(e) {}
        });
        this.peers.clear();

        this.sendSignal('stream_stopped', 'all');
    }

    destroy() {
        if (this.signalPollTimer) clearInterval(this.signalPollTimer);
        this.stop();
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
}

export class LocalMediaSubscriber {
    constructor(videoElement, classId, options = {}) {
        this.videoElement = videoElement;
        this.classId = classId;
        this.peerId = options.peerId || ('student_' + Math.random().toString(36).substring(2, 10));
        this.sessionToken = options.sessionToken || '';
        this.pc = null;
        this.pendingCandidates = [];
        this.processedSignalIds = new Set();
        this.lastSignalTimestamp = 0;
        this.rtcConfig = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
        this.onStreamActive = options.onStreamActive || (() => {});
        this.onStreamInactive = options.onStreamInactive || (() => {});
        this.pollTimer = null;
        this.requestTimer = null;
        this.hasReceivedStream = false;

        this.init();
    }

    init() {
        this.pollTimer = setInterval(() => this.pollServerSignals(), 1500);

        this.requestStream();

        this.requestTimer = setInterval(() => {
            if (!this.hasReceivedStream) {
                this.requestStream();
            }
        }, 3000);
    }

    async pollServerSignals() {
        try {
            const url = `/api/class/${this.classId}/stream/signal?peer_id=${encodeURIComponent(this.peerId)}&since=${this.lastSignalTimestamp}`;
            const res = await fetch(url, {
                headers: { 'X-Session-Token': this.sessionToken }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.signals && data.signals.length > 0) {
                for (const sig of data.signals) {
                    if (sig.id && this.processedSignalIds.has(sig.id)) continue;
                    if (sig.id) this.processedSignalIds.add(sig.id);
                    if (sig.timestamp && sig.timestamp > this.lastSignalTimestamp) {
                        this.lastSignalTimestamp = sig.timestamp;
                    }
                    await this.handleIncomingSignal({
                        classId: this.classId,
                        type: sig.type,
                        from_peer_id: sig.from_peer_id,
                        target_peer_id: sig.target_peer_id,
                        payload: sig.payload
                    });
                }
            }
        } catch (e) {
            console.warn('Error polling student stream signals:', e);
        }
    }

    requestStream() {
        if (this.hasReceivedStream && this.pc && this.pc.connectionState === 'connected') {
            return;
        }
        this.sendSignal('request_stream', 'host');
    }

    async sendSignal(type, targetPeerId, payload = null) {
        const msg = {
            classId: this.classId,
            from_peer_id: this.peerId,
            target_peer_id: targetPeerId,
            type: type,
            payload: payload
        };

        try {
            await fetch(`/api/class/${this.classId}/stream/signal`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                    'X-Session-Token': this.sessionToken
                },
                body: JSON.stringify(msg)
            });
        } catch (e) {
            console.warn('Error sending student signal:', type, e);
        }
    }

    async handleIncomingSignal(data) {
        const { type, target_peer_id, payload } = data;

        if (type === 'stream_started') {
            if (!this.hasReceivedStream) {
                this.requestStream();
            }
            return;
        }

        if (type === 'stream_stopped') {
            this.hasReceivedStream = false;
            if (this.videoElement) {
                this.videoElement.srcObject = null;
            }
            this.onStreamInactive();
            this.togglePlaceholder(true);
            return;
        }

        if (target_peer_id !== this.peerId && target_peer_id !== 'all') {
            return;
        }

        if (type === 'stream_offer' && payload) {
            await this.handleOffer(payload);
        } else if (type === 'ice_candidate' && payload) {
            const cand = createIceCandidate(payload);
            if (cand) {
                if (this.pc && this.pc.remoteDescription && this.pc.remoteDescription.type) {
                    try {
                        await this.pc.addIceCandidate(cand);
                    } catch (err) {
                        console.warn('Error adding host candidate:', err);
                    }
                } else {
                    this.pendingCandidates.push(cand);
                }
            }
        }
    }

    async handleOffer(offerData) {
        if (this.pc) {
            try { this.pc.close(); } catch(e) {}
        }

        this.pc = new RTCPeerConnection(this.rtcConfig);
        this.remoteStream = new MediaStream();

        this.pc.ontrack = (event) => {
            if (event.streams && event.streams[0]) {
                this.remoteStream = event.streams[0];
            } else if (event.track) {
                this.remoteStream.addTrack(event.track);
            }

            this.hasReceivedStream = true;
            this.videoElement.srcObject = this.remoteStream;
            this.videoElement.muted = true; // Crucial for Chrome autoplay compliance
            this.videoElement.playsInline = true;

            const playPromise = this.videoElement.play();
            if (playPromise !== undefined) {
                playPromise.catch(e => {
                    console.log('Autoplay fallback:', e);
                    this.videoElement.muted = true;
                    this.videoElement.play().catch(() => {});
                });
            }

            this.togglePlaceholder(false);
            this.onStreamActive(this.remoteStream);
        };

        this.pc.onconnectionstatechange = () => {
            if (this.pc.connectionState === 'connected') {
                this.hasReceivedStream = true;
                this.togglePlaceholder(false);
                this.onStreamActive(this.remoteStream);
            } else if (this.pc.connectionState === 'failed' || this.pc.connectionState === 'disconnected') {
                this.hasReceivedStream = false;
                this.togglePlaceholder(true);
                this.onStreamInactive();
            }
        };

        this.pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('ice_candidate', 'host', {
                    candidate: event.candidate.candidate,
                    sdpMid: event.candidate.sdpMid,
                    sdpMLineIndex: event.candidate.sdpMLineIndex,
                    usernameFragment: event.candidate.usernameFragment
                });
            }
        };

        const desc = createSessionDescription(offerData);
        if (!desc) {
            console.error('Invalid SDP offer received.');
            return;
        }

        try {
            await this.pc.setRemoteDescription(desc);

            // Flush pending ICE candidates
            while (this.pendingCandidates.length > 0) {
                const cand = this.pendingCandidates.shift();
                await this.pc.addIceCandidate(cand);
            }

            const answer = await this.pc.createAnswer();
            await this.pc.setLocalDescription(answer);

            this.sendSignal('stream_answer', 'host', {
                type: answer.type,
                sdp: answer.sdp
            });
        } catch (err) {
            console.error('Error in WebRTC answer handshake:', err);
        }
    }

    togglePlaceholder(showPlaceholder) {
        const placeholder = document.getElementById('student-waiting-placeholder');
        if (placeholder) {
            placeholder.style.display = showPlaceholder ? 'flex' : 'none';
        }
        if (this.videoElement) {
            this.videoElement.style.display = showPlaceholder ? 'none' : 'block';
        }
    }

    destroy() {
        if (this.pollTimer) clearInterval(this.pollTimer);
        if (this.requestTimer) clearInterval(this.requestTimer);
        if (this.pc) {
            try { this.pc.close(); } catch(e) {}
            this.pc = null;
        }
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
}

export class LiveKitMediaBroadcaster {
    constructor(videoElement, config, options = {}) {
        this.videoElement = videoElement;
        this.config = config;
        this.room = null;
        this.onStreamInactive = options.onStreamInactive || (() => {});
    }

    async connect() {
        if (this.room) return;
        const { Room, RoomEvent, Track } = await loadLiveKit();
        this.room = new Room({
            adaptiveStream: true,
            dynacast: true,
        });
        this.room.on(RoomEvent.LocalTrackUnpublished, (publication) => {
            if (publication.source === Track.Source.ScreenShare) {
                this.onStreamInactive();
            }
        });
        await this.room.connect(this.config.host_url, this.config.token);
    }

    async startScreenAndAudio() {
        await this.connect();

        const screenPublication = await this.room.localParticipant.setScreenShareEnabled(true, {
            audio: true,
            video: true,
            systemAudio: 'include',
            surfaceSwitching: 'include',
            contentHint: 'detail',
        });

        await this.room.localParticipant.setMicrophoneEnabled(true, {
            echoCancellation: true,
            noiseSuppression: true,
        });

        if (screenPublication?.track && this.videoElement) {
            screenPublication.track.attach(this.videoElement);
            this.videoElement.muted = true;
            await this.videoElement.play();
        }

        return true;
    }

    startDemoTradingStream() {
        throw new Error('La demostracion sintetica solo esta disponible con WebRTC local.');
    }

    async stop() {
        if (!this.room) return;
        await Promise.allSettled([
            this.room.localParticipant.setScreenShareEnabled(false),
            this.room.localParticipant.setMicrophoneEnabled(false),
        ]);
        if (this.videoElement) this.videoElement.srcObject = null;
    }

    destroy() {
        if (!this.room) return;
        this.room.disconnect(true);
        this.room = null;
    }
}

export class LiveKitMediaSubscriber {
    constructor(videoElement, config, options = {}) {
        this.videoElement = videoElement;
        this.config = config;
        this.room = null;
        this.audioElements = new Set();
        this.onStreamActive = options.onStreamActive || (() => {});
        this.onStreamInactive = options.onStreamInactive || (() => {});
        this.connect();
    }

    setupEvents(RoomEvent, Track) {
        this.room.on(RoomEvent.TrackSubscribed, (track, publication) => {
            if (publication.source === Track.Source.ScreenShare && track.kind === Track.Kind.Video) {
                track.attach(this.videoElement);
                this.videoElement.muted = true;
                this.videoElement.play().catch(() => {});
                this.onStreamActive();
                return;
            }

            if (track.kind === Track.Kind.Audio) {
                const audio = track.attach();
                audio.autoplay = true;
                audio.style.display = 'none';
                document.body.appendChild(audio);
                this.audioElements.add(audio);
            }
        });

        this.room.on(RoomEvent.TrackUnsubscribed, (track, publication) => {
            track.detach().forEach((element) => {
                this.audioElements.delete(element);
                if (element !== this.videoElement) element.remove();
            });
            if (publication.source === Track.Source.ScreenShare) {
                this.videoElement.srcObject = null;
                this.onStreamInactive();
            }
        });

        this.room.on(RoomEvent.Disconnected, () => this.onStreamInactive());
    }

    async connect() {
        try {
            const { Room, RoomEvent, Track } = await loadLiveKit();
            this.room = new Room({
                adaptiveStream: true,
                dynacast: true,
            });
            this.setupEvents(RoomEvent, Track);
            await this.room.connect(this.config.host_url, this.config.token, {
                autoSubscribe: true,
            });
        } catch (error) {
            console.error('No se pudo conectar a LiveKit:', error);
            this.onStreamInactive();
        }
    }

    async resumeAudio() {
        if (!this.room) return;
        await this.room.startAudio();
        this.audioElements.forEach((element) => {
            element.muted = false;
            element.play().catch(() => {});
        });
    }

    destroy() {
        this.audioElements.forEach((element) => element.remove());
        this.audioElements.clear();
        if (this.room) this.room.disconnect(true);
        this.videoElement.srcObject = null;
    }
}

// Helpers
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function responseJsonSafe(res) {
    try {
        return await res.json();
    } catch {
        return {};
    }
}

// Global CSRF & Session Keepalive for long trading class sessions
function setupCsrfAndSessionKeepalive() {
    // Keep form CSRF tokens synced with meta tag on form submission
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form && form.method && form.method.toUpperCase() === 'POST') {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) {
                const token = meta.getAttribute('content');
                const tokenInput = form.querySelector('input[name="_token"]');
                if (tokenInput && token) {
                    tokenInput.value = token;
                }
            }
        }
    }, true);

    // Refresh token periodically (every 10 minutes)
    setInterval(async () => {
        try {
            const res = await fetch('/csrf-token');
            if (res.ok) {
                const data = await res.json();
                if (data && data.token) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.setAttribute('content', data.token);
                    document.querySelectorAll('input[name="_token"]').forEach(input => {
                        input.value = data.token;
                    });
                }
            }
        } catch (e) {}
    }, 10 * 60 * 1000);
}

// Expose to window for direct Blade template consumption
if (typeof window !== 'undefined') {
    window.AvilaCall = {
        ThemeManager,
        SessionHeartbeatClient,
        LiveChatClient,
        LocalMediaBroadcaster,
        LocalMediaSubscriber,
        LiveKitMediaBroadcaster,
        LiveKitMediaSubscriber,
    };

    const initGlobal = () => {
        ThemeManager.init();
        setupCsrfAndSessionKeepalive();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGlobal);
    } else {
        initGlobal();
    }
}
