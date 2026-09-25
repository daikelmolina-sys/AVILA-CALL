# Memoria del proyecto — AVILA CALL

Este documento conserva contexto estable para futuras sesiones de trabajo. Actualizarlo cuando el usuario confirme o cambie una decisión. No incluir secretos ni datos personales innecesarios.

## Tecnologías acordadas

- La interfaz web de AVILA CALL debe utilizar HTML5, CSS3 y JavaScript.
- Integrar esas tecnologías con el framework/estructura que exista, sin imponer una migración. El backend y sus versiones se determinarán inspeccionando el proyecto.

## Entorno de trabajo del usuario

- IDE: Antigravity IDE.
- Desarrollo local: XAMPP.
- Versiones reales verificadas en el entorno local:
  - PHP: 8.2.12 (CLI / Visual C++ 2019 x64)
  - Servidor web: Apache/2.4.58 (Win64)
  - Base de datos: MariaDB 10.4.32 / SQLite para desarrollo y pruebas locales automatizadas
  - Node.js: v26.4.0, NPM: 11.17.0
  - Framework: Laravel 12.0 con Vite 7.3.6 y Tailwind/Vanilla CSS con variables accesibles Claro/Oscuro

## Producto

- **Nombre:** AVILA CALL.
- **Propósito:** plataforma independiente para impartir clases de trading en vivo, combinando transmisión del profesor y chat de asistentes.
- **Aforo objetivo:** más de 1.000 alumnos conectados a una clase simultáneamente.
- **Integraciones de producto:** no depender de Telegram ni Zoom; la experiencia será propia. La tecnología de medios aún debe elegirse tras validar escala, latencia, privacidad, compatibilidad y coste.

## Decisiones confirmadas

- El anfitrión comparte **su pantalla** durante la clase.
- Los alumnos solo ven la transmisión y escriben en el chat cuando el anfitrión lo habilita.
- Los alumnos no activan cámara o micrófono ni comparten pantalla.
- La clase es únicamente en vivo; no se guarda ni ofrece una grabación para verla después.
- Cada alumno recibe un código individual de acceso.
- Un código admite una sola sesión activa. Si el alumno inicia sesión en otro dispositivo, la sesión anterior debe cerrarse y el nuevo dispositivo queda activo.
- El anfitrión puede abrir/cerrar la escritura en el chat y nombrar moderadores.
- El anfitrión y los moderadores deben poder moderar y expulsar/bloquear asistentes con permisos adecuados.
- Solo las cuentas con rol `host` o `admin` pueden entrar al dashboard. Cada profesor crea y administra sus propias clases y solo genera códigos para ellas; el administrador puede administrar clases y generar códigos en cualquiera. Moderadores y alumnos no pueden crear clases ni generar códigos.
- **Proveedor SFU de producción para escala real:** la arquitectura de medios queda desacoplada mediante `MediaAdapterInterface`. Para soportar más de 1.000 espectadores concurrentes en producción, se conectará a un cluster LiveKit SFU (o Cloudflare Calls WHEP/WHIP) mediante las variables `LIVEKIT_HOST`, `LIVEKIT_API_KEY` y `LIVEKIT_API_SECRET`. Para el entorno de desarrollo y pruebas locales se utiliza `LocalWebRtcMediaAdapter`.
- **Estrategia CSRF y resiliencia de sesión (Error 419):** las rutas API de señalización WebRTC (`api/class/*/stream/*`), chat (`api/class/*/chat/*`), heartbeat (`student/heartbeat`) y logout (`student/logout`) están exentas del middleware CSRF porque validan directamente el `X-Session-Token` / sesión activa en servidor. Además, se configuró en `bootstrap/app.php` el rescate de `TokenMismatchException` para redirigir amigablemente con advertencia y preservación de inputs si expira un formulario de login/estudio. En el frontend se implementó un sincronizador automático de tokens CSRF y un keepalive periódico cada 10 minutos para mantener vivas las sesiones durante clases prolongadas.
- **Transmisión de Pantalla Real y Señalización WebRTC:** `LocalMediaBroadcaster` y `LocalMediaSubscriber` utilizan señalización con UUIDs únicos y filtro temporal `since` para evitar bucles infinitos de renegociación. Se implementó captura robusta de pantalla con audio del sistema y mezcla opcional de voz mediante `getUserMedia` y `AudioContext`. Los candidatos ICE tempranos son retenidos en cola hasta fijar la descripción remota.
- **Retención y purga de chat y moderación:** una hora después de finalizar una clase se purgan sus mensajes de chat y registros de auditoría/moderación (`php artisan avila:purge-ended-class-data --hours=1`). Se conserva además la purga máxima de 30 días como respaldo para datos de clases que no hayan finalizado correctamente (`php artisan avila:purge-chat --days=30`).

## Requisitos importantes de implementación

- La exclusividad de sesión debe aplicarse en servidor y almacenamiento compartido; una sesión nueva revoca realmente el acceso anterior al video y al chat.
- Controlar condiciones de carrera, expiración por desconexión, heartbeat y reconexiones para evitar expulsiones erráticas.
- Los códigos deben ser impredecibles, revocables, no enumerables y protegidos contra fuerza bruta y filtraciones en URLs/logs.
- El estado abierto/cerrado del chat debe validarse también en backend.
- La capacidad de más de 1.000 espectadores debe validarse con una prueba de carga documentada sobre la arquitectura y proveedor de despliegue elegidos. No afirmarla por estimación.
- No solicitar ni almacenar credenciales de brokers o datos de cuentas de trading.
- Purga programada cada minuto para eliminar el chat y los registros de auditoría de clases finalizadas hace al menos una hora; retención máxima adicional de 30 días como respaldo.

## Pendientes por decidir

- Si los códigos se asignan obligatoriamente a nombres/correos y cómo se verifica la identidad del alumno.
- Si los códigos vencen y qué política de asistencia o reingreso aplica por clase.
- Navegadores y dispositivos mínimos admitidos.
- Ventana exacta de reconexión y duración del silencio/expulsión temporal.
- Jurisdicción del operador y textos de aviso/consentimiento que correspondan.
- Confirmar si el profesor debe transmitir su micrófono además de la pantalla.

## Documentos de referencia

- `AVILA_CALL_SKILL.md`: especificación de producto, flujos, seguridad y criterios de aceptación.
- `AGENTS.md`: instrucciones de trabajo para agentes de programación en este repositorio.
