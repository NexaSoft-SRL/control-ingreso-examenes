# Modelo de datos

## 1. Estado del documento

Este documento describe el diseño de datos del sistema de gestión, validación y control
del ingreso de estudiantes a exámenes masivos.

El diseño se elaboró durante el Sprint 0 como propuesta y se implementó entre los sprints
1 y 5. **Las migraciones son la fuente de verdad del esquema**; este documento explica por
qué el esquema quedó así y qué se decidió en cada punto discutido.

Cada decisión de la sección 6 indica cómo cerró: qué se implementó, qué se resolvió de
otra manera que la propuesta original y qué sigue abierto a la espera de una definición
funcional de TIS.

## 2. Principios

El modelo debe:

- preservar integridad referencial;
- impedir inconsistencias críticas también bajo concurrencia;
- mantener trazabilidad de las operaciones relevantes;
- evitar duplicación de hechos;
- permitir incorporar estudiantes, exámenes y ambientes sin cambios estructurales;
- soportar identificación rápida por QR, código universitario y documento;
- conservar los registros históricos necesarios para auditoría y reportes.

## 3. Entidades y tablas

Correspondencia entre las entidades conceptuales del diseño y las tablas implementadas.
Los conceptos que no aparecen como tabla propia se resolvieron dentro de otra entidad, y
la sección 6 explica por qué.

### Administración

| Concepto | Tabla | Nota |
|---|---|---|
| Usuario | `users` | Bloqueo por intentos fallidos y cambio obligatorio de contraseña (HU-01) |
| Rol | `roles` | Los de sistema no se eliminan (`es_sistema`) |
| Permiso | `permissions` | Un permiso por pantalla |
| Rol-Permiso | `permission_role` | Con vigencia opcional (`vigente_desde`, `vigente_hasta`) |
| Bitácora | `bitacora_operaciones` | Hechos de auditoría, no estado (HU-07) |
| Intentos de acceso | `login_attempts` | Soporte del bloqueo de HU-01 |
| Ambiente | `ambientes` | Catálogo de aulas, con capacidad y estado (HU-06) |

### Estudiantes

| Concepto | Tabla | Nota |
|---|---|---|
| Estudiante | `students` | Baja lógica con `activo`; nunca se borra (HU-03) |
| ParticipacionExamen | — | No se implementó: la pertenencia al examen la expresa `habilitaciones_examen` (ver D-02) |

### Exámenes

| Concepto | Tabla | Nota |
|---|---|---|
| Docente | `docentes` | Vínculo opcional y único con `users` (D-01) |
| Asignatura | `asignaturas` | |
| Grupo | `grupos_asignatura` | Docente responsable por grupo, no por asignatura (D-08) |
| Examen | `examenes` | Único por grupo, fecha y hora de inicio (HU-08) |
| ExamenAmbiente | `examen_ambiente` | Aulas habilitadas para el examen (HU-10) |
| NormaExamen | `normas_examen` | Generales y particulares en una sola tabla, con `alcance` (D-06) |
| NormaEstudianteExamen | — | Es una fila de `normas_examen` con `alcance = PARTICULAR` y `student_id` |

### Habilitación

| Concepto | Tabla | Nota |
|---|---|---|
| Habilitación | `habilitaciones_examen` | Única por examen y estudiante (HU-11) |
| MotivoInhabilitación | — | Columna `motivo` de la habilitación, texto libre obligatorio (D-02) |
| AsignaciónAmbiente | — | Columna `ambiente_id` de la habilitación (D-02) |
| CódigoQR | `codigos_qr` | Un token opaco por examen y estudiante (D-05) |

### Ingreso

