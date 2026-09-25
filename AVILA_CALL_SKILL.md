# SKILL.MD — Desarrollo de AVILA CALL

## Documentos que deben leerse antes de trabajar

Antes de inspeccionar o modificar el proyecto, lee `AGENTS.md` y `MEMORY.md` junto con este SKILL.MD. Si alguno todavía no existe en el repositorio, indícalo y continúa con los documentos disponibles.

- `AGENTS.md` define el procedimiento y las reglas de trabajo del agente.
- `MEMORY.md` reúne decisiones confirmadas, contexto duradero y puntos pendientes. No conviertas una pregunta abierta o un supuesto de `MEMORY.md` en requisito aprobado.
- Este SKILL.MD define el alcance funcional de AVILA CALL y sus criterios de aceptación.
- Las instrucciones explícitas más recientes del usuario prevalecen. Si los documentos discrepan o una decisión pendiente bloquea una implementación, conserva los requisitos no afectados, registra la discrepancia y consulta al usuario sobre ese punto antes de fijarlo.

Al terminar una fase, actualiza `MEMORY.md` únicamente con decisiones nuevas confirmadas o cambios duraderos; no copies resultados temporales ni secretos.

## Propósito

Construir una plataforma web independiente llamada **AVILA CALL** para impartir clases de trading en vivo. Debe reunir una experiencia de transmisión de clase y chat en tiempo real, con acceso individual por código. La meta de capacidad es **más de 1.000 alumnos conectados a una misma clase**.

Este documento define el alcance funcional y los criterios de aceptación. Antes de modificar un repositorio existente, inspecciona su estructura, tecnología, instrucciones locales, configuración y pruebas. Conserva lo que ya funciona y adapta la implementación al proyecto real. Si no hay proyecto, presenta primero una base técnica pequeña y mantenible.

## Tecnologías de interfaz requeridas

- La interfaz web debe utilizar **HTML5, CSS3 y JavaScript**. Usa HTML semántico y accesible, CSS adaptable con variables para temas Claro/Oscuro y JavaScript para interacciones de la interfaz, estado del chat y actualización del tema.
- Si el repositorio ya utiliza un framework o compilador, integra HTML5/CSS3/JavaScript dentro de esa arquitectura en lugar de crear una segunda aplicación paralela o reemplazar herramientas existentes sin necesidad.
- El backend, las APIs y los servicios de tiempo real deben seguir la tecnología que revele el repositorio; no se deduce el backend únicamente de que el usuario use XAMPP.

## Entorno de trabajo confirmado

- El usuario trabaja con **Antigravity IDE** y **XAMPP** para el entorno local.
- Revisa el contenido real del repositorio y las versiones instaladas de PHP, servidor web y base de datos antes de decidir implementación, comandos o compatibilidad. No fijes versiones basándote solo en el nombre XAMPP.
- Escribe instrucciones y entregas que el usuario pueda ejecutar desde Antigravity IDE y su instalación local de XAMPP cuando corresponda.

## Decisiones confirmadas

- La plataforma será independiente; no depende de Telegram ni de Zoom.
- La clase es en vivo y admite más de 1.000 asistentes.
- El anfitrión comparte **su pantalla** para enseñar.
- Los alumnos solo ven la transmisión y participan por chat.
- No se graban ni se guardan las clases para verlas posteriormente.
- Cada alumno recibe su propio código de acceso.
- Cada código admite una sola sesión activa. Si el alumno entra desde otro dispositivo, la sesión anterior se cierra y el dispositivo nuevo queda activo, como en Netflix.
- El anfitrión puede habilitar o deshabilitar la escritura en el chat.
- El anfitrión puede designar moderadores; anfitrión y moderadores pueden gestionar el chat y expulsar o bloquear asistentes.
- Marca: **AVILA CALL**.
- Estilo recomendado: profesional, claro y adaptable a móviles. Azul marino como base, blanco para superficies y texto, turquesa como acento. Evita usar verde/rojo como colores dominantes o como si la interfaz diera señales de compra o venta.

## Supuesto funcional que debe hacerse visible

