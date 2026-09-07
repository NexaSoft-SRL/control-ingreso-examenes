# Dependencias entre módulos

## Objetivo

El backend se organiza como un monolito modular.

Cada módulo conserva la propiedad de su lógica y sus datos. Una dependencia entre módulos debe existir únicamente cuando el flujo funcional la requiere y debe respetar la dirección establecida en este documento.

Las dependencias no indicadas están prohibidas.

## Matriz

| Origen | Administracion | Estudiantes | Examenes | Habilitacion | Ingreso | Monitoreo | Reportes |
|---|---|---|---|---|---|---|---|
| Administracion | — | No | No | No | No | No | No |
| Estudiantes | Sí | — | No | No | No | No | No |
| Examenes | Sí | Sí | — | No | No | No | No |
| Habilitacion | Sí | Sí | Sí | — | No | No | No |
| Ingreso | Sí | Sí | Sí | Sí | — | No | No |
| Monitoreo | Sí | Sí | Sí | Sí | Sí | — | No |
| Reportes | Sí | Sí | Sí | Sí | Sí | No | — |

## Justificación

Administracion concentra autenticación, autorización, usuarios, roles, permisos y auditoría.

Estudiantes mantiene el padrón y la carga masiva.

Examenes utiliza información académica y de estudiantes cuando una regla o asignación lo requiere.

Habilitacion relaciona estudiantes con exámenes y conserva su condición y motivo de inhabilitación.

Ingreso constituye el núcleo operacional: identifica al estudiante, verifica habilitación y ambiente, registra el ingreso y las incidencias.

Monitoreo explota los datos de ingreso y habilitación para mostrar ingresados, faltantes y avance por ambiente.

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
