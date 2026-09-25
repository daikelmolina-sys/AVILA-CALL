<?php

namespace Database\Seeders;

use App\Models\AccessCode;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default Host user for development in XAMPP
        $host = User::updateOrCreate(
            ['email' => 'profesor@avilacall.local'],
            [
                'name' => 'Profesor Avila Call',
                'password' => Hash::make('password123'),
                'role' => 'host',
            ]
        );

        // Demo Live Class
        $liveClass = LiveClass::firstOrCreate(
            ['title' => 'Masterclass Trading: Estructura de Mercado y Liquidez'],
            [
                'description' => 'Sesión en vivo de análisis de mercado, zonas de oferta/demanda y gestión de riesgo.',
                'host_id' => $host->id,
                'status' => 'live',
                'chat_enabled' => true,
                'media_provider' => 'local_webrtc',
                'started_at' => now(),
            ]
        );

        // Generate a sample student access code and sample moderator code
        if ($liveClass->accessCodes()->count() === 0) {
            AccessCode::createSecureCode($liveClass->id, 'Alumno Demo 1', 'student');
            AccessCode::createSecureCode($liveClass->id, 'Moderador Demo', 'moderator');
        }
    }
}