La clase necesita la voz del profesor para explicar lo que muestra. Por ello, transmite la pantalla compartida y el audio del micrófono del anfitrión; **no transmite cámara del anfitrión ni audio, cámara o pantalla de los alumnos**. Si el producto existente no permite separar estas fuentes, documenta la limitación antes de elegir otra solución.

## Roles

1. **Administrador:** gestiona anfitriones, clases y códigos individuales; puede revocar accesos y revisar la lista de asistentes.
2. **Anfitrión:** inicia y termina su clase, comparte pantalla, habilita/cierra el chat, nombra moderadores y expulsa o bloquea asistentes.
3. **Moderador:** puede moderar mensajes y expulsar/bloquear asistentes, pero no cambiar ajustes de la clase ni transferir el rol de anfitrión.
4. **Alumno:** entra con su código individual, ve la clase en vivo y escribe cuando el anfitrión habilita el chat. No publica audio, video ni pantalla.

Aplica autorización en el servidor para cada acción; ocultar un botón no constituye control de acceso.

## Flujos requeridos

### Administrar clases y accesos

- Crear una clase con nombre, descripción opcional, anfitrión asignado y estado (programada, en vivo, finalizada).
- Generar un código distinto, impredecible y revocable para cada alumno autorizado. El administrador puede asignar el código a un nombre o correo para reconocer al alumno, sin obligar a recopilar datos innecesarios.
- Mostrar al administrador el estado de cada código: disponible, conectado, revocado o vencido, según las reglas implementadas.
- Permitir revocar y regenerar un código. Un código revocado no puede entrar aunque el alumno conserve el enlace.
- No exponer códigos en URLs, mensajes de error, analítica ni logs. Guardar una representación segura del código cuando sea viable; no almacenar secretos en texto plano sin necesidad.

### Entrar a la clase

- El alumno introduce su código en una página de acceso sencilla y recibe errores que no revelen si un código parcialmente parecido existe.
- Validar que el código pertenece a esa clase, está activo y la clase admite ingresos.
- Al autenticar una segunda sesión para el mismo código, invalidar atómicamente la sesión anterior y admitir la nueva. La sesión antigua debe perder el acceso al video y al chat en tiempo real, no solo mostrar un aviso.
- La transición debe tolerar reconexiones breves de red sin expulsar al alumno de forma errática. Usa una concesión breve con heartbeat/lease para detectar sesiones abandonadas. Define y documenta la ventana de reconexión.
- No permitir que dos dispositivos usen simultáneamente el mismo código. Controlar carreras concurrentes en backend/almacenamiento compartido; no depender solo del estado en memoria del proceso web.
- Limitar intentos de códigos, aplicar demoras o bloqueo temporal y evitar enumeración automatizada.

### Emitir y ver la clase

- Solo el anfitrión asignado puede iniciar o terminar la transmisión y compartir pantalla.
- Solicitar permiso de pantalla mediante el mecanismo seguro del navegador; indicar claramente cuándo se está compartiendo y permitir detenerla.
- Enviar pantalla y micrófono del anfitrión a los alumnos. No solicitar permisos de cámara o micrófono a los alumnos.
- Los alumnos pueden ver el estado de conexión y recibir un mensaje útil si la clase aún no empezó, terminó o perdió temporalmente la transmisión.
- Mostrar nombre de la clase y anfitrión, número aproximado de asistentes y estado en vivo.
- No crear grabación, repetición, archivo de video ni reproducción posterior. No habilitar grabación del lado del servidor o del proveedor por defecto. Si la solución de medios guarda material temporalmente, documentar esa retención y minimizarla; el producto no debe ofrecerlo como grabación de clase.

### Chat y moderación

