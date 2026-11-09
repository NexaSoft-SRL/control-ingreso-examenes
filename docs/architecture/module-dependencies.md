# Dependencias entre módulos

## Objetivo

El backend se organiza como un monolito modular.

Cada módulo conserva la propiedad de su lógica y sus datos. Una dependencia entre módulos debe existir únicamente cuando el flujo funcional la requiere y debe respetar la dirección establecida en este documento.

Las dependencias no indicadas están prohibidas.

## Matriz

| Origen | Administracion | Academico | Estudiantes | Examenes | Habilitacion | Ingreso | Monitoreo | Reportes |
|---|---|---|---|---|---|---|---|---|
| Administracion | — | No | No | No | No | No | No | No |
| Academico | Sí | — | No | No | No | No | No | No |
| Estudiantes | Sí | Sí | — | No | No | No | No | No |
| Examenes | Sí | Sí | Sí | — | No | No | No | No |
| Habilitacion | Sí | Sí | Sí | Sí | — | No | No | No |
| Ingreso | Sí | Sí | Sí | Sí | Sí | — | No | No |
| Monitoreo | Sí | Sí | Sí | Sí | Sí | Sí | — | No |
| Reportes | Sí | Sí | Sí | Sí | Sí | Sí | No | — |

## Justificación

Administracion concentra autenticación, autorización, usuarios, roles, permisos, auditoría, respaldo y el registro de los correos enviados.

Academico mantiene la oferta importada: períodos, facultades, carreras, asignaturas, grupos, horarios, docentes, edificios y aulas (ADR-0002). Usa de Administracion la bitácora y las cuentas.

Estudiantes mantiene el padrón, las inscripciones en grupos, las cargas de inscritos y sus conflictos.

Examenes arma el examen de una asignatura con sus grupos, sus aulas, quién atiende cada una y los auxiliares.

Habilitacion relaciona a los inscritos con un examen: conserva su condición, el motivo de inhabilitación y el aula que les toca.

Ingreso constituye el núcleo operacional: emite los códigos QR, identifica al estudiante en la puerta, verifica habilitación y aula, registra el ingreso y las incidencias.

Monitoreo explota los datos de ingreso y habilitación para mostrar los exámenes en curso y la asistencia por aula.

Reportes explota los datos transaccionales de examen, habilitación e ingreso para producir asistencia, ausentes, horarios e intentos no autorizados.

Monitoreo y Reportes no dependen entre sí.

## Regla de integración

Una autorización en esta matriz no significa que cualquier clase interna de otro módulo sea automáticamente una API pública.

Las integraciones nuevas entre módulos deben preferir una superficie explícita en Application, especialmente Contracts y DTOs cuando corresponda.

Los módulos no consumen la capa Http de otros módulos.

La aparición de una necesidad que contradiga esta matriz requiere revisión arquitectónica antes de modificar Deptrac.

## Controles automatizados

La arquitectura se valida mediante dos vistas independientes de Deptrac.

`deptrac.php` controla las capas internas y las dependencias externas. En esta vista las dependencias `uncovered` deben hacer fallar el quality gate.

`deptrac.modules.php` controla exclusivamente los límites entre módulos. Las dependencias externas no pertenecen a esta vista y no se utiliza `--fail-on-uncovered` en este análisis.

La capa Http de cada módulo es privada para ese módulo. Que un módulo tenga autorización para consumir otro módulo no implica autorización para consumir sus Controllers, Requests, Resources o Middleware.
