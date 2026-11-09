# ADR-0002: Módulo Academico para la oferta importada

## Estado

Aceptado.

## Contexto

El ADR-0001 fijó siete módulos. En ese reparto, las asignaturas, los grupos y los docentes vivían en `Examenes`, y los ambientes en `Administracion`, porque se registraban a mano desde sus pantallas.

El replanteo del sistema (`docs/replanteamiento/README.md`) cambia el origen de esos datos: los períodos, las facultades, las carreras, las asignaturas, los grupos, sus horarios, los docentes, los edificios y las aulas **se importan** de la oferta académica publicada por la universidad y no se editan a mano.

Esos datos dejan de pertenecer a un módulo consumidor:

- `Estudiantes` inscribe en grupos.
- `Examenes` arma un examen con grupos y aulas.
- `Habilitacion`, `Ingreso`, `Monitoreo` y `Reportes` muestran asignaturas, grupos, aulas y edificios.
- `Administracion` no depende de ningún módulo y no puede alojar datos que tienen sus propios casos de uso (importar, detectar períodos, activar la cuenta de un docente).

Dejarlos en `Examenes` obligaría a `Estudiantes` a depender de `Examenes`, en contra de la matriz de módulos. Dejarlos en `Administracion` mezclaría la seguridad del sistema con el catálogo académico.

## Decisión

Se agrega un octavo módulo oficial:

- Academico

Es dueño de las tablas `periodos`, `facultades`, `carreras`, `asignaturas`, `plan_estudios`, `docentes`, `edificios`, `aulas`, `grupos`, `horarios` e `importaciones_oferta`, de sus modelos y de la importación de la oferta.

Depende únicamente de `Administracion` (bitácora y cuentas, por sus contratos). Todos los demás módulos de negocio pueden depender de él:

Administracion <- Academico <- Estudiantes <- Examenes <- Habilitacion <- Ingreso <- Monitoreo, Reportes

Expone a los demás, como cualquier módulo, solo `Application/Contracts` y `Application/DTOs`. Los primeros contratos públicos son:

- `PeriodoGateway`: períodos vigentes y período principal.
- `AlcanceDocenteGateway`: qué docente corresponde a una cuenta y si un grupo es suyo.

Los modelos de `Academico` no se importan desde otro módulo. Un gateway de consulta de otro módulo que necesite sus datos los lee por nombre de tabla (`DB::table('grupos')`), como ya hacen `Monitoreo` y `Reportes` con las tablas ajenas.

Las aulas pasan a llamarse así en el código, las tablas y la interfaz. «Ambiente» queda solo en los documentos del diseño anterior.

## Consecuencias

`Examenes` pierde las asignaturas, los grupos y los docentes; `Administracion` pierde los ambientes y el padrón.

`docs/architecture/architecture-contract.json`, `deptrac.modules.php`, `dependency-rules.md` y `module-dependencies.md` declaran el módulo y su lugar en la matriz.

Cada módulo registra sus enlaces contrato → implementación en su `Infrastructure/Providers/<Módulo>ServiceProvider.php`; `deptrac.php` admite `Illuminate\Support\ServiceProvider` en `Infrastructure`.

La documentación C4 (`docs/arquitectura.md`) se actualiza al cierre del replanteo.
