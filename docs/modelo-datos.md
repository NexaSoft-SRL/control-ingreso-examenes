# Modelo de datos

Modelo preliminar derivado de los dieciséis requerimientos generales del pliego. Su
versión definitiva constituye parte del entregable de la etapa de especificación y diseño
y se acuerda con la empresa TIS conforme al punto 3.8 del pliego.

## 1. Entidades

| Entidad | Contenido |
|---|---|
| `estudiantes` | Código universitario, documento de identidad, nombres, apellidos, carrera y estado |
| `docentes` | Datos del docente responsable de la asignatura |
| `asignaturas` | Oferta académica de la gestión |
| `examenes` | Asignatura, fecha, hora de inicio, duración, tipo y normas aplicables |
| `ambientes` | Identificación, edificio, capacidad y estado |
| `asignaciones` | Correspondencia entre examen, ambiente y estudiantes asignados |
| `habilitaciones` | Estudiante, examen, condición y motivo de inhabilitación |
| `ingresos` | Habilitación, ambiente, usuario que registra, fecha y hora |
| `incidencias` | Intento no autorizado, problema de identificación, cambio de ambiente o expulsión, con motivo y hora |
| `usuarios`, `roles`, `permisos` | Control de acceso |
| `bitacora` | Registro de auditoría de las operaciones realizadas |

## 2. Restricciones implementadas en la base de datos

**Unicidad del ingreso.** Índice único sobre el par `(examen_id, estudiante_id)` en la
tabla `ingresos`, que impide el doble registro con independencia de la concurrencia de
las solicitudes. Da cumplimiento al requerimiento 8.

**Inmutabilidad.** La tabla `ingresos` no admite operaciones de actualización ni de
eliminación. Las correcciones se registran como anulaciones que conservan el asiento
original.

**Integridad referencial.** No se admite el registro de un ingreso sin habilitación
válida asociada.

## 3. Índices

La operación del punto de control resuelve una consulta por estudiante y examen en
condiciones de concurrencia y con requisito de tiempo de respuesta reducido. Se
establecen los siguientes índices:

- `estudiantes.codigo_universitario`
- `estudiantes.documento_identidad`
- `habilitaciones (examen_id, estudiante_id)`
- `ingresos (examen_id, estudiante_id)`, que implementa además la restricción de unicidad

## 4. Alcance

El modelo no comprende la gestión del examen en sí: contenido, respuestas, calificación
ni corrección. El sistema concluye su intervención con el registro del ingreso al
ambiente. Cualquier ampliación se tramita mediante orden de cambio, conforme al punto 3.8
del pliego.