- El chat tiene estado visible: abierto o cerrado. El anfitrión puede cambiarlo en vivo; el estado se aplica a todos sin refrescar la página.
- Al estar cerrado, los alumnos pueden leer los mensajes disponibles, pero el servidor rechaza nuevos mensajes. La validación no puede depender solo del frontend.
- Soportar moderadores nombrados por el anfitrión durante la clase y revocar ese rol.
- Anfitrión y moderadores pueden borrar mensajes, silenciar temporalmente, expulsar de la clase y bloquear el código de acceso, con confirmación para acciones permanentes.
- Registrar las acciones administrativas esenciales (actor, acción, objetivo y hora) sin guardar contenido sensible innecesario.
- Limitar frecuencia y longitud de mensajes, escapar contenido y prevenir XSS, spam e inundación del chat. No renderizar HTML enviado por alumnos.
- Los alumnos expulsados no pueden volver a entrar con el mismo código mientras dure la expulsión; los bloqueados no vuelven a entrar hasta que un administrador/anfitrión autorizado levante el bloqueo.

## Requisitos de escala y arquitectura

- Diseñar para más de 1.000 espectadores simultáneos en una sola clase, incluyendo video, audio, chat, presencia y eventos de moderación.
- No asumir que una única instancia PHP/Node o una conexión de videollamada grupal punto a punto puede sostener ese aforo.
- Antes de implementar medios, evaluar una arquitectura de transmisión para un emisor y muchos espectadores con distribución escalable (por ejemplo, WebRTC con SFU/CDN o transmisión de baja latencia equivalente). Elegir tras verificar límite concurrente, latencia, compatibilidad de navegadores, costes, región, privacidad, capacidad de screen share, controles de grabación y recuperación ante fallos. No fijar un proveedor o precio sin validarlo en el momento de desarrollo.
- Separar la capa de medios de la lógica de clase mediante un adaptador para poder cambiar el proveedor sin rehacer roles, códigos y chat.
- Usar infraestructura compartida y persistente para sesiones, revocaciones, estado del chat y presencia; debe funcionar con varias instancias de aplicación.
- Distribuir eventos de chat/control mediante un mecanismo en tiempo real escalable y almacenamiento efímero/persistente acorde a retención definida. No guardar la clase como video.
- Realizar una prueba de carga que simule más de 1.000 espectadores y mensajes concurrentes antes de afirmar que el sistema soporta ese volumen. Informar resultados medidos, configuración y límites. No declarar certificada la capacidad si no se probó con el proveedor y entorno de despliegue elegidos.
- Definir comportamiento para caída del proveedor de video, pérdida de conexión del anfitrión, reinicio del servidor y reconexión masiva.

## Diseño visual

- Paleta inicial sugerida:
  - Azul marino `#10243A` para navegación y encabezados.
  - Azul profundo `#173B57` para superficies secundarias.
  - Blanco `#FFFFFF` y gris muy claro `#F4F7FA` para contenido.
  - Turquesa `#20B8A5` para acciones principales y estado de clase en vivo.
  - Gris oscuro `#263746` para texto; estados de error con rojo sobrio solo cuando corresponda.
- Incluir un control visible **Claro/Oscuro** accesible desde la cabecera o menú principal. Al alternarlo, actualizar toda la interfaz (fondos, textos, tarjetas, formularios, bordes, chat, controles y estados), no solo el fondo.
- El tema Claro debe usar fondos blancos/gris claro y texto oscuro; el tema Oscuro debe usar azul marino/grafito y texto claro, conservando el turquesa como acento. Mantener contraste suficiente en ambos temas.
- Guardar la preferencia de tema para que se conserve al volver a entrar; usar el perfil del usuario si existe, o almacenamiento local del navegador en la primera versión. Si no hay preferencia guardada, respetar inicialmente el tema del sistema operativo.
- El control debe anunciar su estado a tecnologías de asistencia, funcionar con teclado y mostrar claramente el tema activo.
- Asegurar contraste, tipografía legible y controles grandes para uso móvil.
- La pantalla de alumno prioriza el video, el chat y el estado del chat; debe adaptarse a móvil y escritorio.
- La pantalla del anfitrión prioriza previsualización/estado de pantalla compartida, controles de clase y chat/moderación.
- Mostrar con claridad cuándo el chat está cerrado y cuándo la clase está en vivo.
- No hacer que el producto parezca una plataforma de ejecución de operaciones ni mostrar recomendaciones financieras automatizadas.

## Seguridad, privacidad y operación

