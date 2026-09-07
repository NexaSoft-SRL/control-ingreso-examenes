# ADR-0001: Monolito modular como arquitectura interna

## Estado

Aceptado.

## Contexto

El sistema de control de ingreso a exámenes masivos será desarrollado por siete integrantes durante cinco sprints de construcción.

La solución contratada utiliza Laravel, React y PostgreSQL y se despliega como una única aplicación web sobre la infraestructura de TIS.

El sistema comprende siete módulos funcionales:

1. Gestión de estudiantes.
2. Gestión de exámenes y ambientes.
3. Habilitación.
4. Control de ingreso.
5. Monitoreo.
6. Reportes.
7. Administración y seguridad.

Una organización basada únicamente en carpetas globales como Controllers, Models y Services permitiría mezclar componentes de diferentes módulos conforme crezca el proyecto.

También aumentaría el riesgo de dependencias no controladas y dificultaría la revisión del código producido por varios desarrolladores.

## Decisión

La aplicación utilizará una arquitectura de monolito modular.

El código backend específico del negocio se organizará bajo:

app/Modules/

Los módulos oficiales son:

- Estudiantes
- Examenes
- Habilitacion
- Ingreso
- Monitoreo
- Reportes
- Administracion

Cada módulo podrá utilizar, cuando sean necesarias, las siguientes capas:

- Domain
- Application
- Infrastructure
- Http

No se crearán capas o directorios vacíos únicamente para completar una estructura visual.

Las carpetas se crearán cuando exista código real que requiera esa responsabilidad.

## Responsabilidades de las capas

### Domain

Contiene conceptos y reglas propias del negocio.

Puede contener:

- Models
- Enums
- Rules
- Exceptions
- ValueObjects

Domain no conoce HTTP, casos de uso de Application ni adaptadores externos.

Como decisión pragmática propia de esta arquitectura Laravel, los modelos persistentes del dominio pueden utilizar Eloquent. No se pretende implementar un dominio completamente independiente del framework ni duplicar cada entidad mediante modelos de persistencia y repositorios sin una necesidad concreta.

### Application

Contiene los casos de uso y la coordinación de las operaciones del sistema.

Puede contener:

- Actions
- Queries
- DTOs
- Contracts
- Authorization

Application depende de Domain.

### Infrastructure

Contiene implementaciones técnicas dependientes de herramientas externas o detalles de infraestructura.

Puede incluir, cuando exista una necesidad concreta:

- persistencia especializada,
- importación de archivos,
- exportación,
- generación de códigos,
- almacenamiento de archivos,
- implementaciones de contratos definidos por Application.

Infrastructure puede depender de Application y Domain para implementar sus contratos.

### Http

Contiene exclusivamente elementos relacionados con el transporte HTTP.

Puede contener:

- Controllers
- Requests
- Resources
- Middleware

Http puede depender de Application y Domain.

## Dirección general de dependencias

La dirección principal es:

Http -> Application -> Domain

Infrastructure -> Application -> Domain

Domain no puede depender de Application, Infrastructure ni Http.

Application no puede depender de Http.

Shared no puede depender de Modules.

Un módulo nunca puede consumir la capa Http de otro módulo.

## Comunicación entre módulos

La comunicación entre módulos debe realizarse mediante una superficie pública explícita.

Cuando un módulo necesite una capacidad estable de otro módulo, la dependencia deberá expresarse mediante contratos o servicios públicos de Application.

No se crearán interfaces de forma automática para cada clase.

Las abstracciones se introducirán únicamente cuando exista una dependencia que deba protegerse o una infraestructura que deba desacoplarse.

## Directorios genéricos

No se utilizarán carpetas genéricas como:

- Helpers
- Utils
- Common
- Misc
- Other
- Otros
- Temporal
- Temp
- Nuevo
- Services2

No se utilizará un directorio global app/Services.

## Patrones adicionales

No se introducirá Repository Pattern, CQRS, microservicios, event sourcing ni otra abstracción arquitectónica de forma automática.

Su incorporación requiere una necesidad concreta y un ADR aprobado.

## Shared

app/Shared se reserva exclusivamente para componentes realmente compartidos por más de un módulo.

Shared no constituye un lugar para código cuya ubicación no haya sido determinada.

La creación de categorías dentro de Shared requiere revisión arquitectónica y actualización de las reglas automáticas.

## Consecuencias

Los módulos mantienen agrupados sus conceptos, casos de uso, transporte e implementaciones técnicas.

Las dependencias tienen una dirección explícita.

La arquitectura puede verificarse automáticamente mediante análisis estático y pruebas de arquitectura.

Los cambios estructurales requieren una decisión explícita.

Se reduce el riesgo de que diferentes desarrolladores introduzcan estructuras incompatibles.

El despliegue continúa siendo el de una única aplicación Laravel, por lo que no se añade la complejidad operativa de una arquitectura distribuida.
