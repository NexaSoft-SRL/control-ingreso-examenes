# Modo sin conexión de la puerta

Pedido de la consultora en la decimocuarta revisión (10 de noviembre de 2026): si se cae
internet en el examen, el control no debe parar, también cuando hay **varios puestos**.

## Principio

Cada puesto **decide con lo que sabe** y guarda lo que hizo. Cuando vuelve la conexión, el
servidor **revisa** lo ocurrido y le muestra al docente lo que parece irregular para que lo
resuelva a mano. El sistema no oculta los choques: los detecta y los asienta.

Con un solo puesto casi no hay choques. Con varios, dos puestos pueden admitir al mismo
estudiante sin saberlo; eso es justo lo que se detecta y se revisa después.

## Flujo

1. **Preparar** (con conexión). El puesto descarga una *instantánea* del aula: los exámenes
   del día, sus inscritos con su condición (habilitado o no, y por qué), quién ya ingresó y las
   claves de los QR. El servidor anota que ese dispositivo descargó (sesión `DESCARGADA`).
2. **Trabajar sin conexión.** Si una verificación no llega al servidor, el puesto decide con
   la instantánea, con las mismas reglas de la puerta, y guarda cada evento en una cola.
3. **Sincronizar.** Al volver la conexión envía la cola. El servidor aplica cada evento una
   sola vez, crea los ingresos y las incidencias, y registra las irregularidades. La sesión
   pasa a `SINCRONIZADA`.
4. **Revisar.** El docente ve en «Sin conexión» las irregularidades del examen, las resuelve
   con una nota, y ve qué dispositivos nunca sincronizaron.

## Qué se guarda en el celular y qué no

| Sí | No |
|---|---|
| Código SIS, nombre y condición de cada inscrito | Carnet de identidad |
| Hash SHA-256 de cada QR (no el QR en claro) | Código QR en claro |
| Quién ya ingresó, hora y aula | Foto del estudiante |
| Horario de cada examen y hora del servidor al preparar | Correo del estudiante |

Con la instantánea robada no se puede fabricar un QR: solo se conocen sus hashes.

## Qué no se puede hacer sin conexión

Verificar el carnet, tomar la foto del intruso, registrar una situación excepcional, expulsar
y mostrar la foto del estudiante. Piden conexión y la pantalla lo dice.

## Casos y solución

| # | Caso | Qué hace el sistema | Irregularidad |
|---|---|---|---|
| 1 | Se cae internet, un solo puesto | Sigue con la instantánea y sincroniza después | — |
| 2 | Dos puestos admiten al mismo estudiante | El primero por hora crea el ingreso; el segundo no (el ingreso es único) y queda asentado con ambas aulas y horas | `INGRESO_DOBLE` (alta) |
| 3 | Se inhabilitó a alguien mientras el puesto estaba sin conexión y entró | Se asienta el ingreso (entró de verdad) y se marca para revisar | `ADMITIDO_NO_HABILITADO` (alta) |
| 4 | Se habilitó a alguien mientras estaba sin conexión y se lo rechazó | Se asienta el intento rechazado y se avisa que hoy figura habilitado | `RECHAZADO_HABILITADO` (media) |
| 5 | Se admitió a alguien que no está inscrito (instantánea desactualizada) | Se asienta el ingreso y se marca | `NO_INSCRITO_ADMITIDO` (alta) |
| 6 | El QR se emitió después de preparar y el puesto no lo conoce | Se envía el QR al servidor, que lo resuelve; si el estudiante estaba habilitado se avisa | `RECHAZADO_HABILITADO` (media) |
| 7 | QR no válido de verdad | Intento rechazado, como con conexión | — |
| 8 | El reloj del celular está mal | Se corrige con la diferencia medida contra el servidor al preparar; si la hora sigue fuera de la ventana del examen o en el futuro, el ingreso toma la hora de recepción | `HORA_DUDOSA` (baja) |
| 9 | La sincronización se corta a la mitad y se reintenta | Cada evento tiene un identificador: se aplica una sola vez | — |
| 10 | El puesto lee dos veces el mismo QR | Ingreso repetido, como con conexión | — |
| 11 | El celular se apaga o se pierde con eventos sin enviar | El servidor sabe que descargó y no sincronizó; cuando termina el examen se muestra al docente | `DISPOSITIVO_SIN_SINCRONIZAR` (alta) |
| 12 | Evento de un examen que no es del aula, o con datos que no existen | Se descarta y se asienta | `EVENTO_INVALIDO` (media) |
| 13 | La instantánea es de otro día | No se usa: hay que preparar de nuevo con conexión | — |
| 16 | Navegación privada, almacenamiento bloqueado o con poco espacio | El panel avisa «Navegación privada · se pierde al cerrar la ventana», «Este navegador no guarda datos» o que el navegador puede borrar lo guardado; se pide almacenamiento persistente. La detección de ventana privada es una estimación | — |
| 14 | Se recarga la página o se cierra el navegador | La instantánea y la cola persisten en el almacenamiento del navegador | — |
| 15 | Un puesto con conexión admite a alguien que otro, sin conexión, ya había admitido | Al sincronizar el segundo queda como `INGRESO_DOBLE` | `INGRESO_DOBLE` (alta) |

## Resolución por el docente

Cada irregularidad se resuelve con una **nota obligatoria** (mínimo 5 caracteres) y una
decisión: *confirmada* (lo ocurrido es válido) o *descartada* (error del puesto o de la
instantánea). Queda en la bitácora con quién la resolvió. El examen figura como «con modo
sin conexión usado» mientras tenga sesiones sincronizadas o pendientes.

## Seguridad

- La instantánea la entrega la misma ruta de la puerta: solo quien controla esa aula (cuenta
  con permiso o código de aula).
- No viajan carnets, fotos ni QR en claro.
- La sincronización exige el mismo alcance que la verificación, y cada evento se valida
  contra el servidor.