- Usar HTTPS, cookies seguras, protección CSRF donde aplique, validación de entradas y controles de autorización en servidor.
- Proteger códigos contra fuerza bruta, reutilización revocada, fuga en logs, enumeración y robo de sesión.
- Invalidar sesiones al revocar códigos, expulsar/bloquear, finalizar clase o detectar una nueva sesión del mismo alumno.
- Separar datos por clase y evitar que asistentes de una clase accedan a chat, listas o eventos de otra.
- No almacenar datos de tarjetas/cuentas de trading. No solicitar credenciales de brokers.
- Mostrar aviso antes de entrar que indique que se trata de una transmisión en vivo, que el profesor comparte pantalla y que la clase no se ofrece como grabación. No afirmar que la captura del alumno sea técnicamente imposible; explicar límites de protección del contenido de manera honesta.
- Definir retención del chat y registros de moderación. Como no se graban las clases, no retener datos de clase más de lo necesario. Documentar consentimiento y obligaciones aplicables a la jurisdicción del operador antes de publicar.
- Añadir alertas operativas para errores de streaming, tasa de reconexiones, fallos de chat y picos de acceso, sin incluir secretos ni contenido privado en telemetría.

## Entregables de implementación

1. Inventario inicial del proyecto y decisiones técnicas propuestas.
2. Esquema de datos/migraciones para usuarios/roles, clases, códigos de acceso, sesiones activas, moderación y estado de clase/chat. Ajustarlo al stack existente.
3. Flujos funcionales de administración, anfitrión, moderador y alumno.
4. Integración de transmisión en vivo encapsulada, sin grabación de clase.
5. Pruebas de autorización, códigos, exclusividad de sesión, chat abierto/cerrado, revocación, expulsión/bloqueo y reconexión.
6. Prueba de carga documentada para el objetivo de más de 1.000 espectadores, o una limitación clara si el entorno/proveedor no puede probarlo.
7. Instrucciones para instalación, configuración de secretos, despliegue y operación. Nunca incluir claves reales en el repositorio.
8. Resumen de archivos modificados, migraciones, pruebas ejecutadas, resultados y riesgos que sigan abiertos.

## Criterios de aceptación

- Un alumno autorizado entra a su clase con su código individual; un código revocado o inválido no entra.
- Si ese mismo alumno abre sesión en un segundo dispositivo, el primero pierde video y chat y el segundo permanece activo.
- Cien intentos simultáneos con el mismo código no generan dos sesiones válidas.
- El anfitrión comparte pantalla y voz; los alumnos pueden ver y escribir solo cuando el chat está abierto.
- Los alumnos no pueden publicar audio, video ni pantalla.
- Cerrar chat en el anfitrión bloquea el envío también en backend; abrirlo lo restablece en tiempo real.
- Moderadores pueden realizar únicamente las acciones permitidas; alumnos no pueden invocar acciones de anfitrión/moderación modificando solicitudes.
- Expulsar o bloquear impide el reingreso según la política definida.
- La clase termina sin ofrecer una grabación o repetición posterior.
- La interfaz es utilizable en teléfono y escritorio y muestra claramente estados, errores y controles.
- La aplicación usa HTML5, CSS3 y JavaScript para la experiencia web, integrados correctamente con el stack existente.
- El control Claro/Oscuro cambia correctamente todas las vistas, funciona con teclado y conserva la preferencia después de recargar o volver a iniciar sesión según el mecanismo elegido.
- La capacidad superior a 1.000 se considera demostrada solo con una prueba de carga documentada en la arquitectura de despliegue elegida.

## Forma de trabajo del agente

- Avanza por fases pequeñas: inspección, diseño técnico, esquema, autenticación/códigos, clase/transmisión, chat/moderación, interfaz, pruebas de carga y certificación.
- No cambies decisiones comerciales confirmadas ni agregues funciones como grabaciones, micrófonos de alumnos o cámara de alumnos sin autorización.
- Si el proveedor de video o los límites de coste impiden cumplir una especificación, entrega evidencia concreta y alternativas compatibles antes de cambiar el alcance.
- No declares terminado un flujo que solo tenga interfaz simulada: identifica explícitamente cualquier servicio pendiente de configurar.
