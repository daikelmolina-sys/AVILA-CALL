# Informe de carga y arquitectura de medios — AVILA CALL

## Estado del requisito de más de 1.000 asistentes

**No verificado.** No existe todavía una prueba de carga ejecutada contra un despliegue LiveKit real de AVILA CALL con más de 1.000 suscriptores concurrentes.

El comando local siguiente ejercita lógica PHP de forma secuencial, pero no abre conexiones concurrentes, no transporta audio/video y no mide el SFU ni la red:

```powershell
php artisan avila:simulate-load --viewers=1000 --messages=200
```

Sus resultados sirven únicamente como benchmark local de códigos, sesiones, heartbeat y chat. No constituyen evidencia de capacidad para 1.000 espectadores.

## Estado de los adaptadores

### WebRTC local

- Uso: desarrollo y demostraciones en XAMPP.
- Señalización: pasa por Laravel y exige sesión, rol y pertenencia a la clase.
- Topología: el profesor establece una conexión WebRTC por alumno.
- Escala superior a 1.000: **no apta y no verificada**.

### LiveKit SFU

- El backend genera tokens JWT de corta duración sin exponer el secreto de API.
- El profesor solo puede publicar `microphone`, `screen_share` y `screen_share_audio`.
- El alumno recibe un token de solo suscripción; no puede publicar medios ni datos.
- No se concede `roomRecord` y la aplicación no inicia egress o grabaciones.
- Una sesión sustituida, expulsada o bloqueada se elimina del SFU mediante `RoomService.RemoveParticipant`.
- Al finalizar la clase se solicita `RoomService.DeleteRoom` para desconectar a todos.
- El SDK web se carga únicamente en clases configuradas con LiveKit.
- Despliegue y credenciales en este entorno: **no configurados**.
- Prueba real de más de 1.000 asistentes: **no ejecutada**.

## Evidencia requerida para cambiar el estado a “Cumple”

Debe ejecutarse `lk load-test` contra el mismo despliegue, región y configuración destinados a producción, con al menos un publicador de video y 1.001 suscriptores. La herramienta oficial genera tráfico real de medios y puede distribuirse entre varias máquinas generadoras.

Conservar como evidencia:

1. Fecha, versión de LiveKit Server/Cloud y versión de `lk`.
2. Topología, región, capacidad de nodos, Redis/TURN y límites del plan.
3. Comando sanitizado y duración sostenida de la prueba.
4. Cantidad conectada/suscrita, errores, desconexiones y reconexiones.
5. CPU, memoria, ancho de banda, pérdida de paquetes y latencia del SFU.
6. Métricas del backend AVILA CALL para login, heartbeat, chat y base de datos durante la misma prueba.

Procedimiento reproducible: `LIVEKIT_DEPLOYMENT.md`.

Referencias oficiales:

- https://docs.livekit.io/transport/self-hosting/benchmark/
- https://docs.livekit.io/home/server/generating-tokens/
- https://docs.livekit.io/transport/media/screenshare/