| Concepto | Tabla | Nota |
|---|---|---|
| Ingreso | `ingresos_examen` | Con la anulación en las mismas columnas (D-03, D-07) |
| AnulaciónIngreso | — | Columnas `anulado_en`, `anulado_por` y `motivo_anulacion` del ingreso |
| Incidencia | `incidencias_ingreso` | Lo que el sistema rechazó en la puerta (D-04) |
| Expulsión | `expulsiones` | Tabla propia, colgada del ingreso previo (D-04, HU-24) |
| Situación excepcional | `situaciones_excepcionales` | Lo que el personal resolvió a mano (D-04, HU-25) |

### Tablas de trabajo de Laravel

`sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches` y
`failed_jobs` existen en el esquema pero no son del dominio: las crea el framework para
sostener sesiones, colas y caché. No se respaldan (HU-35) ni se documentan aquí como
entidades.

### Monitoreo, reportes y respaldo

No agregan tablas. El tablero (HU-27), la lista de presentes y ausentes (HU-28) y los
cinco reportes (HU-29 a HU-33) son consultas de solo lectura sobre lo anterior. Los
respaldos (HU-35) son archivos SQL en `storage/app/respaldos`, no filas.

## 4. Relaciones

Un docente puede ser responsable de varios grupos de asignatura, y un grupo tiene un solo
docente responsable.

Una asignatura puede tener varios grupos, y cada grupo, varios exámenes.

Un examen puede utilizar varios ambientes y un ambiente puede ser utilizado por varios
exámenes en distintos momentos. Esa relación es `examen_ambiente`.

Un examen puede tener varias normas. Una norma de alcance particular se limita además a
un estudiante.

La condición del estudiante respecto a un examen es `habilitaciones_examen`, única por
examen y estudiante. La ausencia de fila significa que **nadie registró condición** para
ese estudiante y examen, y es distinta de una condición negativa: el punto de control las
distingue y el reporte de HU-33 las cuenta por separado.

El ambiente asignado a un estudiante es una columna de su habilitación, no una entidad
aparte, y puede quedar nulo mientras no se haya distribuido (HU-14).

`codigos_qr` identifica una relación estudiante-examen en la puerta. No es evidencia de
habilitación: después de resolver el token, el sistema comprueba igual la condición y el
ambiente.

Un ingreso corresponde al acceso efectivo de un estudiante a un examen. Guarda examen y
estudiante directamente ---no a través de la habilitación (D-03)---, el ambiente por el
que entró, el medio con que se lo identificó, quién lo registró y la hora que puso el
servidor.

Una incidencia registra lo que el sistema rechazó en la puerta; puede existir sin ingreso
previo y, en el caso de un problema de identificación, incluso sin estudiante. Una
expulsión, en cambio, cuelga obligatoriamente de un ingreso previo. Una situación
excepcional registra lo que una persona resolvió, con su motivo.

## 5. Reglas funcionales implementadas

Provienen del pliego, del backlog o de la propuesta técnica, y todas están cubiertas por
pruebas automatizadas:

- el estudiante se identifica por código universitario, documento de identidad o código
  QR (HU-15, HU-16, HU-18);
- antes del ingreso se comprueba la habilitación (HU-19) y el ambiente que le corresponde
  (HU-20);
- la fecha y la hora del ingreso las pone el servidor y el cliente no puede enviarlas
  (HU-21);
- un estudiante no registra más de un ingreso vigente para el mismo examen, ni siquiera
  con dos puestos de control operando a la vez (HU-22);
- los intentos de ingreso a exámenes que no corresponden quedan registrados, distinguiendo
  al no registrado del inhabilitado (HU-23);
- las expulsiones conservan motivo y hora, y cierran la puerta de ese examen (HU-24);
- las situaciones excepcionales ---problema de identificación, cambio de ambiente,
  estudiante no habilitado--- quedan registradas con su motivo (HU-25);
- las operaciones relevantes conservan usuario, fecha y hora en la bitácora (HU-07);
- una corrección de ingreso conserva el registro original mediante una anulación
  registrada (HU-21);
- la capacidad de un ambiente no puede quedar por debajo de los estudiantes ya asignados
  en exámenes por rendirse (HU-06 con las tablas de HU-08 y HU-14).

