# Instrucciones para agentes — AVILA CALL

## Antes de trabajar

1. Lee este archivo, `MEMORY.md` y `AVILA_CALL_SKILL.md` antes de analizar o modificar el proyecto.
2. Inspecciona el repositorio, la tecnología, las convenciones, la configuración y las pruebas existentes. No supongas un framework ni reemplaces una arquitectura que ya funcione.
3. Si hay instrucciones `AGENTS.md` en carpetas internas, léelas también y respeta su alcance.
4. Identifica qué requisito y flujo vas a tocar. Haz cambios pequeños, compatibles y verificables.

## Tecnologías de interfaz requeridas

- Construye la interfaz web con HTML5, CSS3 y JavaScript. Prioriza HTML semántico, accesibilidad y diseño adaptable.
- Integra estas tecnologías con el stack o framework ya presente; no dupliques la aplicación ni migres de framework sin necesidad y evidencia.
- Usa estilos compartidos y variables CSS para que los temas Claro/Oscuro cubran todas las pantallas.

## Entorno de trabajo del usuario

- IDE: Antigravity IDE.
- Entorno local: XAMPP.
- Inspecciona las versiones reales de PHP, Apache y MySQL/MariaDB y la configuración del proyecto antes de proponer comandos o dependencias. No supongas que una versión concreta está instalada.
- Incluye instrucciones que el usuario pueda seguir en Antigravity IDE y en su entorno local de XAMPP cuando aplique.

## Fuente de requisitos y decisiones

- `AVILA_CALL_SKILL.md` contiene el alcance funcional y los criterios de aceptación acordados para el producto.
- `MEMORY.md` contiene las decisiones de producto confirmadas y los puntos aún pendientes. Consúltalo para mantener continuidad.
- Si dos documentos parecen contradecirse, no inventes una regla nueva: registra la discrepancia en el informe y conserva los requisitos expresamente confirmados más recientes.
- No cambies decisiones de producto por conveniencia técnica. Si una decisión impide completar el trabajo, explica el límite y presenta alternativas antes de cambiar el alcance.

## Reglas del producto que no se deben alterar sin autorización

- AVILA CALL es una plataforma independiente de clases de trading en vivo.
- El objetivo es soportar más de 1.000 espectadores simultáneos por clase y demostrarlo con pruebas del despliegue elegido.
- El anfitrión comparte pantalla y voz. No se transmite su cámara por defecto.
- Los alumnos solo ven la clase y escriben en el chat cuando está abierto. No publican audio, video ni pantalla.
- No se graban ni se ofrecen repeticiones de las clases.
- Cada alumno tiene un código individual y una sola sesión activa. Un nuevo ingreso en otro dispositivo invalida la sesión anterior.
- El anfitrión controla el chat y puede nombrar moderadores; los permisos se validan en el servidor.
- Marca: AVILA CALL. Estilo recomendado: azul marino, blanco y turquesa, con diseño legible y adaptable.

## Seguridad y privacidad

- Nunca escribas claves, tokens, contraseñas ni datos personales reales en el repositorio, ejemplos, pruebas, logs o documentos.
- Valida permisos en servidor para cada operación; no confíes en controles de interfaz.
- Protege los códigos frente a fuerza bruta, enumeración, filtración y reutilización tras revocación.
- Evita que una clase pueda consultar datos, asistentes, eventos o chat de otra.
- No solicites credenciales de brokers ni datos financieros de los alumnos.
- No actives grabación, almacenamiento de video ni repetición como comportamiento predeterminado.
- Registra solo la información operativa necesaria y documenta su retención.

## Implementación y verificación

- Mantén la integración de medios desacoplada de la lógica de clase para permitir cambiar el proveedor.
- No declares que se soportan más de 1.000 espectadores sin una prueba de carga reproducible y documentada en la arquitectura elegida.
- Añade o ejecuta verificaciones relevantes para permisos, acceso por código, exclusividad de sesión, chat, moderación, reconexiones y aislamiento entre clases.
- Comprueba migraciones y compatibilidad con los datos existentes antes de aplicar cambios destructivos.
- Informa claramente qué está implementado, qué está simulado y qué depende de configurar un servicio externo.
- Al terminar, resume archivos cambiados, decisiones técnicas, comandos de prueba ejecutados, resultados y pendientes.

## Mantenimiento de memoria

- Actualiza `MEMORY.md` solo cuando una decisión estable haya sido confirmada o cambie por instrucción expresa del usuario.
- Distingue decisiones confirmadas, supuestos técnicos y preguntas abiertas.
- No guardes secretos, datos personales innecesarios, resultados transitorios de una sesión ni suposiciones como si fueran decisiones.
