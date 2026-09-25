<?php

namespace App\Http\Controllers;

use App\Models\AccessCode;
use App\Models\ActiveSession;
use App\Models\LiveClass;
use App\Services\Media\MediaManager;
use App\Services\Media\LiveKitRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LiveClassController extends Controller
{
    protected MediaManager $mediaManager;

    public function __construct(MediaManager $mediaManager, protected LiveKitRoomService $liveKitRooms)
    {
        $this->mediaManager = $mediaManager;
    }

    /**
     * Dashboard: list trading classes
     */
    public function index(): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = LiveClass::with('host')->orderBy('id', 'desc');
        if (!$user->isAdmin()) {
            $query->where('host_id', $user->id);
        }

        $classes = $query->paginate(15);
        $liveKitConfigured = $this->mediaManager->getAdapter('livekit_sfu')->isConfigured();

        return view('admin.classes.index', compact('classes', 'liveKitConfigured'));
    }

    /**
     * Store new live class
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'description' => 'nullable|string|max:1000',
            'media_provider' => 'nullable|string|in:local_webrtc,livekit_sfu',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $provider = $validated['media_provider'] ?? 'local_webrtc';

        if (!$this->mediaManager->getAdapter($provider)->isConfigured()) {
            return back()->withErrors([
                'media_provider' => 'LiveKit no esta configurado en este entorno.',
            ])->withInput();
        }

        $liveClass = LiveClass::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'host_id' => $user->id,
            'status' => 'scheduled',
            'chat_enabled' => true,
            'media_provider' => $provider,
        ]);

        return redirect()->route('admin.classes.show', $liveClass->id)
            ->with('success', 'Clase creada exitosamente.');
    }

    /**
     * Show class management detail (codes, statistics, configuration)
     */
    public function show(int $id): View
    {
        $liveClass = LiveClass::with(['host', 'accessCodes' => function ($q) {
            $q->orderBy('id', 'desc');
        }])->findOrFail($id);

        $this->authorizeHost($liveClass);

        $connectedCount = $liveClass->accessCodes->where('status', 'connected')->count();
        $availableCount = $liveClass->accessCodes->where('status', 'available')->count();
        $revokedCount = $liveClass->accessCodes->whereIn('status', ['revoked', 'banned'])->count();

        $moderationLogs = $liveClass->moderationLogs()->latest()->take(25)->get();

        return view('admin.classes.show', compact('liveClass', 'connectedCount', 'availableCount', 'revokedCount', 'moderationLogs'));
    }

    /**
     * Host broadcasting studio (screen sharing + mic broadcast + chat + audience)
     */
    public function studio(int $id): View
    {
        $liveClass = LiveClass::with('host')->findOrFail($id);
        $this->authorizeHost($liveClass);

        $adapter = $this->mediaManager->getAdapterForClass($liveClass);
        abort_unless($adapter->isConfigured(), 503, 'El proveedor de transmision de esta clase no esta configurado.');
        $mediaConfig = $adapter->getClientConfig($liveClass, 'host', 'host_'.Auth::id());

        return view('host.studio', compact('liveClass', 'mediaConfig'));
    }

    /**
     * Start live class
     */
    public function startClass(int $id): JsonResponse|RedirectResponse
    {
        $liveClass = LiveClass::findOrFail($id);
        $this->authorizeHost($liveClass);

        $adapter = $this->mediaManager->getAdapterForClass($liveClass);
        abort_unless($adapter->isConfigured(), 503, 'El proveedor de transmision de esta clase no esta configurado.');
        $streamInfo = $adapter->initializeClassStream($liveClass);

        $liveClass->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'live',
                'stream_info' => $streamInfo,
            ]);
        }

        return redirect()->route('host.studio', $liveClass->id)->with('success', '¡La clase está ahora EN VIVO!');
    }

    /**
     * End live class (terminates active sessions, stops stream, prevents recording)
     */
    public function endClass(int $id): JsonResponse|RedirectResponse
    {
        $liveClass = LiveClass::findOrFail($id);
        $this->authorizeHost($liveClass);

        $adapter = $this->mediaManager->getAdapterForClass($liveClass);
        $this->liveKitRooms->deleteClassRoom($liveClass);
        $adapter->endClassStream($liveClass);

        $liveClass->update([
            'status' => 'ended',
            'ended_at' => now(),
            'current_viewers_count' => 0,
        ]);

        // Invalidate all active sessions for this class immediately
        ActiveSession::where('live_class_id', $liveClass->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'revocation_reason' => 'class_ended',
                'updated_at' => now(),
            ]);

        // Release connected access codes
        AccessCode::where('live_class_id', $liveClass->id)
            ->where('status', 'connected')
            ->update(['status' => 'available']);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'status' => 'ended']);
        }

        return redirect()->route('admin.classes.show', $liveClass->id)->with('info', 'La clase ha finalizado.');
    }

    /**
     * Generate individual access codes (single or batch)
     */
    public function generateCodes(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $liveClass = LiveClass::findOrFail($id);
        $this->authorizeHost($liveClass);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:1000',
            'prefix_label' => 'nullable|string|max:50',
            'role' => 'nullable|string|in:student,moderator',
        ]);

        $quantity = (int)$validated['quantity'];
        $role = $validated['role'] ?? 'student';
        $prefix = $validated['prefix_label'] ?? 'Alumno';

        $generatedCodes = [];

        for ($i = 1; $i <= $quantity; $i++) {
            $studentIdentifier = $quantity > 1 ? "{$prefix} #{$i}" : $prefix;
            $res = AccessCode::createSecureCode($liveClass->id, $studentIdentifier, $role);
            $generatedCodes[] = [
                'raw_code' => $res['raw_code'],
                'student_identifier' => $studentIdentifier,
                'role' => $role,
            ];
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'count' => count($generatedCodes),
                'codes' => $generatedCodes,
            ]);
        }

        return back()->with('generated_codes', $generatedCodes)
            ->with('success', "Se generaron {$quantity} código(s) de acceso de forma segura.");
    }

    /**
     * Revoke an access code
     */
    public function revokeCode(int $classId, int $codeId): RedirectResponse|JsonResponse
    {
        $liveClass = LiveClass::findOrFail($classId);
        $this->authorizeHost($liveClass);

        $code = AccessCode::where('id', $codeId)->where('live_class_id', $liveClass->id)->firstOrFail();
        $code->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        ActiveSession::where('access_code_id', $code->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'revocation_reason' => 'revoked',
                'updated_at' => now(),
            ]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('info', 'Código revocado exitosamente.');
    }

    protected function authorizeHost(LiveClass $liveClass): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && $liveClass->host_id !== $user->id) {
            abort(403, 'No tienes permiso para administrar esta clase.');
        }
    }
}
