# Reglas de dependencias arquitectónicas

## Capas

Las capas permitidas dentro de un módulo son:

- Domain
- Application
- Infrastructure
- Http

No es obligatorio que todas existan si el módulo todavía no las necesita.

## Dirección principal

Las dependencias principales permitidas son:

Http -> Application
Http -> Domain

Application -> Domain

Infrastructure -> Application
Infrastructure -> Domain

## Dependencias prohibidas

Quedan prohibidas:

Domain -> Application
Domain -> Infrastructure
Domain -> Http

Application -> Http

Shared -> Modules

Un módulo -> Http de otro módulo

## Comunicación entre módulos

Un módulo no debe acceder a Controllers, Requests, Resources o Middleware de otro módulo.

Las dependencias entre módulos deben ser explícitas.

Cuando un módulo requiera una capacidad de otro módulo deberá consumir una superficie pública de Application, preferentemente mediante Contracts cuando sea necesario proteger el límite.

No se crearán contratos o interfaces sin una necesidad concreta.

Las dependencias permitidas entre módulos se documentarán en una matriz de módulos y serán verificadas automáticamente.

## Directorios estructurales permitidos en app

En el primer nivel de app solamente pueden existir:

- Modules
- Shared
- Providers

La incorporación de otro directorio estructural requiere un ADR aprobado.

## Módulos permitidos

Dentro de app/Modules solamente pueden existir:

- Estudiantes
- Examenes
- Habilitacion
- Ingreso
- Monitoreo
- Reportes
- Administracion

La incorporación de un nuevo módulo requiere:

1. ADR aprobado.
2. actualización de la documentación C4.
3. actualización de las reglas automáticas.
4. revisión mediante Pull Request.

## Categorías permitidas por capa

### Domain

- Models
- Enums
- Rules
- Exceptions
- ValueObjects

### Application

- Actions
- Queries
- DTOs
- Contracts
- Authorization

### Infrastructure

Las categorías de Infrastructure deben expresar una responsabilidad técnica concreta.

No se crearán categorías genéricas.

Entre las categorías previstas por los requerimientos del proyecto se encuentran:

- Import
- Export
- Files
- Persistence
- QR
- Providers

La incorporación de otra categoría requiere que su responsabilidad sea explícita y que las reglas automáticas sean actualizadas.

### Http

- Controllers
- Requests
- Resources
- Middleware

No es obligatorio que todas las categorías existan.

## Shared

Shared comienza sin categorías obligatorias.

Una categoría dentro de Shared solo puede existir cuando su contenido sea realmente compartido por más de un módulo y su incorporación haya sido revisada arquitectónicamente.

Shared nunca puede depender de un módulo de negocio.

## Directorios genéricos prohibidos

No se permiten directorios cuyo nombre no exprese una responsabilidad arquitectónica clara, incluyendo:

- Helpers
- Utils
- Common
- Misc
- Other
- Otros
- Temporal
- Temp
- Nuevo
- Services
- Services2

Queda específicamente prohibido app/Services como contenedor global de lógica.

## Cambios arquitectónicos

Una modificación de:

- módulos,
- capas,
- reglas de dependencia,
- estructura de primer nivel,
- estructura de Shared,
- límites públicos entre módulos,

requiere:

1. ADR cuando la modificación altere una decisión arquitectónica.
2. revisión del responsable de arquitectura.
3. actualización de las reglas automáticas.
4. Pull Request.
5. ejecución satisfactoria del quality gate.

## Uso de Eloquent en Domain

Esta arquitectura es un monolito modular pragmático sobre Laravel y no una implementación estricta de Clean Architecture.

Los modelos persistentes pueden ubicarse en:

Domain/Models

y utilizar Eloquent.

Esto no autoriza a Domain a depender de:

- Controllers
- Requests
- Resources
- Middleware
- Actions
- Queries
- Infrastructure de su propio módulo
- Infrastructure de otros módulos

No se introducirán modelos de persistencia duplicados, mappers o Repository Pattern de forma sistemática.

Una abstracción de persistencia se incorporará únicamente cuando exista un problema concreto que la justifique.

## Dependencias de factories

El código de producción ubicado en app/Modules no depende de clases ubicadas en database/factories.

Las factories pueden depender de los modelos del dominio, pero los modelos del dominio no dependen de sus factories.

Esto mantiene la dirección:

Factory -> Domain Model

y evita introducir dependencias de soporte de pruebas dentro del código productivo.


## Dependencias externas permitidas en Domain

Domain no dispone de acceso general a Laravel ni a otras dependencias externas.

Toda dependencia externa utilizada desde Domain debe estar modelada explícitamente en el control arquitectónico. Las dependencias no clasificadas se consideran `uncovered` y deben hacer fallar el quality gate.

Actualmente se permiten únicamente las siguientes dependencias de Laravel para soportar el modelo autenticable de la aplicación:

- `Illuminate\Foundation\Auth\User`
- `Illuminate\Notifications\Notifiable`

Esta excepción es deliberadamente mínima. No autoriza el namespace `Illuminate` completo ni el paquete `laravel/framework` de forma general.

Por ejemplo, dependencias de transporte HTTP como `Illuminate\Http\Request` no están permitidas dentro de Domain.

Deptrac debe ejecutarse con `--fail-on-uncovered` para impedir que una nueva dependencia externa entre al dominio sin una decisión arquitectónica explícita.
