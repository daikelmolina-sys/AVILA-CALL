# Despliegue y prueba real de LiveKit para AVILA CALL

## 1. Configurar un entorno de pruebas

Provisiona LiveKit Cloud o un clúster LiveKit de staging separado de producción. XAMPP sigue ejecutando Laravel, pero no sustituye al SFU.

En `.env` local configura valores reales sin copiarlos a documentación, pruebas o control de versiones:

```dotenv
MEDIA_PROVIDER=local_webrtc
LIVEKIT_HOST=wss://<host-livekit>
LIVEKIT_API_KEY=<clave>
LIVEKIT_API_SECRET=<secreto>
LIVEKIT_TOKEN_TTL=600
```

Después ejecuta:

```powershell
php artisan config:clear
php artisan config:cache
```

Reinicia Apache desde XAMPP. Al crear una clase, selecciona `LiveKit SFU` y prueba primero con dos navegadores: profesor y alumno.

## 2. Verificación funcional previa

- El profesor comparte pantalla y micrófono, pero nunca cámara.
- El alumno recibe pantalla y audio y no obtiene permisos de publicación.
- Una segunda sesión con el mismo código desconecta la primera del SFU.
- Expulsar o bloquear desconecta al alumno del SFU.
- Finalizar la clase desconecta la sala completa.
- No existe egress, grabación ni repetición configurada.

## 3. Prueba de más de 1.000 suscriptores

Instala la CLI oficial de LiveKit en una o varias máquinas generadoras externas al nodo SFU. Define las credenciales únicamente como variables de entorno de esa sesión y ejecuta una prueba de livestream con más de 1.000 suscriptores:

```powershell
lk load-test `
  --url $env:LIVEKIT_HOST `
  --api-key $env:LIVEKIT_API_KEY `
  --api-secret $env:LIVEKIT_API_SECRET `
  --room avila-load-test `
  --video-publishers 1 `
  --subscribers 1001
```

Si una sola máquina generadora alcanza su propio límite de CPU, red o descriptores, distribuye los participantes entre varias máquinas. No ejecutes esta carga sin revisar los límites y costes del proveedor.

En paralelo, prueba los endpoints reales de AVILA CALL para códigos, heartbeat y chat con concurrencia; el comando `avila:simulate-load` no reemplaza esa prueba HTTP distribuida.

## 4. Criterio de cierre

El requisito seguirá como **No verificado** hasta adjuntar al informe la salida sanitizada, métricas del SFU y del backend, duración sostenida, errores y evidencia de al menos 1.001 suscriptores conectados al mismo livestream.

Documentación oficial: https://docs.livekit.io/transport/self-hosting/benchmark/