## 5.1 Invariantes del modelo

- una sola habilitación por estudiante y examen ---índice `uq_habilitacion_examen_estudiante`---;
- un solo ambiente asignado por estudiante y examen, porque es una columna de esa fila
  única;
- un solo ingreso vigente por estudiante y examen, conservando los anulados ---índice
  parcial `uq_ingreso_vigente_por_estudiante`---;
- una sola expulsión por ingreso ---índice `uq_expulsion_por_ingreso`---;
- un solo código QR por estudiante y examen ---índice `uq_codigo_qr_examen_estudiante`---;
- un examen único por grupo, fecha y hora de inicio;
- la bitácora representa hechos de auditoría y no el estado actual de las entidades.

## 6. Decisiones de diseño

### D-01 — Docente y usuario — IMPLEMENTADA

Docente se mantiene como entidad académica independiente de Usuario, con un vínculo
opcional y único (`docentes.user_id`, con índice único).

Permite registrar docentes antes de provisionarles una cuenta y evita acoplar la
información académica al mecanismo de acceso. El alta de docente se hace desde la pantalla
de usuarios y roles, con ese permiso (HU-02).

### D-02 — Semántica de Habilitación — IMPLEMENTADA, CON DOS SIMPLIFICACIONES

La habilitación es la condición evaluada de un estudiante respecto a un examen, única por
esa combinación. La ausencia de fila no equivale a una inhabilitación, y el sistema
distingue los dos casos en la puerta (HU-19) y en los reportes (HU-33).

Dos conceptos que el diseño preveía como entidades propias quedaron dentro de la
habilitación:

- **MotivoInhabilitación no es un catálogo.** Se implementó como texto libre obligatorio
  cuando la condición es negativa (HU-12). Un catálogo cerrado obligaría a mantener una
  tabla de motivos que nadie pidió administrar, y las revisiones mostraron motivos
  redactados caso por caso. Si TIS pide después consolidar motivos, la migración es
  agregar la tabla y una columna opcional, sin tocar lo registrado.
- **AsignaciónAmbiente no es una tabla.** Es la columna `ambiente_id` de la habilitación.
  Como la habilitación ya es única por estudiante y examen, esa columna garantiza sola el
  invariante «un solo ambiente asignado por estudiante y examen», sin una tabla adicional
  ni una restricción extra.

`ParticipacionExamen`, que el diseño usaba para representar de forma neutral la
pertenencia al examen, no se implementó: la habilitación cumple ese papel, y una fila
neutral aparte habría duplicado la clave estudiante-examen sin agregar información.

### D-03 — Unicidad e integridad de Ingreso — IMPLEMENTADA, DISTINTO DE LA PROPUESTA

El diseño proponía que Ingreso referenciara a Habilitación para no duplicar estudiante y
examen. **Se implementó con `examen_id` y `student_id` directos.** Razones:

- el punto de control necesita registrar hechos de estudiantes que **no tienen**
  habilitación ---las incidencias de HU-23---, y hacer que unas tablas cuelguen de la
  habilitación y otras no habría partido el modelo en dos formas de nombrar lo mismo;
- todas las consultas del monitoreo y de los reportes parten del par examen-estudiante;
  llegar a él por un salto adicional encarecía cada una sin beneficio.

La unicidad quedó resuelta por D-07: como una anulación permite registrar un ingreso
nuevo, una restricción simple habría impedido conservar el historial. Se implementó un
**índice único parcial** de PostgreSQL:

```sql
CREATE UNIQUE INDEX uq_ingreso_vigente_por_estudiante
ON ingresos_examen (examen_id, student_id)
WHERE anulado_en IS NULL
```

Permite varios ingresos históricos para el mismo par y como máximo uno vigente, y
sostiene la regla bajo concurrencia sin depender de la comprobación previa del código
(HU-22).

