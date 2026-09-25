<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatApiController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\StudentRoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - AVILA CALL
|--------------------------------------------------------------------------
*/

// Student Authentication & Room
Route::get('/', [AuthController::class, 'showStudentLogin'])->name('student.login');
Route::post('/student/auth', [AuthController::class, 'authenticateStudentCode'])->name('student.auth');
Route::post('/student/heartbeat', [AuthController::class, 'heartbeat'])->name('student.heartbeat');
Route::post('/student/logout', [AuthController::class, 'logoutStudent'])->name('student.logout');
Route::get('/student/room/{class}', [StudentRoomController::class, 'show'])->name('student.room');

// Host / Admin Authentication
Route::get('/host/login', [AuthController::class, 'showHostLogin'])->name('host.login');
Route::post('/host/login', [AuthController::class, 'loginHost'])->name('host.login.submit');
Route::post('/host/logout', [AuthController::class, 'logoutHost'])->name('host.logout');
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf.token');

// Host / Admin Protected Routes
Route::middleware(['auth', 'host_or_admin'])->group(function () {
    Route::get('/admin/classes', [LiveClassController::class, 'index'])->name('admin.classes.index');
    Route::post('/admin/classes', [LiveClassController::class, 'store'])->name('admin.classes.store');
    Route::get('/admin/classes/{class}', [LiveClassController::class, 'show'])->name('admin.classes.show');
    Route::post('/admin/classes/{class}/start', [LiveClassController::class, 'startClass'])->name('admin.classes.start');
    Route::post('/admin/classes/{class}/end', [LiveClassController::class, 'endClass'])->name('admin.classes.end');
    Route::post('/admin/classes/{class}/codes', [LiveClassController::class, 'generateCodes'])->name('admin.classes.codes.generate');
    Route::post('/admin/classes/{class}/codes/{code}/revoke', [LiveClassController::class, 'revokeCode'])->name('admin.classes.codes.revoke');

    Route::get('/host/studio/{class}', [LiveClassController::class, 'studio'])->name('host.studio');
});

// Real-Time Chat & Moderation API
Route::prefix('api/class/{class}/chat')->group(function () {
    Route::get('/messages', [ChatApiController::class, 'getMessages'])->name('api.chat.messages');
    Route::post('/messages', [ChatApiController::class, 'sendMessage'])->name('api.chat.send');
    Route::post('/toggle', [ChatApiController::class, 'toggleChat'])->name('api.chat.toggle');
    Route::post('/delete-message', [ChatApiController::class, 'deleteMessage'])->name('api.chat.delete');
    Route::post('/kick', [ChatApiController::class, 'kickStudent'])->name('api.chat.kick');
    Route::post('/ban', [ChatApiController::class, 'banStudent'])->name('api.chat.ban');
    Route::post('/role', [ChatApiController::class, 'setModeratorRole'])->name('api.chat.role');
});

// Real-Time WebRTC Media Signaling
Route::prefix('api/class/{class}/stream')->group(function () {
    Route::post('/signal', [\App\Http\Controllers\StreamSignalController::class, 'sendSignal'])->name('api.stream.signal.send');
    Route::get('/signal', [\App\Http\Controllers\StreamSignalController::class, 'getSignals'])->name('api.stream.signal.get');
});