Queda **una regla en la aplicación y no en el esquema**: que no se registre un ingreso de
un estudiante cuya condición sea negativa. La comprueba la acción `RegistrarIngreso`
antes de escribir, y existe la excepción deliberada del ingreso autorizado a mano, que se
asienta como situación excepcional (HU-25). Llevarla a una restricción física exigiría
duplicar la condición dentro del ingreso, y ese dato copiado podría quedar desactualizado.

### D-04 — Incidencias — IMPLEMENTADA COMO TRES TABLAS

El diseño preveía una sola entidad Incidencia con un tipo y contexto variable. Se
implementó separando **quién escribe el hecho**, que es lo que hace que las columnas
obligatorias sean distintas:

| Tabla | Quién la escribe | Qué exige |
|---|---|---|
| `incidencias_ingreso` | el sistema, al rechazar | tipo, examen, hora; estudiante opcional |
| `expulsiones` | el personal, sobre un ingreso | ingreso previo, motivo y hora obligatorios |
| `situaciones_excepcionales` | el personal, a mano | tipo, motivo obligatorio; estudiante opcional |

Una sola tabla habría necesitado columnas obligatorias para unos tipos y nulas para
otros, que es exactamente la ambigüedad que el diseño quería evitar. Separarlas permite
además que la expulsión tenga su clave foránea al ingreso y su índice único, en vez de
una restricción condicional por tipo.

El cambio de ambiente no guarda «ambiente anterior» y «nuevo»: el anterior es el de la
habilitación, que no se modifica, y el nuevo es el ambiente del ingreso. El dato no se
duplica y la comparación sigue siendo posible.

Las incidencias guardan además `ambiente_id` ---la puerta donde ocurrió el intento---,
que el reporte de HU-33 usa para filtrar.

### D-05 — Código QR — IMPLEMENTADA, CON ALCANCE REDUCIDO

El código es un token opaco de 48 caracteres al azar, único en la tabla y único por
examen y estudiante. No expone datos personales y no es evidencia de habilitación: al
resolverlo, el sistema comprueba igual la condición, el ambiente y el ingreso previo
(HU-18).

Dos puntos del diseño quedaron **fuera de alcance por ahora**, y conviene decirlo en vez
de simular que se cumplieron:

- **No se almacena una representación criptográfica del token**, sino el token mismo. La
  hoja de códigos (HU-17) se reimprime cuantas veces haga falta, y eso exige poder
  reproducir el código exacto. Guardar solo un hash obligaría a regenerar el código en
  cada reimpresión e invalidar los ya repartidos.
- **No hay revocación ni expiración.** Emitir dos veces no duplica ni cambia el código
  vigente. Si TIS pide revocar, el cambio es agregar una columna de estado y mover el
  índice único a los códigos activos, sin perder trazabilidad.

### D-06 — Normas del examen — IMPLEMENTADA EN UNA TABLA CON ALCANCE

Las normas generales y las particulares comparten la tabla `normas_examen` y se
distinguen por `alcance` (`GENERAL` o `PARTICULAR`). La norma particular lleva además
`student_id` y el motivo por el que se la otorga (HU-09).

Dos tablas habrían duplicado el texto, el autor y las mismas consultas. La regla ---que
una particular exige estudiante y una general no--- la sostiene la validación de la
petición y está cubierta por pruebas.

Sigue **sin definir con TIS** si una norma particular es adicional, una excepción o una
sustitución de la general. El sistema las muestra juntas y no presupone precedencia, que
es lo único que se puede afirmar con lo que hay.

No se introdujo un catálogo global de normas reutilizables entre exámenes: nada en los
requisitos lo justifica todavía.

### D-07 — Anulación y nuevo ingreso — RESUELTA

**La anulación invalida el ingreso original y permite registrar después uno nuevo**
(opción 2). El registro original se conserva con su hora, su autor y el motivo de la
anulación; el estudiante queda libre para volver a ingresar, y en la lista figuran los dos
hechos.

Es lo que exige el caso real que la originó: se registró al estudiante equivocado y hay
que corregirlo sin borrar nada. De aquí sale el índice único parcial de D-03.

En la lista de presentes y ausentes (HU-28), un ingreso anulado devuelve al estudiante a
la situación de no presentado.

### D-08 — Responsabilidad docente y asignatura — RESUELTA

La responsabilidad corresponde al **grupo de la asignatura**, no a la asignatura ni al
examen: `grupos_asignatura.docente_id`. Una asignatura puede tener varios grupos, cada uno
con su docente, que es como trabaja la facultad y lo que confirmaron las revisiones.

El examen cuelga del grupo, así que su docente responsable se deduce sin duplicar el dato.

### D-09 — Gestión académica — SIGUE ABIERTA

No se incorporó `GestiónAcadémica` al modelo. Los exámenes conservan su fecha, y los
reportes se acotan por rango de fechas (HU-32), que cubre las consultas conocidas hasta
hoy.

La decisión pendiente es si hace falta una entidad propia para conservar la oferta
académica entre gestiones ---asignaturas, grupos y docentes que cambian de un semestre a
otro---. Incorporarla después implica agregar la entidad y una referencia en grupos y
exámenes; no obliga a rehacer lo registrado.

## 7. Restricciones implementadas

Restricciones vigentes en PostgreSQL:

- unicidad de código universitario y de documento de identidad en `students`;
- unicidad de nombre de ambiente y de código de asignatura;
- unicidad de habilitación por examen y estudiante;
- un solo ingreso vigente por examen y estudiante ---índice único parcial---;
- una sola expulsión por ingreso;
- un solo código QR por examen y estudiante, y token único en toda la tabla;
- un examen único por grupo, fecha y hora de inicio;
- un ambiente no se repite dentro del mismo examen (`uq_examen_ambiente`);
- claves foráneas con `restrict` donde borrar destruiría historia ---ingresos sobre
  estudiantes y exámenes--- y `null on delete` donde el dato es contextual ---ambiente,
  autor de un registro---.

Reglas sostenidas por la aplicación, con su prueba, por las razones explicadas en las
decisiones correspondientes:

- obligatoriedad del motivo cuando la habilitación es negativa (HU-12);
- ausencia de motivo cuando la condición es positiva (HU-12);
- prohibición de registrar el ingreso de un estudiante inhabilitado, salvo excepción
  registrada (HU-19, HU-25);
- obligatoriedad de un ingreso previo para una expulsión (HU-24);
- posibilidad de registrar un problema de identificación sin estudiante (HU-25);
- el ambiente asignado a un estudiante pertenece a los ambientes del examen (HU-14);
- la capacidad de un ambiente no baja de los estudiantes ya asignados (HU-06);
- la bitácora no se edita: no existe ruta que la modifique (HU-07).

## 8. Índices

Además de los índices que respaldan las restricciones anteriores:

- `idx_ingreso_examen_estudiante` sobre `ingresos_examen (examen_id, student_id)`;
- `idx_incidencia_examen_tipo` sobre `incidencias_ingreso (examen_id, tipo)`;
- `idx_situacion_examen_ambiente` sobre `situaciones_excepcionales (examen_id, ambiente_id)`;
- los de las claves foráneas que crean las migraciones.

Las búsquedas que los justifican son las de la puerta ---estudiante por código o
documento, habilitación por estudiante y examen--- y las del monitoreo y los reportes
---ingresos por examen y ambiente, incidencias por examen, tipo y fecha, bitácora por
usuario, operación y fecha---.

No se crearon índices redundantes con restricciones UNIQUE o claves primarias.

## 9. Fuera de alcance

El sistema no administra contenido del examen, respuestas, corrección ni calificaciones.

Su responsabilidad funcional concluye en el control y trazabilidad del ingreso, las
situaciones excepcionales relacionadas y la información necesaria para monitoreo y
reportes.
