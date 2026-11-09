# Especificación de implementación del replanteo

Documento de trabajo para implementar el diseño de [`README.md`](README.md) sobre el código existente. Está escrito para que varios implementadores trabajen **en paralelo sobre el mismo árbol** sin pisarse: cada paquete de la sección 6 es dueño de una lista cerrada de archivos.

Fuentes, por orden de autoridad: `docs/replanteamiento/README.md` (reglas), `../Maquetacion/` (pantallas y comportamiento), este documento (cómo). Donde la maqueta y el README no dicen nada o se contradicen, la sección 7 fija el valor por defecto.

Estado de partida comprobado: rama `feat/replanteo`, 359 pruebas de backend (`php artisan test`: 21 de arquitectura, 1 de ejemplo, 144 de Administración, 193 del resto), 147 de frontend (`npm run test:run`) y 32 de integración (`composer test:postgresql`).

Antes de empezar la fase 0 se etiqueta el estado actual: `git tag pre-replanteo`. Todo «se adapta» de este documento significa **reescribir tomando como base el archivo anterior**, que se consulta con `git show pre-replanteo:<ruta>`.

---

## 1. Inventario de lo existente

### 1.1 Convenciones reales del código (obligatorias)

**Módulos y capas.** `app/Modules/<Módulo>/{Domain,Application,Infrastructure,Http}`. Las subcarpetas permitidas las fija `docs/architecture/architecture-contract.json` y las verifica `scripts/architecture/src/StructureChecker.php` (`composer quality:structure`):

| Capa | Categorías permitidas |
|---|---|
| `Domain` | `Models`, `Enums`, `Rules`, `Exceptions`, `ValueObjects` |
| `Application` | `Actions`, `Queries`, `DTOs`, `Contracts`, `Authorization` |
| `Infrastructure` | `Import`, `Export`, `Files`, `Mail`, `Persistence`, `QR`, `Providers` |
| `Http` | `Controllers`, `Requests`, `Resources`, `Middleware` |

Una carpeta fuera de esa lista (`Infrastructure/Console`, `Infrastructure/Jobs`, `Services`, `Helpers`…) **hace fallar el control**. Un módulo declarado puede no existir; uno no declarado falla. No se admiten archivos sueltos en la raíz de un módulo ni de una capa. Solo `.php`, nombres `^[A-Z][A-Za-z0-9]*\.php$`.

**Dependencias entre capas** (`deptrac.php`, con `--fail-on-uncovered`): `Http → Application, Domain`; `Infrastructure → Application, Domain`; `Application → Domain`; `Domain →` solo `Illuminate\Database\Eloquent\{Model, Relations\HasMany|BelongsTo|BelongsToMany}`, `Illuminate\Support\Carbon`, `Foundation\Auth\User` y `Notifiable`. Cada clase del marco que use una capa tiene que estar en la lista blanca de `deptrac.php`: `Http` puede usar `JsonResponse`, `Request`, `Response`, `UploadedFile`, `FormRequest`, `Validation\Rule`, `Facades\Auth`; `Infrastructure` puede usar `Facades\{DB,Hash,Schema,Storage,Mail}`, `Support\Str`, `QueryException`, `JoinClause`, `Mailable(s)`, `Bus\Queueable`, `SerializesModels`, Endroid QR, Dompdf y PhpSpreadsheet. **Usar una clase del marco que no esté en la lista rompe `quality:layers`**: se pide al dueño de `deptrac.php` (fase 0), no se edita desde un paquete paralelo.

**Dependencias entre módulos** (`deptrac.modules.php`): de un módulo solo son públicos `Application/Contracts` y `Application/DTOs`. Para leer datos de otro módulo hay dos caminos, los dos ya en uso: (a) inyectar un contrato suyo (`Ingreso` usa `Examenes\Application\Contracts\HabilitacionGateway`); (b) en un gateway de consulta, **SQL con `DB::table('tabla_ajena')` y joins por nombre de tabla**, sin importar el modelo ajeno (así leen hoy `Monitoreo` y `Reportes`, que no tienen tablas propias). Nunca se importa `Domain\Models` de otro módulo.

**Patrón de un caso de uso.**

- `Application/Contracts/XGateway.php`: interfaz. `Application/DTOs/XData.php`: `final readonly class` con propiedades promovidas públicas.
- `Application/Actions/Verbo.php` (escritura) o `Application/Queries/Consultar.php` (lectura): `final readonly class`, constructor con los contratos, un método público `execute(...)`. Aquí van las reglas de negocio y las excepciones de dominio (`Domain/Exceptions/*`, extienden `RuntimeException`).
- `Infrastructure/Persistence/EloquentXGateway.php`: implementación. Las escrituras van en `DB::transaction(fn () => …, 3)` y **dentro de la transacción** se asienta la bitácora con `BitacoraGateway::registrar(?int $usuarioId, string $operacion, ?string $tabla, ?int $registroId, ?string $descripcion)`; la clave de operación es `entidad.verbo` (`examen.registrar`, `ingreso.registrar`).
- `Http/Requests/XRequest.php`: `FormRequest` con `authorize(): true` (el permiso lo pone la ruta), `rules()` y, cuando conviene, `toData()` que devuelve el DTO.
- `Http/Controllers/XController.php`: sin lógica. Traduce a HTTP: `null`/`false` → 404 `{"message": …}`; excepción de dominio → `try/catch` local con 409 o 422. No hay manejador global de excepciones (`bootstrap/app.php` lo deja vacío) ni *route-model binding*: el controlador comprueba la existencia y responde `404 {"message":"Examen no encontrado."}`.
- Respuestas JSON: `{"data": …}` en listados y altas nuevas, `{"message": …}` en confirmaciones; validación fallida = 422 estándar de Laravel (`message`, `errors`). Sin sesión, 401 `{"message":"No hay una sesión activa."}`; sin permiso, 403 `{"message":"Tu rol no tiene permiso para esta sección.","permiso_requerido":"…"}` (`Http/Middleware/ExigirPermiso.php`).

**Registro.** Rutas: un archivo por tema en `routes/`, incluido con `require` desde `routes/web.php` (no hay `routes/api.php`: todo es grupo `web`, con sesión y CSRF), prefijo `api`, `->middleware(['auth', 'permiso:<clave>'])`, parámetros con `->whereNumber()`, nombres `tema.accion`. Enlaces contrato → implementación: hoy **todos** en `app/Providers/AppServiceProvider.php`; desde la fase 0 cada módulo tiene su `Infrastructure/Providers/<Módulo>ServiceProvider.php` registrado en `bootstrap/providers.php` (es lo que ya manda `docs/arquitectura.md` §5 y evita que ocho paquetes editen el mismo archivo). Comandos: cierres en `routes/console.php` que reciben la acción por inyección (`Artisan::command('respaldo:generar', function (GenerarRespaldo $generar) {…})`); no hay clases `Command`.

**Pruebas.** `phpunit.xml` arranca con `tests/Integration/PostgreSQL/bootstrap.php`: **toda la suite corre contra PostgreSQL 15.10, base `control_ingreso_testing`** (no hay SQLite; el SQL puede ser específico de PostgreSQL). Una prueba de característica es `final class XTest extends Tests\TestCase` con `use RefreshDatabase` y `use Tests\Support\UsuarioConPermisos`; se autentica con `$this->actingAs($this->usuarioConPermisos(['clave']))`, llama con `getJson`/`postJson` y afirma con `assertJsonPath` y `assertDatabaseHas`. Métodos `public function test_*` en inglés descriptivo. No hay fábricas de dominio: cada archivo arma sus datos con ayudantes privados. Cada pantalla trae al menos: caso feliz, validación, 401 de invitado, 403 sin permiso y asiento de bitácora. `tests/Architecture/*` no arrancan Laravel; `ModulePublicApiGuardTest` crea y borra sondas en `app/Modules/Habilitacion/**` y en `app/Modules/Ingreso/Application/Actions/*Probe.php`, y `RepositoryStructureGuardTest` exige que `Administracion` y `Habilitacion` sigan en el contrato.

**Análisis estático y estilo.** PHPStan nivel `max` sobre `app`, `config`, `database`, `routes`, `tests`; por eso los gateways convierten cada fila con ayudantes privados `texto(mixed): ?string` y `entero(mixed): int`. `declare(strict_types=1)` en los archivos nuevos. Pint con la configuración por defecto.

**Frontend.** `resources/js`, React 19 + react-router 7 + Tailwind 4, `PropTypes` obligatorios (`react/prop-types` es error en `eslint.config.js`), Prettier con `printWidth` 100, 4 espacios, comillas simples. Pruebas con vitest + Testing Library junto a cada página (`X.test.jsx`). Hoy las páginas llaman a `window.axios` y las pruebas lo sustituyen por un objeto de `vi.fn`.

**Calidad.** Una pieza no está terminada si falla `composer quality:backend` (composer, estructura, capas, módulos, PHPStan, Pint, autoload, pruebas), `composer test:postgresql` o `npm run quality` (ESLint, Prettier, vitest, compilación).

### 1.2 Módulos: de cinco a ocho

`deptrac.modules.php` y el contrato ya declaran siete módulos (`Estudiantes` y `Habilitacion` están declarados pero vacíos). El replanteo los **materializa** y agrega uno, `Academico`:

| Módulo | Qué tiene | Depende de (contratos) |
|---|---|---|
| `Administracion` | Acceso por usuario, cuentas, roles y permisos, bitácora, respaldo, registro de correos | — |
| `Academico` **(nuevo)** | Períodos, facultades, carreras, asignaturas, grupos, horarios, docentes, edificios y aulas; importación de GENDA | Administracion |
| `Estudiantes` **(se materializa)** | Padrón, inscripciones, cargas y conflictos | Administracion, Academico |
| `Examenes` | Examen, sus grupos, sus aulas, quién atiende, auxiliares, código de acceso | Administracion, Academico, Estudiantes |
| `Habilitacion` **(se materializa)** | Habilitación con motivo y reparto por aula | + Examenes |
| `Ingreso` | Códigos QR y su envío, la puerta, ingresos, incidencias | + Habilitacion |
| `Monitoreo` | En curso y asistencia por aula (solo lectura) | + Ingreso |
| `Reportes` | Reportes y exportación (solo lectura) | todos menos Monitoreo |

Un módulo nuevo exige ADR (`docs/architecture/adr/`), y actualizar `architecture-contract.json`, `deptrac.modules.php`, `docs/architecture/dependency-rules.md` y `module-dependencies.md`: lo hace F0-A.

### 1.3 Administración (`app/Modules/Administracion`)

| Pieza | Archivos | Veredicto |
|---|---|---|
| Inicio y cierre de sesión | `Actions/{AuthenticateUser,CerrarSesion}`, `Contracts/AuthenticationSecurityGateway`, `Persistence/EloquentAuthenticationSecurityGateway`, `Http/Controllers/AuthenticationController`, `Requests/LoginRequest`, `config/auth_security.php` | **Se adapta**: busca por `usuario` (no por `email`), rechaza la contraseña temporal vencida, devuelve intentos restantes y el bloqueo; nuevo `GET /api/auth/sesion` (ya está en la lista blanca de `ExigirContrasenaPropia` pero no existe la ruta) |
| Cambio obligatorio de contraseña | `Middleware/ExigirContrasenaPropia`, `Actions/CambiarContrasena`, `Contracts/CredencialesGateway`, `Requests/CambiarContrasenaRequest`, `Controllers/ContrasenaController` | **Se adapta**: la regla pasa a 10 caracteres, mayúscula, minúscula y número; con contraseña temporal no se pide la actual |
| Restablecimiento por enlace | `Actions/{SolicitarRestablecimiento,RestablecerContrasena}`, `Contracts/RestablecimientoGateway`, `Persistence/EloquentRestablecimientoGateway`, `Mail/EnlaceRestablecimientoMail`, `resources/views/correos/restablecimiento.blade.php` | **Se adapta**: se pide por `usuario`; el enlace va al correo de contacto si existe |
| Cuentas | `Actions/{CrearCuentaUsuario,ActualizarCuentaUsuario,CambiarEstadoCuenta}`, `Contracts/CuentaUsuarioGateway`, `DTOs/{NuevaCuentaData,UsuarioConRolData}`, `Persistence/EloquentCuentaUsuarioGateway`, `Mail/CredencialesInicialesMail`, `Controllers/CuentaUsuarioController`, sus tres `Requests` | **Se adapta**: usuario único, correo opcional, rol en el alta, contraseña temporal `Xx9-Xxx9-Xx9` con vencimiento a 72 h devuelta una vez; nueva acción `RestablecerContrasenaTemporal`. `CuentaUsuarioGateway` pasa a ser el contrato que usan `Academico` (cuenta del docente) y `Examenes` (cuenta del auxiliar) |
| Roles y permisos | `Authorization/VerificarPermiso`, `Middleware/ExigirPermiso`, `Contracts/RolGateway`, `Persistence/EloquentRolGateway`, `Domain/Models/{Role,Permission,User}`, `database/seeders/RolePermissionSeeder.php` | **Se conserva** el mecanismo (permiso por pantalla, con vigencia). **Cambian los datos**: tres roles y la lista de permisos de 3.1 |
| Edición de roles | `Actions/GestionarRoles` (crear, eliminar, sincronizar), `Controllers/RolController`, `Requests/{CrearRolRequest,SincronizarPermisosRequest,AsignarRolRequest}`, `DTOs/{AsignacionPermisoData,PermisoData,RolData}`, `routes/roles.php` | **Se elimina** la edición (la maqueta muestra la matriz como tabla fija). Queda una consulta `ConsultarMatrizPermisos` |
| Bitácora | `Contracts/{BitacoraGateway,ConsultaBitacoraGateway}`, `Actions/ConsultarBitacora`, `DTOs/{BitacoraOperacionData,ConsultarBitacoraData}`, sus gateways, `Controllers/BitacoraController`, `Requests/ConsultarBitacoraRequest`, `routes/bitacora.php` | Escritura: **se conserva tal cual**. Consulta: **se adapta** (hoy devuelve todas las filas sin paginar): paginación, búsqueda, filtro por rol, texto legible de la operación |
| Respaldo | `Actions/{GenerarRespaldo,RestaurarRespaldo}`, `Contracts/RespaldoGateway`, `DTOs/RespaldoData`, `Files/RespaldoSqlEnDisco`, `Domain/Exceptions/RespaldoNoEncontradoException`, `Controllers/RespaldoController`, `routes/respaldos.php`, comando y tarea en `routes/console.php` | **Se adapta**: cada generación deja fila en `respaldos` (tipo, estado, tamaño, error); la tarea pasa a las 03:00; `RespaldoSqlEnDisco::EXCLUIDAS` suma `respaldos`; se identifica por `id` |
| Padrón (CRUD) | `Actions/{Create,Update,Delete,Find,List}Student`, `Contracts/StudentRepository`, `Persistence/EloquentStudentRepository`, `Domain/Models/Student`, `Controllers/StudentController`, `Requests/{Store,Update}StudentRequest`, `routes/students.php`, `database/factories/StudentFactory.php` | **Se elimina**. El padrón se muda a `Estudiantes` y deja de editarse a mano: se llena con cargas y se corrige resolviendo conflictos |
| Carga masiva | `Actions/CargarPadronMasivo`, `Contracts/{LectorPadronGateway,PadronMasivoGateway}`, `DTOs/{FilaPadronData,RechazoCargaData,ResultadoCargaData}`, `Import/LectorPadronPhpSpreadsheet`, `Persistence/EloquentPadronMasivoGateway`, `Controllers/CargaMasivaController`, `Requests/CargarPadronRequest` | **Se muda a `Estudiantes` y se adapta**: el lector (`.csv`/`.xlsx`, detección de `;`, encabezado opcional) se reutiliza casi íntegro; la acción pasa de «crear o actualizar» a «inscribir en un grupo sin duplicar y abrir conflicto» |
| Ambientes | `Actions/{Create,Update,Delete,Find,List}Ambiente`, `Contracts/{AmbienteRepository,CompromisoAmbienteGateway}`, sus gateways, `Domain/Models/Ambiente`, `Domain/Exceptions/CapacidadInsuficienteException`, `Controllers/AmbienteController`, `Requests/{Store,Update}AmbienteRequest`, `routes/ambientes.php`, `database/factories/AmbienteFactory.php` | **Se elimina**. Las aulas se importan, no tienen capacidad ni estado, y viven en `Academico` |
| Registro de correos | — | **Nuevo**: `Contracts/RegistroCorreoGateway` + `Persistence/EloquentRegistroCorreoGateway` sobre `envios_correo` |

Pantallas: `resources/js/paginas/admin/{UsuariosRoles,Bitacora,Respaldos}.jsx` y `auth/*` **se reemplazan** por las de la maqueta (`auth/OlvideContrasena.jsx` desaparece como página: es un diálogo del inicio de sesión; `auth/RestablecerContrasena.jsx` se conserva adaptada, la maqueta no la dibuja pero el enlace del correo la necesita). `admin/AsignaturasAmbientes.jsx`, `estudiantes/{RegistroEstudiantes,CargaMasiva}.jsx` y `componentes/PestanasPadron.jsx` **se eliminan**.

### 1.4 Exámenes (`app/Modules/Examenes`)

| Pieza | Archivos | Veredicto |
|---|---|---|
| Asignaturas a mano | `Actions/{RegistrarAsignatura,EliminarAsignatura,ListarAsignaturas}`, `Contracts/AsignaturaGateway`, `DTOs/{RegistrarAsignaturaData,GrupoAsignaturaData}`, `Persistence/EloquentAsignaturaGateway`, `Domain/Exceptions/AsignaturaTieneDependenciasException`, `Domain/Models/{Asignatura,GrupoAsignatura}`, `Controllers/AsignaturaController`, `Requests/RegistrarAsignaturaRequest`, `routes/asignaturas.php` | **Se elimina**. Asignaturas y grupos se importan; los modelos renacen en `Academico` |
| Docentes a mano | `Actions/{RegistrarDocente,ListarDocentes}`, `Contracts/DocenteGateway`, `DTOs/RegistrarDocenteData`, `Persistence/EloquentDocenteGateway`, `Domain/Models/Docente`, `Controllers/DocenteController`, `Requests/RegistrarDocenteRequest` | **Se elimina**. El docente se importa; su cuenta se activa en `Academico` |
| Examen | `Actions/{RegistrarExamen,ActualizarExamen,EliminarExamen,ListarExamenes}`, `Contracts/ExamenGateway`, `DTOs/RegistrarExamenData`, `Domain/Enums/TipoExamen`, `Domain/Exceptions/ExamenConIngresosException`, `Domain/Models/Examen`, `Persistence/EloquentExamenGateway`, `Controllers/ExamenController`, `Requests/RegistrarExamenRequest`, `routes/examenes.php` | **Se adapta a fondo**: el examen es de la asignatura y abarca grupos; se registra de una vez con sus grupos, aulas y personal; el listado es el del docente, con avance y paso siguiente. `TipoExamen` pierde `PRACTICO` (la maqueta ofrece cuatro) |
| Ambientes del examen | `Actions/{AsignarAmbienteAExamen,QuitarAmbienteDeExamen,ConsultarAmbientesDeExamen}`, `Contracts/AmbienteExamenGateway`, `DTOs/{AmbienteAsignadoData,OcupacionExamenData}`, `Domain/Exceptions/AmbienteNoDisponibleException`, `Persistence/EloquentAmbienteExamenGateway`, `Controllers/AmbienteExamenController`, `Requests/AsignarAmbienteRequest` | **Se elimina como pieza aparte**: las aulas viajan dentro del examen. De la lógica de solape sobrevive la consulta, convertida en **aviso** de aula compartida (`Queries/ConsultarOpcionesDeAulas`). Sin capacidad |
| Normas | `Actions/{Registrar,Eliminar,Listar}NormaExamen`, `Contracts/NormaExamenGateway`, `DTOs/{NormaExamenData,RegistrarNormaData}`, `Domain/Enums/AlcanceNorma`, `Domain/Models/NormaExamen`, `Persistence/EloquentNormaExamenGateway`, `Controllers/NormaExamenController`, `Requests/RegistrarNormaRequest` | **Se elimina**. Queda un texto `examenes.normas` que edita el paso 1 del registro y lee la puerta (ver 7, D-08) |
| Habilitación | `Actions/{RegistrarHabilitacion,ListarHabilitados}`, `Contracts/HabilitacionGateway`, `DTOs/{HabilitacionData,CambioHabilitacionData}`, `Domain/Models/HabilitacionExamen`, `Persistence/EloquentHabilitacionGateway`, `Controllers/HabilitacionController`, `Requests/RegistrarHabilitacionRequest` | **Se muda a `Habilitacion` y se adapta**: hoy lista **todo** el padrón activo; pasa a listar los inscritos de los grupos del examen, paginado y con filtros |
| Distribución | `Actions/{DistribuirEstudiantes,AsignarEstudianteAAmbiente}`, `Contracts/DistribucionGateway`, `DTOs/DistribucionData`, `Domain/Exceptions/AmbienteSinCupoException`, `Persistence/EloquentDistribucionGateway`, `Controllers/DistribucionController`, `Requests/AsignarEstudianteRequest` | **Se muda a `Habilitacion` y se adapta**: reparto al aula con menos carga, sin tope. El ajuste individual (`AsignarEstudianteAAmbiente`) **se elimina**: la maqueta no lo tiene |
| Consulta en puerta | `Actions/ConsultarHabilitacion`, `Controllers/ConsultaHabilitacionController`, `Requests/ConsultarHabilitacionRequest` | **Se elimina**: la absorbe la verificación de la puerta (`Ingreso`) |
| Quién atiende, auxiliares, código de acceso, alcance | — | **Nuevo** |

Pantallas: `resources/js/paginas/examenes/Examenes.jsx` y `habilitacion/Habilitacion.jsx` **se reemplazan**; `ingreso/ConsultaHabilitacion.jsx` **se elimina**.

### 1.5 Ingreso (`app/Modules/Ingreso`)

| Pieza | Archivos | Veredicto |
|---|---|---|
| Códigos QR | `Actions/EmitirCodigosQr`, `Contracts/{CodigoQrGateway,GeneradorImagenQr}`, `DTOs/CodigoQrData`, `Persistence/EloquentCodigoQrGateway`, `QR/GeneradorImagenQrEndroid`, `Controllers/CodigoQrController`, `resources/views/qr/hoja.blade.php` | **Se adapta**: token y emisión idempotente se conservan; el listado se pagina y filtra; la credencial suma aula, edificio y piso; la hoja imprimible (HTML con `@media print`, no PDF) acepta filtros. **Nuevo**: envío por correo en cola |
| Lectura de QR | `Actions/LeerCodigoQr`, `Domain/Exceptions/CodigoDeOtroExamenException`, `Controllers/LecturaQrController`, `Requests/LeerCodigoQrRequest` | **Se funde** en la verificación de la puerta |
| Registro de ingreso | `Actions/{RegistrarIngreso,ConsultarIngresos}`, `Contracts/IngresoGateway`, `DTOs/{IngresoData,RegistrarIngresoData}`, `Domain/Enums/MedioIdentificacion`, `Domain/Exceptions/IngresoNoPermitidoException`, `Domain/Models/IngresoExamen`, `Persistence/EloquentIngresoGateway`, `Controllers/IngresoController`, `Requests/RegistrarIngresoRequest` | **Se adapta a fondo**: la puerta trabaja **por aula y hora**, resuelve el examen, y verificar y registrar son una sola operación. Hoy la carrera entre dos puestos acaba en 500 (nadie captura la `QueryException` del índice único): la versión nueva la captura y responde «Ya ingresó» |
| Anulación | `Actions/AnularIngreso`, `Requests/AnularIngresoRequest`, columnas `anulado_*`, índice parcial | **Se elimina**: el ingreso es único e inmutable |
| Incidencias del sistema | `Contracts/IncidenciaGateway`, `Domain/Enums/TipoIncidencia`, `Persistence/EloquentIncidenciaGateway` | **Se adapta** sobre la tabla `incidencias` unificada (origen `SISTEMA`) |
| Expulsión | `Actions/RegistrarExpulsion`, `Contracts/ExpulsionGateway`, `DTOs/ExpulsionData`, `Domain/Exceptions/ExpulsionNoPermitidaException`, `Domain/Models/Expulsion`, `Persistence/EloquentExpulsionGateway`, `Controllers/ExpulsionController`, `Requests/RegistrarExpulsionRequest` | **Se adapta**: es una incidencia de tipo `EXPULSION` sobre un ingreso; la hora la pone el servidor (la maqueta ya no la pide) |
| Situación excepcional | `Actions/RegistrarSituacion`, `Contracts/SituacionGateway`, `DTOs/{SituacionData,RegistrarSituacionData}`, `Domain/Enums/TipoSituacion`, `Persistence/EloquentSituacionGateway`, `Controllers/SituacionController`, `Requests/RegistrarSituacionRequest` | **Se adapta**: es la «excepción» de la puerta y queda como incidencia; `CAMBIO_AMBIENTE` pasa a `CAMBIO_AULA` |
| Reportar y atender incidencias; acceso con código de aula | — | **Nuevo** |

Pantallas: `resources/js/paginas/ingreso/{PuntoControl,CodigosQr}.jsx` **se reemplazan**; `SituacionesExcepcionales.jsx` **se elimina** (queda dentro del punto de control). De `PuntoControl.jsx` actual se rescata el uso de `qr-scanner` (import dinámico, `new QrScanner(video, cb, { highlightScanRegion: true, maxScansPerSecond: 4 })`), al que le falta la limpieza al desmontar.

### 1.6 Monitoreo y Reportes

| Pieza | Archivos | Veredicto |
|---|---|---|
| Tablero | `Monitoreo/Application/{Contracts/TableroGateway,Queries/ConsultarTablero,DTOs/{TableroData,AmbienteTableroData}}`, `Persistence/EloquentTableroGateway`, `Http/Controllers/TableroController`, `routes/monitoreo.php` | **Se adapta** a «En curso»: por examen, con quién atiende cada aula, incidencias sin atender y aula compartida; solo para el docente del examen |
| Asistencia | `Monitoreo/Application/{Queries/ConsultarAsistencia,DTOs/AsistenciaData}`, `Domain/Enums/EstadoAsistencia`, `Http/Controllers/AsistenciaController` | **Se adapta**: por examen **y aula**, dos listas (faltan, ingresaron), paginada y con búsqueda |
| Reportes | `Reportes/Application/{Contracts/{ReporteGateway,ExportadorReporte},Queries/{ConsultarReporte,ArmarTabla},DTOs/*}`, `Domain/Enums/TipoIntento`, `Infrastructure/{Persistence/EloquentReporteGateway,Export/ExportadorReporteArchivo}`, `Http/{Controllers/ReporteController,Requests/FiltroReporteRequest}`, `routes/reportes.php` | `ExportadorReporteArchivo` (PDF con Dompdf, xlsx con PhpSpreadsheet) y `TablaReporte`: **se conservan tal cual**. Las cinco consultas **se adaptan** al esquema nuevo y a los filtros nuevos (período, facultad, texto, fechas o un examen). La vista previa en pantalla **se elimina**: la maqueta solo descarga. **Nuevo**: listado de exámenes del administrador, resumen por examen del docente y «mis estudiantes» de un grupo |

Pantallas: `resources/js/paginas/monitoreo/{Tablero,Asistencia}.jsx` y `reportes/Reportes.jsx` **se reemplazan**.

### 1.7 Lo demás

| Pieza | Veredicto |
|---|---|
| `app/Providers/AppServiceProvider.php` | Queda vacío de enlaces (se reparten en los *providers* de módulo) |
| `bootstrap/app.php` | Se adapta: alias `puesto` para el middleware de la puerta |
| `routes/web.php` | Se adapta: nueva lista de `require`; ruta `/sw.js`; se conserva la ruta de reserva de la SPA |
| `routes/{ambientes,asignaturas,students,roles}.php` | Se eliminan |
| `database/seeders/{DemoGuiaSeeder,DatosMasivosSeeder}.php` | Se eliminan; los sustituyen los de 4.5 |
| `database/factories/UserFactory.php` | Se adapta (agrega `usuario`) |
| `docs/ejemplos/padron_*.csv` | Se sustituyen por `inscritos_grupo_ejemplo.csv`, `inscritos_grupo_con_errores.csv` e `inscripciones_facultad_ejemplo.csv` |
| `tests/Support/UsuarioConPermisos.php` | Se conserva; se le suman los rasgos de 6.1 |
| `docs/{arquitectura,modelo-datos}.md`, `README.md`, `docs/guia/*` | Se actualizan al final (paquete I1); las fichas por historia quedan como historia del diseño anterior |
| `../Maquetacion/` | No se toca: es la referencia |

---
## 2. Esquema de base de datos objetivo

### 2.1 Estrategia de migraciones

**Se reemplazan las migraciones de dominio y se conservan las del marco y de administración.** Motivo en una línea: la base se puede rehacer con `migrate:fresh` y 15 de las 17 tablas de dominio cambian de clave, de nombre o de dueño; alterarlas una por una deja un historial que ya no describe el sistema, mientras que `users`, roles, bitácora y las tablas del marco casi no cambian y tienen pruebas de integración atadas a sus migraciones.

| Migración existente | Veredicto |
|---|---|
| `0001_01_01_000000_create_users_table.php`, `…000001_create_cache_table.php`, `…000002_create_jobs_table.php` | Se conservan |
| `2026_09_16_140731_add_authentication_security_fields_and_login_attempts.php` | Se conserva |
| `2026_09_17_203234_create_bitacora_operaciones_table.php` | Se conserva |
| `2026_09_23_120124_create_roles_permissions_tables.php`, `2026_09_25_090000_add_password_changed_at_to_users.php`, `2026_09_25_100000_add_vigencia_and_system_flag_to_roles.php` | Se conservan |
| `2026_09_17_004551_create_{asignaturas,docentes,grupos_asignatura}_table.php` | **Se eliminan** (las sustituyen las de 2.3) |
| `2026_09_21_213342_create_estudiantes_table.php`, `2026_09_25_110000_align_students_with_backlog.php` | **Se eliminan** |
| `2026_09_23_120000_create_ambientes_table.php` | **Se elimina** |
| `2026_09_28_120000` a `2026_09_28_230000` (exámenes, normas, examen_ambiente, habilitaciones, ingresos, incidencias, QR, índice parcial, expulsiones, situaciones, aula en incidencias: 12 archivos) | **Se eliminan** |

Migraciones nuevas (todas en `database/migrations/`, las crea el paquete F0-A; ningún otro paquete crea ni edita migraciones):

| Archivo | Tablas |
|---|---|
| `2026_10_06_000100_adapt_users_to_username_login.php` | altera `users` |
| `2026_10_06_001000_create_academico_tables.php` | `periodos`, `facultades`, `carreras`, `asignaturas`, `plan_estudios`, `docentes`, `edificios`, `aulas`, `grupos`, `horarios`, `importaciones_oferta` |
| `2026_10_06_002000_create_estudiantes_tables.php` | `estudiantes`, `cargas_inscritos`, `inscripciones`, `conflictos_padron` |
| `2026_10_06_003000_create_examenes_tables.php` | `examenes`, `examen_grupo`, `examen_aula`, `examen_aula_apoyo`, `auxiliares`, `accesos_aula` |
| `2026_10_06_004000_create_habilitaciones_table.php` | `habilitaciones` |
| `2026_10_06_005000_create_ingreso_tables.php` | `codigos_qr`, `ingresos`, `incidencias` |
| `2026_10_06_006000_create_respaldos_and_envios_correo_tables.php` | `respaldos`, `envios_correo`; índices de `bitacora_operaciones` |

Convenciones que ya usa el esquema y se mantienen: tablas en plural y en español (salvo las del marco: `users`, `roles`, `permissions`, `permission_role`); claves `bigint` con `$table->id()`; `foreignId(...)->constrained(...)->cascadeOnUpdate()` con `restrictOnDelete()` donde borrar destruiría historia y `nullOnDelete()` donde el dato es contextual; índices y únicos con nombre explícito (`uq_…`, `idx_…`); los `CHECK`, índices parciales y disparadores se crean con `DB::statement` solo si `DB::connection()->getDriverName() === 'pgsql'`; enumeraciones como `string` con un `enum` de PHP en `Domain/Enums` (no tipos ENUM de PostgreSQL).

Cambios de nombre respecto de lo anterior: `students` → `estudiantes` (FK `estudiante_id`), `ambientes` → `aulas` (FK `aula_id`), `grupos_asignatura` → `grupos`, `examen_ambiente` → `examen_aula`, `habilitaciones_examen` → `habilitaciones`, `ingresos_examen` → `ingresos`, `incidencias_ingreso` + `expulsiones` + `situaciones_excepcionales` → `incidencias`. Se elimina `normas_examen` (ver 2.5, `examenes.normas`).

Total: 9 tablas del marco + 31 de dominio = **40 tablas**.

### 2.2 Marco y administración

**`users`** (se altera)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(255) | Nombre completo, como se muestra |
| `usuario` | string(60), **único** `uq_users_usuario` | NUEVA. Identificador de acceso; en minúsculas; `^[a-z0-9._-]+$` |
| `email` | string(255), **nulo**, único | CAMBIA a nulo. Correo de contacto; PostgreSQL admite varios nulos en un único |
| `password` | string | `hashed` |
| `password_changed_at` | timestamp nulo | Existente. `null` = contraseña temporal, cambio obligatorio |
| `password_temporal_expira_en` | timestamp nulo | NUEVA. Emisión + 72 h; vencida, la cuenta no entra hasta que se restablezca |
| `is_active` | boolean, def. true | Existente. `false` = «Bloqueado» en la interfaz |
| `failed_login_attempts`, `locked_until`, `last_login_at` | | Existentes (bloqueo de 15 min tras 5 intentos) |
| `role_id` | FK `roles`, nulo, nullOnDelete | Existente |
| `email_verified_at`, `remember_token`, timestamps | | Existentes |

`password_reset_tokens` (clave `email`), `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `login_attempts` (su columna `identifier` pasa a guardar el **usuario** tecleado): sin cambio de estructura.

**`roles`**, **`permissions`**, **`permission_role`**: sin cambio de estructura. Cambian los datos: tres roles de sistema (`Administrador`, `Docente`, `Auxiliar`) y la lista de permisos de 3.1.

**`bitacora_operaciones`**: sin cambio (`usuario_id` nulo, `operacion`, `tabla_afectada`, `registro_id`, `descripcion`, `fecha_operacion`). Se agrega en la migración `…006000` un índice `idx_bitacora_fecha` sobre `fecha_operacion` y otro `idx_bitacora_operacion` sobre `operacion` si no existen (la pantalla filtra por ambos sobre miles de filas).

**`respaldos`** (nueva; hoy los respaldos son solo archivos y no pueden registrar un fallo ni el tipo)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `archivo` | string(120), único | `respaldo-AAAAMMDD-HHMMSS.sql`; nulo si falló antes de escribir |
| `tipo` | string(12) | `PROGRAMADO` \| `PUNTUAL` |
| `estado` | string(10) | `CORRECTO` \| `FALLIDO` |
| `tamano_bytes` | bigint nulo | |
| `tablas`, `filas` | integer nulo | |
| `error` | text nulo | |
| `generado_por` | FK `users` nulo, nullOnDelete | nulo = el planificador |
| `restaurado_en` | timestamp nulo | última restauración desde este archivo |
| `restaurado_por` | FK `users` nulo | |
| `created_at` | timestamp | |

Esta tabla, `sessions`, `cache*`, `jobs*`, `failed_jobs` y `password_reset_tokens` **quedan fuera del volcado y de la restauración**: una restauración no puede borrar el historial de respaldos ni la sesión de quien la ordena.

**`envios_correo`** (nueva)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tipo` | string(20) | `CREDENCIALES` \| `RESTABLECIMIENTO` \| `CODIGO_QR` |
| `destinatario` | string(150) | |
| `user_id` | FK `users` nulo | cuenta destinataria (credenciales, restablecimiento) |
| `examen_id` | FK `examenes` nulo, cascadeOnDelete | |
| `estudiante_id` | FK `estudiantes` nulo | |
| `estado` | string(10) | `PENDIENTE` \| `ENVIADO` \| `FALLIDO` |
| `error` | text nulo | |
| `solicitado_por` | FK `users` nulo | |
| `enviado_en` | timestamp nulo | |
| timestamps | | |

Índice `idx_envio_examen_estado (examen_id, estado)`. Va en la última migración (`…006000`) porque referencia a `examenes` y `estudiantes`.

### 2.3 Académico

**`periodos`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `codigo` | string(10), único | `2/2026` |
| `anio` | smallint | |
| `numero` | smallint | 0 anual, 1, 2, 3 verano (sin confirmar), 4 invierno |
| `tipo` | string(12) | `SEMESTRE_1` \| `SEMESTRE_2` \| `ANUAL` \| `INVIERNO` \| `VERANO` |
| `fecha_inicio`, `fecha_fin` | date nulo | nulo = «Sin fechas», a ajustar a mano |
| `ventanas` | json nulo | `{"primeros_parciales":["2026-10-12","2026-10-31"],…}` tal cual `fuentes.json` |
| `fuente` | string(120) nulo | de dónde salieron las fechas |
| `ajustado_por` | FK `users` nulo | quién tocó las fechas a mano |
| timestamps | | |

Único `uq_periodo_anio_numero (anio, numero)`. **El estado no se guarda**: `VIGENTE` si `fecha_inicio ≤ hoy ≤ fecha_fin`, `CERRADO` si `hoy > fecha_fin`, `PROXIMO` si `hoy < fecha_inicio`, `SIN_FECHAS` si falta alguna. Pueden estar vigentes varios a la vez.

**`facultades`** (catálogo fijo de cuatro filas, lo siembra `FacultadSeeder`)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `clave` | string(10), único | `fcyt`, `fce`, `fhce`, `fach`: nombre del archivo JSON y clave de `locations.json` |
| `sigla` | string(10), único | `FCyT`, `FCE`, `FHCE`, `FACH`: lo que se muestra |
| `nombre` | string(120) | |
| `codigo_umss` | string(4) | `20`, `13`, `18`, `17` (campo `code` de `pensum.json`) |
| `color` | char(7) | `#B90813`, `#107C41`, `#ea580c`, `#154075` |
| `orden` | smallint | |

**`carreras`**: `id`; `facultad_id` FK restrict; `codigo` string(10) **único** (`411702`); `nombre` string(150); `regimen` string(10) `SEMESTRAL` \| `ANUAL`; timestamps.

**`asignaturas`**: `id`; `codigo` string(30) **único** (`2008019`); `nombre` string(150); timestamps. Pierde `semestre`, `descripcion` y `estado`: el nivel depende de la carrera y va en `plan_estudios`.

**`plan_estudios`**: `id`; `carrera_id` FK cascade; `asignatura_id` FK cascade; `nivel` string(30) (`SEMESTRE 3`, `PRIMER AÑO`); único `uq_plan_carrera_asignatura (carrera_id, asignatura_id)`.

**`docentes`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK `users` nulo, **único**, nullOnDelete | la cuenta; nulo = «Sin cuenta» |
| `nombre_completo` | string(150) | como viene en la oferta: «BLANCO COCA LETICIA» pasado a «Blanco Coca Leticia» |
| `nombre_normalizado` | string(150), **único** | mayúsculas, sin tildes, espacios simples: es la identidad al reimportar |
| timestamps | | |

Un docente es una fila aunque dicte en varias facultades (decisión 16). **Sus facultades se derivan** de `grupos (docente_id, facultad_id)` del período; no hay tabla puente. Pierde `codigo_docente`, `nombres`, `apellidos`, `correo`, `telefono`, `estado`: el correo es de la cuenta (`users.email`).

**`edificios`**: `id`; `facultad_id` FK restrict; `clave` string(40) **único** (`fcyt_blk_0_50366` = clave de facultad + `id` de `locations.json`); `nombre` string(150); `poligono` json (lista de `[longitud, latitud]`, cerrada); `centro_lon`, `centro_lat` decimal(10,7); timestamps.

**`aulas`**: `id`; `nombre` string(30) **único** `uq_aula_nombre` (`691B`); `edificio_id` FK nulo nullOnDelete (nulo = aula de la oferta sin ubicar: `AULVIR`, `AUDINF`…); `facultad_id` FK nulo (la del edificio o, sin edificio, la primera facultad que la trajo); `piso` string(40) nulo (`1° Piso`); timestamps. Sin capacidad ni estado.

**`grupos`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `periodo_id` | FK `periodos` restrict | |
| `asignatura_id` | FK `asignaturas` restrict | |
| `facultad_id` | FK `facultades` restrict | la del archivo que lo trajo |
| `docente_id` | FK `docentes` nulo, nullOnDelete | nulo = «Por designar» |
| `codigo` | string(20) | `3`, `A1`, `07` |
| timestamps | | |

Único `uq_grupo_periodo_asignatura_facultad_codigo (periodo_id, asignatura_id, facultad_id, codigo)`. Índices `idx_grupo_docente_periodo (docente_id, periodo_id)` y `idx_grupo_facultad_periodo (facultad_id, periodo_id)`.

**`horarios`**: `id`; `grupo_id` FK cascade; `aula_id` FK nulo nullOnDelete; `dia` char(2) (`LU`…`SA`); `hora_inicio`, `hora_fin` time; `es_auxiliatura` boolean def. false; índices `idx_horario_grupo (grupo_id)` e `idx_horario_aula_dia (aula_id, dia)`. Sin timestamps: se borran y recrean en cada importación del grupo.

**`importaciones_oferta`**: `id`; `facultad_id` FK; `estado` string(12) `IMPORTANDO` \| `IMPORTADA` \| `FALLO`; `periodo_codigo` string(10) nulo (el de `INFO.SEMESTER`); `fecha_fuente` date nulo (`INFO.DATE`); `resumen` json nulo; `error` text nulo; `ejecutada_por` FK `users` nulo; `iniciada_en`, `terminada_en` timestamp. Índice `(facultad_id, id)`. El estado de una facultad es el de su última fila; sin filas, «Sin importar».

### 2.4 Estudiantes

**`estudiantes`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `codigo_universitario` | string(20), **único** | código SIS |
| `documento_identidad` | string(30) nulo | único parcial `uq_estudiante_documento … WHERE documento_identidad IS NOT NULL` |
| `nombres`, `apellidos` | string(100) | |
| `carrera_id` | FK `carreras` nulo, nullOnDelete | |
| `facultad_id` | FK `facultades` nulo | la de la carrera o la del grupo de su primera inscripción |
| `origen` | string(15) | `ADMINISTRACION` \| `DOCENTE`: quién lo creó |
| `verificado` | boolean | true si lo creó o lo confirmó una carga de administración |
| `activo` | boolean def. true | |
| timestamps | | |

Índice `idx_estudiante_apellidos (apellidos, nombres)`. **El correo no se guarda**: es `codigo_universitario` + `@est.umss.edu` (regla de `fuentes.json`), calculado por el modelo.

**`cargas_inscritos`** (una fila por archivo subido): `id`; `alcance` string(10) `GRUPO` \| `FACULTAD`; `grupo_id` FK nulo; `facultad_id` FK nulo; `periodo_id` FK; `archivo` string(200); `filas`, `nuevos`, `reutilizados`, `ya_inscritos`, `rechazados`, `conflictos` integer; `rechazos` json (`[{"fila":31,"motivo":"Sin documento de identidad"}]`); `cargada_por` FK `users`; `created_at`.

**`inscripciones`**: `id`; `estudiante_id` FK restrict; `grupo_id` FK restrict; `via` string(15) `ADMINISTRACION` \| `DOCENTE`; `cargada_por` FK `users` nulo; `carga_id` FK `cargas_inscritos` nulo; timestamps. Único `uq_inscripcion_estudiante_grupo (estudiante_id, grupo_id)`; índice `idx_inscripcion_grupo (grupo_id)`.

**`conflictos_padron`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `estudiante_id` | FK `estudiantes` cascade | el guardado (sus datos actuales se leen de ahí) |
| `grupo_id` | FK `grupos` nulo | la inscripción que quedó en espera |
| `carga_id` | FK `cargas_inscritos` nulo | |
| `fila` | integer nulo | |
| `tipo` | string(20) | `DOCUMENTO_DISTINTO` \| `NOMBRE_DISTINTO` |
| `documento_nuevo` | string(30) nulo | |
| `nombres_nuevos`, `apellidos_nuevos` | string(100) | |
| `via` | string(15) | de dónde vino la carga nueva |
| `estado` | string(10) | `PENDIENTE` \| `RESUELTO` |
| `resolucion` | string(16) nulo | `MANTENER_PADRON` \| `USAR_CARGA` |
| `reportado_por` | FK `users` | |
| `resuelto_por` | FK `users` nulo | |
| `resuelto_en` | timestamp nulo | |
| timestamps | | |

Índice `idx_conflicto_estado (estado)`. Al resolver, con cualquiera de las dos salidas, se crea la inscripción en espera (`grupo_id`).

### 2.5 Exámenes

**`examenes`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `periodo_id` | FK `periodos` restrict | el de los grupos |
| `asignatura_id` | FK `asignaturas` restrict | |
| `tipo` | string(20) | `PRIMER_PARCIAL` \| `SEGUNDO_PARCIAL` \| `FINAL` \| `SEGUNDA_INSTANCIA` |
| `fecha` | date | |
| `hora_inicio` | time | |
| `duracion_minutos` | smallint | CHECK 15–480 |
| `normas` | text nulo | texto único que ve la puerta (sustituye a `normas_examen`) |
| `creado_por` | FK `users` restrict | el docente que lo registró |
| timestamps | | |

Índices `idx_examen_fecha_hora (fecha, hora_inicio)`, `idx_examen_asignatura_periodo (asignatura_id, periodo_id)`, `idx_examen_creador (creado_por)`. Desaparecen `grupo_asignatura_id`, `descripcion`, `estado` y el único por grupo-fecha-hora. Regla en la aplicación: un grupo no puede estar en dos exámenes del mismo `tipo`.

**`examen_grupo`**: `id`; `examen_id` FK cascade; `grupo_id` FK restrict; único `uq_examen_grupo (examen_id, grupo_id)`; índice `idx_examen_grupo_grupo (grupo_id)`.

**`examen_aula`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `examen_id` | FK `examenes` cascade | |
| `aula_id` | FK `aulas` restrict | |
| `encargado_id` | FK `users` restrict | quién está a cargo: un docente o un auxiliar; por defecto el creador |
| `codigo_acceso` | char(6) nulo | seis dígitos; nulo = sin código |
| `codigo_generado_en` | timestamp nulo | |
| timestamps | | |

Único `uq_examen_aula (examen_id, aula_id)`; índices `idx_examen_aula_aula (aula_id)` y `idx_examen_aula_encargado (encargado_id)`; único parcial `uq_examen_aula_codigo (codigo_acceso) WHERE codigo_acceso IS NOT NULL`. **No hay restricción de solape**: el aula compartida es un aviso (decisión 20).

**`examen_aula_apoyo`**: `id`; `examen_aula_id` FK cascade; `user_id` FK `users` restrict; único `uq_apoyo_aula_usuario (examen_aula_id, user_id)`.

**`auxiliares`**: `id`; `user_id` FK `users` **único**, cascade; `codigo_universitario` string(20) nulo (el auxiliar es un estudiante: su correo es ese código + `@est.umss.edu`); `registrado_por` FK `users` nulo (el docente o el administrador que lo dio de alta); timestamps. El auxiliar es una cuenta con rol `Auxiliar`; esta tabla solo guarda de quién es y su código.

**`accesos_aula`** (quién entró con el código de un aula): `id`; `examen_aula_id` FK cascade; `nombre` string(120); `ip` string(45) nulo; `creado_en` timestamp. Índice `(examen_aula_id)`.

### 2.6 Habilitación

**`habilitaciones`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `examen_id` | FK `examenes` cascade | |
| `estudiante_id` | FK `estudiantes` restrict | |
| `habilitado` | boolean | |
| `aula_id` | FK `aulas` nulo, nullOnDelete | el aula que le toca; nulo = sin repartir |
| `motivo` | text nulo | obligatorio si `habilitado = false` (regla de aplicación) |
| `registrada_por` | FK `users` nulo | |
| timestamps | | |

Único `uq_habilitacion_examen_estudiante (examen_id, estudiante_id)`; índice `idx_habilitacion_examen_aula (examen_id, aula_id)`; CHECK (pgsql) `habilitado OR aula_id IS NULL`. **«Sin revisar» es la ausencia de fila**: la lista del examen son los inscritos de sus grupos con `LEFT JOIN` a esta tabla.

### 2.7 Ingreso

**`codigos_qr`**: `id`; `examen_id` FK cascade; `estudiante_id` FK restrict; `token` string(64) **único** (48 caracteres al azar, opaco, como hoy); `emitido_en` timestamp; `emitido_por` FK `users` nulo; `enviado_en` timestamp nulo (NUEVA); timestamps. Único `uq_codigo_qr_examen_estudiante (examen_id, estudiante_id)`.

**`ingresos`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `examen_id` | FK `examenes` restrict | |
| `estudiante_id` | FK `estudiantes` restrict | |
| `aula_id` | FK `aulas` restrict | la puerta por la que entró |
| `medio` | string(12) | `QR` \| `CODIGO` \| `DOCUMENTO` |
| `es_excepcion` | boolean def. false | entró por un cambio de aula autorizado |
| `registrado_en` | timestamp | lo pone el servidor |
| `registrado_por` | FK `users` nulo, restrict | nulo solo si entró con código de aula |
| `acceso_aula_id` | FK `accesos_aula` nulo | |
| `created_at` | timestamp | sin `updated_at` |

**Único total** `uq_ingreso_examen_estudiante (examen_id, estudiante_id)`: un ingreso por estudiante y examen, también bajo concurrencia. Índices `idx_ingreso_examen_aula (examen_id, aula_id)` e `idx_ingreso_aula_hora (aula_id, registrado_en)`. **Inmutable**: desaparecen la anulación y sus tres columnas; no hay ruta que modifique ni borre; en PostgreSQL un disparador `BEFORE UPDATE OR DELETE` (`trg_ingresos_inmutables`) rechaza la operación (el `TRUNCATE` de la restauración no dispara disparadores de fila). Una corrección se asienta como incidencia.

**`incidencias`** — decisión: **una sola tabla** que absorbe `incidencias_ingreso`, `expulsiones` y `situaciones_excepcionales`. Lo pide la decisión 25 («las expulsiones y las situaciones excepcionales de la puerta quedan también como incidencia») y es lo que muestra la maqueta: una lista, un formulario, un «Atender». Lo que antes justificaba tres tablas (columnas obligatorias distintas) se resuelve con dos restricciones.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `examen_id` | FK `examenes` nulo, restrict | nulo solo en intentos que el sistema no pudo atribuir a un examen del aula |
| `aula_id` | FK `aulas` nulo | dónde ocurrió |
| `estudiante_id` | FK `estudiantes` nulo | si se lo identificó |
| `estudiante_texto` | string(150) nulo | lo que tecleó quien reporta («Nombre o código») |
| `ingreso_id` | FK `ingresos` nulo | obligatorio en una expulsión |
| `tipo` | string(30) | `INTENTO_NO_AUTORIZADO` \| `SUPLANTACION` \| `MATERIAL_NO_PERMITIDO` \| `EXPULSION` \| `CAMBIO_AULA` \| `OTRA` |
| `origen` | string(8) | `PERSONA` (alguien la reportó) \| `SISTEMA` (la puerta rechazó) |
| `causa` | string(20) nulo | solo `SISTEMA`: `NO_INSCRITO` \| `NO_HABILITADO` \| `CODIGO_INVALIDO` |
| `examen_correspondiente_id` | FK `examenes` nulo | solo `SISTEMA`: el examen que sí le tocaba a esa hora |
| `detalle` | text | mínimo 5 caracteres en las de `PERSONA` |
| `ocurrida_en` | timestamp | servidor |
| `reportada_por` | FK `users` nulo | |
| `acceso_aula_id` | FK `accesos_aula` nulo | |
| `atendida_en` | timestamp nulo | |
| `atendida_por` | FK `users` nulo | |
| timestamps | | |

Índices `idx_incidencia_examen_atendida (examen_id, atendida_en)`, `idx_incidencia_examen_aula (examen_id, aula_id)`, `idx_incidencia_aula_hora (aula_id, ocurrida_en)`; único parcial `uq_expulsion_por_ingreso (ingreso_id) WHERE tipo = 'EXPULSION'`; CHECK (pgsql) `tipo <> 'EXPULSION' OR ingreso_id IS NOT NULL`. Las de `origen = SISTEMA` no cuentan como «sin atender» ni salen en «En curso»: solo alimentan el reporte de intentos no correspondientes (requerimiento 9).

---

## 3. Contrato de la API HTTP

### 3.1 Reglas generales

- **Base**: todo bajo `/api`, en el grupo `web` de Laravel. **Autenticación por sesión con cookie**, como hoy: `Auth::login` + `session()->regenerate()`. El cliente envía `X-Requested-With: XMLHttpRequest` y el `X-XSRF-TOKEN` que axios toma solo de la cookie `XSRF-TOKEN`. No hay tokens ni Sanctum.
- **Formato**: JSON en `snake_case`. Listados `{"data": [...], "meta": {...}}`; un recurso `{"data": {...}}`; confirmación `{"message": "..."}`. Fechas `AAAA-MM-DD`, horas `HH:MM` (o `HH:MM:SS` en ingresos), instantes ISO 8601 con zona (`America/La_Paz`).
- **Paginación del lado del servidor**: `?pagina=1&por_pagina=25` (máximo 100); `meta = {"total": 849, "pagina": 1, "por_pagina": 25}` más los conteos que la pantalla muestra en sus filtros. Se implementa con `->forPage()` + `count()`; no se usa `LengthAwarePaginator` en la respuesta. **Búsqueda**: `?buscar=texto`, `ILIKE` sin distinguir mayúsculas y **sin tildes** (`translate(lower(col), 'áéíóúñ', 'aeioun')`), sobre los campos que indica cada ruta. Los catálogos de menos de 300 filas (facultades, edificios, aulas, grupos y exámenes de un docente) se devuelven enteros y la página los filtra en memoria.
- **Errores**:

| Código | Cuándo | Cuerpo |
|---|---|---|
| 401 | Sin sesión | `{"message":"No hay una sesión activa."}` |
| 403 | El rol no tiene el permiso | `{"message":"Tu rol no tiene permiso para esta sección.","permiso_requerido":"examenes"}` |
| 403 | Tiene el permiso pero el recurso no es suyo (examen ajeno, grupo ajeno, aula no asignada) | `{"message":"Este examen no es tuyo.","alcance":true}` |
| 404 | No existe | `{"message":"Examen no encontrado."}` |
| 409 | Contraseña temporal sin cambiar (cualquier ruta salvo `auth/logout`, `auth/contrasena`, `auth/sesion`) | `{"message":"Debes definir tu propia contraseña antes de continuar.","debe_cambiar_contrasena":true}` |
| 409 | Choque con el estado | `{"message":"…","codigo":"EXAMEN_CON_INGRESOS"}` |
| 419 | Sesión o CSRF vencidos | el de Laravel; el cliente lo trata como 401 |
| 422 | Validación | `{"message":"…","errors":{"campo":["…"]}}` |

- **Permisos** (`permiso:<clave>` en la ruta) y roles que los traen:

| Clave | Pantalla en la matriz | Adm. | Doc. | Aux. |
|---|---|:-:|:-:|:-:|
| `periodo_oferta` | Período y oferta académica | ● | | |
| `aulas_docentes` | Aulas y docentes | ● | | |
| `padron_estudiantes` | Padrón e inscripciones | ● | | |
| `mis_grupos` | Padrón e inscripciones (sus grupos) | | ● | |
| `examenes` | Exámenes | | ● | |
| `habilitacion` | Habilitación | | ● | |
| `codigos_qr` | Códigos QR | | ● | |
| `punto_control` | Punto de control | | ● | ● |
| `seguimiento_vivo` | Seguimiento en vivo | | ● | ● |
| `reportes_universidad` | Reportes de la universidad | ● | | |
| `reportes_examenes` | Reportes de sus exámenes | | ● | |
| `usuarios_roles` | Usuarios y roles | ● | | |
| `bitacora` | Bitácora | ● | | |
| `respaldo_restauracion` | Respaldo | ● | | |

- **Alcance**, además del permiso (lo comprueban las acciones con `Examenes\Application\Contracts\AlcanceExamenGateway` y `Academico\Application\Contracts\AlcanceDocenteGateway`):
  - *Docente del examen* = quien lo registró (`creado_por`) o el docente de alguno de sus grupos. Todos ven el examen, habilitan, emiten y envían QR, siguen «En curso», controlan cualquier aula y atienden incidencias. **Solo quien lo registró** lo modifica o lo elimina.
  - *Grupo del docente* = `grupos.docente_id` es su docente.
  - *Auxiliar de un aula* = `examen_aula.encargado_id` o fila en `examen_aula_apoyo`. Solo esa aula de ese examen, y solo **el día del examen**.
- **En vivo**: no hay WebSockets (el servidor de destino no admite procesos permanentes). Las tres vistas en vivo hacen **sondeo periódico** con `GET`: «En curso» cada **10 s**, punto de control cada **10 s**, asistencia del aula cada **15 s**. El sondeo se detiene con la pestaña oculta (`document.hidden`) y se reanuda con una carga inmediata. Las respuestas en vivo traen `hora_servidor`.
- La columna **Pantalla** nombra el archivo de `../Maquetacion/src/paginas/`.

### 3.2 Acceso — `routes/auth.php` (Administración)

| # | Método y ruta | Quién | Pantalla |
|---|---|---|---|
| 1 | `POST /api/auth/login` | público | `auth/Login` |
| 2 | `POST /api/auth/logout` | sesión | menú |
| 3 | `GET /api/auth/sesion` | sesión | arranque de la aplicación |
| 4 | `POST /api/auth/contrasena` | sesión | `auth/PrimerIngreso` |
| 5 | `POST /api/auth/contrasena/olvide` | público | `auth/Login` («Recuperar contraseña») |
| 6 | `POST /api/auth/contrasena/restablecer` | público | página del enlace del correo |
| 7 | `POST /api/auth/codigo-aula` | público | `auth/Login` («Entrar con código de aula», ver D-03) |

**1.** Cuerpo `{"usuario":"leticia.blanco","password":"…"}` (`usuario`: requerido, `^[a-z0-9._-]+$` sin distinguir mayúsculas, se compara en minúsculas). 200:

```json
{"message":"Autenticación correcta.",
 "user":{"id":7,"nombre":"Blanco Coca Leticia","usuario":"leticia.blanco","correo":null,
         "rol":"Docente","permisos":["mis_grupos","examenes","habilitacion","codigos_qr","punto_control","seguimiento_vivo","reportes_examenes"],
         "debe_cambiar_contrasena":false,"docente_id":412}}
```

Errores: 401 `{"message":"Usuario o contraseña incorrectos.","intentos_restantes":3}` (`intentos_restantes` solo si la cuenta existe); 423 `{"message":"Cuenta bloqueada.","minutos":15}` (bloqueo por intentos o `is_active = false`, este último sin `minutos`); 401 `{"message":"La contraseña temporal venció. Pide una nueva a la administración.","codigo":"TEMPORAL_VENCIDA"}`.
**3.** 200 `{"user":{…mismo objeto…}}`; 401. Es la fuente de verdad de la sesión del cliente.
**4.** `{"contrasena_actual":"…","contrasena_nueva":"…","contrasena_nueva_confirmation":"…"}`. `contrasena_actual` es obligatoria **salvo** que la cuenta esté con contraseña temporal. `contrasena_nueva`: mínimo 10, con mayúscula, minúscula y número (`Password::min(10)->mixedCase()->numbers()`), confirmada y distinta de la actual. 200 `{"message":"Contraseña actualizada."}`; 422.
**5.** `{"usuario":"…"}` → siempre 200 `{"message":"Si la cuenta tiene un correo registrado, te enviamos un enlace."}`. Enlace: `/restablecer-contrasena?token=…&usuario=…`, vigencia 60 min, un solo uso.
**6.** `{"usuario","token","contrasena_nueva","contrasena_nueva_confirmation"}` → 200; 422 `{"message":"El enlace no es válido o ya venció. Solicita uno nuevo."}`.
**7.** `{"codigo":"482913","nombre":"Vargas Arce Sergio"}` (`codigo` 6 dígitos; `nombre` mínimo 3). Válido solo el día del examen, desde 60 min antes del inicio hasta el final. Crea `accesos_aula`, guarda `acceso_aula_id` en la sesión y responde 200 `{"acceso":{"aula_id":31,"aula":"624","examen_id":1,"nombre":"Vargas Arce Sergio","hasta":"2026-10-12T09:45:00-04:00"}}`; 422 `{"message":"Código no válido."}`. Más de 3 intentos en 5 min desde una IP: 429 (`throttle:3,5`). Esa sesión **solo** puede usar las rutas 62–66 y 68, y solo para su aula. La ruta la implementa `Ingreso` (va en `routes/ingreso.php`), no `Administracion`.

### 3.3 Usuarios, bitácora y respaldo (Administración)

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 8 | `GET /api/usuarios` | `usuarios_roles` | `admin/Usuarios` |
| 9 | `POST /api/usuarios` | `usuarios_roles` | `admin/Usuarios` (Nuevo usuario) |
| 10 | `PUT /api/usuarios/{usuario}` | `usuarios_roles` | `admin/Usuarios` (Editar) |
| 11 | `POST /api/usuarios/{usuario}/contrasena-temporal` | `usuarios_roles` | `admin/Usuarios` (Restablecer contraseña) |
| 12 | `GET /api/permisos/matriz` | `usuarios_roles` | `admin/Usuarios` (Permisos por rol) |
| 13 | `GET /api/bitacora` | `bitacora` | `admin/Bitacora` |
| 14 | `GET /api/respaldos` | `respaldo_restauracion` | `admin/Respaldo` |
| 15 | `POST /api/respaldos` | `respaldo_restauracion` | «Respaldar ahora» |
| 16 | `GET /api/respaldos/{respaldo}/descargar` | `respaldo_restauracion` | «Descargar» |
| 17 | `POST /api/respaldos/{respaldo}/restaurar` | `respaldo_restauracion` | «Restaurar» |

**8.** `?buscar=` (nombre, usuario, correo) `&rol=Administrador|Docente|Auxiliar&bloqueadas=1&pagina&por_pagina=20`. Orden: más recientes primero.
`{"data":[{"id":5,"nombre":"Mamani Torrez Diego","usuario":"diego.mamani","correo":null,"rol":"Auxiliar","estado":"activo"}],"meta":{"total":268,"pagina":1,"por_pagina":20,"conteos":{"Administrador":4,"Docente":213,"Auxiliar":51,"bloqueadas":13}}}`. `estado`: `activo` \| `bloqueado`.
**9.** `{"nombre","usuario","correo":null,"rol":"Docente"}`: `nombre` mín. 3; `usuario` regex y único (se guarda en minúsculas; 422 `Ya existe`); `correo` opcional, formato de correo, único; `rol` uno de los tres. 201 `{"data":{…fila…},"contrasena_temporal":"Fa7-Kmq4-Ru9","enviada_a":null,"caduca_en":"2026-10-15T08:21:00-04:00","message":"Usuario creado."}`. La contraseña temporal se devuelve **esta única vez**; si hay correo además se envía. Asienta `usuario.crear`.
**10.** `{"nombre","usuario","correo","rol","activo":true}` → 200 `{"data":{…},"message":"Cambios guardados."}` (`"Cuenta bloqueada."` / `"Cuenta desbloqueada."` si cambió `activo`). 422 `{"message":"No puedes bloquear tu propia cuenta."}`; 404.
**11.** Sin cuerpo → 200 `{"contrasena_temporal":"…","enviada_a":"x@umss.edu.bo","caduca_en":"…"}`; pone `password_changed_at = null`. Asienta `usuario.restablecer_temporal`.
**12.** `{"roles":["Administrador","Docente","Auxiliar"],"pantallas":[{"pantalla":"Período y oferta académica","roles":["Administrador"]}, …13 filas…]}` (las dos claves de padrón salen como una sola fila).
**13.** `?buscar=` (nombre del usuario, descripción) `&rol=&operacion=usuario.crear&desde=2026-10-01&hasta=2026-10-12&pagina&por_pagina=25`; `hasta ≥ desde` (422). Orden: `fecha_operacion DESC, id DESC`.
`{"data":[{"id":991,"usuario":"Blanco Coca Leticia","rol":"Docente","operacion":"habilitacion.inhabilitar","operacion_texto":"Inhabilitó al estudiante","entidad":"Habilitación #19 · Introducción a la Programación","fecha":"2026-10-11T16:40:00-04:00"}],"meta":{"total":1503,"pagina":1,"por_pagina":25,"operaciones":[{"clave":"examen.registrar","texto":"Registró el examen"}],"fecha_minima":"2026-08-03"}}`. `usuario` nulo → `"Sistema"`. `entidad` = `descripcion`. `operacion_texto` sale de un mapa clave → texto en `Application/Queries/ConsultarBitacora` (clave desconocida: la clave misma). Solo `GET`: la bitácora no se edita.
**14.** `?filtro=todos|programados|puntuales|fallidos&pagina&por_pagina=15`. `{"data":[{"id":76,"fecha":"2026-10-12T03:00:00-04:00","tipo":"Programado","tamano_bytes":50331648,"estado":"correcto","restaurado":false,"archivo":"respaldo-20261012-030000.sql"}],"meta":{"total":88,"pagina":1,"por_pagina":15,"conteos":{"todos":88,"programados":75,"puntuales":13,"fallidos":4},"programacion":"Diario, 03:00","ultimo":"2026-10-12T03:00:00-04:00"}}`. `estado`: `correcto` \| `fallido`; `restaurado` es verdadero solo en el último restaurado.
**15.** 201 `{"data":{…fila…},"message":"Respaldo generado."}`; si falla, 500 `{"message":"No se pudo generar el respaldo.","data":{…fila fallida…}}`.
**16.** 200 `application/sql`, `Content-Disposition: attachment`; 404; 409 si es `fallido`.
**17.** 200 `{"message":"Restaurado al 12 oct 2026, 03:00.","tablas":31}`; 404; 409 `{"codigo":"RESPALDO_INCOMPATIBLE"}` si el archivo es anterior al replanteo (no nombra la tabla `periodos`).

### 3.4 Académico — `routes/academico.php`

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 18 | `GET /api/facultades` | sesión | filtros de facultad, colores |
| 19 | `GET /api/periodos` | `periodo_oferta` | `admin/Periodo` (Períodos detectados) |
| 20 | `GET /api/periodos/vigentes` | sesión | subtítulos «Período 2/2026» |
| 21 | `PUT /api/periodos/{periodo}` | `periodo_oferta` | `admin/Periodo` (ajustar fechas, ver D-11) |
| 22 | `POST /api/periodos/detectar` | `periodo_oferta` | `admin/Periodo` |
| 23 | `GET /api/periodos/resumen` | `periodo_oferta` | `admin/Periodo` (cabecera, Pendientes, Oferta por facultad) |
| 24 | `POST /api/oferta/importaciones` | `periodo_oferta` | `admin/Periodo` (Importar / Reintentar) |
| 25 | `GET /api/oferta/carreras` | `periodo_oferta` o `padron_estudiantes` | `admin/Oferta`, `estudiantes/Padron` |
| 26 | `GET /api/oferta/asignaturas` | `periodo_oferta` | `admin/Oferta` |
| 27 | `GET /api/oferta/asignaturas/{asignatura}/grupos` | `periodo_oferta` | `admin/Oferta` (al abrir una asignatura) |
| 28 | `GET /api/edificios` | sesión | `admin/Aulas`, `MapaCampus` en todas las pantallas |
| 29 | `GET /api/aulas` | sesión | `admin/Aulas`, `examenes/RegistrarExamen` (paso 3) |
| 30 | `GET /api/docentes` | `aulas_docentes` | `admin/Docentes` |
| 31 | `GET /api/docentes/{docente}` | `aulas_docentes` | `admin/Docentes` (detalle) |
| 32 | `POST /api/docentes/{docente}/cuenta` | `aulas_docentes` | `admin/Docentes` (Activar cuenta) |
| 33 | `GET /api/docente/grupos` | `mis_grupos` | `docente/MisGrupos`, `examenes/RegistrarExamen` |

**18.** `{"data":[{"id":1,"clave":"fcyt","sigla":"FCyT","nombre":"Ciencias y Tecnología","color":"#B90813","edificios":21,"aulas":93}]}`.
**19.** `?pagina&por_pagina=4`, orden: vigentes primero, luego por año y número descendente. `{"data":[{"id":2,"codigo":"2/2026","tipo":"Semestre 2","fecha_inicio":"2026-08-10","fecha_fin":"2026-12-26","estado":"Vigente","nota":"30 carreras · 3.169 grupos"}],"meta":{…}}`. `estado`: `Vigente` \| `Cerrado` \| `Próximo` \| `Sin fechas`.
**20.** `{"data":[{"id":2,"codigo":"2/2026","tipo":"Semestre 2","fecha_inicio":"…","fecha_fin":"…","ventanas":{"primeros_parciales":["2026-10-12","2026-10-31"]}}],"principal":"2/2026"}`. `principal`: el vigente con más grupos.
**21.** `{"fecha_inicio":"2026-02-02","fecha_fin":"2026-12-19"}` (`fecha_fin > fecha_inicio`) → 200 `{"data":{…}}`. Asienta `periodo.ajustar`.
**22.** 200 `{"creados":1,"actualizados":3}`.
**23.** `{"hoy":"2026-10-12","periodo":{"codigo":"2/2026","estado":"Vigente"},"ventana":{"nombre":"Primeros parciales","desde":"2026-10-12","hasta":"2026-10-31"},"pendientes":{"docentes_sin_cuenta":{"valor":636,"de":849},"grupos_sin_lista":{"valor":3212,"de":4115}},"facultades":[{"sigla":"FCyT","nombre":"Ciencias y Tecnología","carreras":20,"grupos":1100,"aulas":93,"importacion":{"estado":"importada","fecha":"2026-08-03","error":null}}]}`. `importacion.estado`: `sin` \| `importando` \| `importada` \| `fallo`.
**24.** `{"facultad":"fach"}` → corre la importación dentro de la petición. 201 `{"data":{"estado":"importada","fecha":"2026-10-12","resumen":{"carreras":6,"asignaturas":165,"grupos":383,"grupos_sin_docente":17,"docentes":169,"aulas":33,"sesiones_descartadas":0}}}`; 422 `{"message":"No se pudo importar FACH.","data":{"estado":"fallo","error":"…"}}`; 409 `{"codigo":"IMPORTACION_EN_CURSO"}` si hay una fila `IMPORTANDO` de esa facultad de hace menos de 5 min.
**25.** `?facultad=fcyt` → `{"data":[{"id":14,"codigo":"411702","nombre":"Licenciatura en Ingeniería de Sistemas","regimen":"Semestral"}]}`.
**26.** `?periodo=2&facultad=fcyt&carrera=14&nivel=SEMESTRE 1&buscar=` (nombre, código, docente de algún grupo) `&pagina&por_pagina=15`. `{"data":[{"id":88,"codigo":"2008019","nombre":"Álgebra I","nivel":"Semestre 1","grupos":9,"inscritos":412}],"meta":{"total":63,"pagina":1,"por_pagina":15,"grupos":168,"niveles":["Semestre 1","Semestre 2"]}}`.
**27.** `?periodo=2&facultad=fcyt&buscar=&pagina&por_pagina=10` (con `buscar`, los grupos del docente que coincide van primero). `{"data":[{"id":501,"codigo":"5","docente":"Taborga Acha Fidel","horarios":[{"dia":"MI","hora":"14:15-15:45","aula":"617"}],"inscritos":58}],"meta":{…}}`. `docente` nulo = «Por designar».
**28.** `?facultad=fcyt` (opcional). Entero, sin paginar: `{"data":[{"id":3,"clave":"fcyt_blk_0_50366","facultad":"FCyT","nombre":"Edificio de Laboratorios Básicos","poligono":[[-66.14435,-17.39445]],"centro":[-66.14432,-17.39463],"aulas":["680B","680J"],"pisos":[{"nombre":"1° Piso","aulas":["A740"]}]}],"meta":{"caja":{"lon":[-66.14864,-66.14414],"lat":[-17.39548,-17.39258]}}}`. `aulas` lleva **todas** las del edificio; `pisos` solo si se conocen. Se sirve con `Cache-Control: private, max-age=3600`.
**29.** `?facultad=&ubicadas=1` → `{"data":[{"id":31,"nombre":"624","edificio_id":7,"edificio":"Edificio Académico 2","piso":"1° Piso","facultad":"FCyT"}]}`. Entero (≈ 290 filas), orden natural por nombre.
**30.** `?periodo=&facultad=fcyt&sin_cuenta=1&varias_facultades=1&buscar=` (nombre) `&pagina&por_pagina=20`, orden por nombre. `{"data":[{"id":412,"nombre":"Blanco Coca Leticia","facultades":[{"sigla":"FCyT","grupos":3}],"cuenta":"activa"}],"meta":{"total":849,"pagina":1,"por_pagina":20,"conteos":{"todas":849,"FCyT":287,"FCE":214,"FHCE":211,"FACH":169,"sin_cuenta":636,"varias_facultades":29}}}`. `cuenta`: `activa` \| `temporal` (aún no cambió la contraseña) \| `sin_cuenta`.
**31.** `{"data":{"id":412,"nombre":"…","grupos":3,"facultades":[{"sigla":"FCyT","grupos":3,"materias":["Introducción a la Programación","Taller de Ingeniería de Software"]}],"cuenta":{"estado":"activa","usuario":"leticiablanco","correo":null},"usuario_sugerido":"leticiablanco"}}`. `cuenta` nulo si no tiene. `usuario_sugerido`: ver D-02.
**32.** `{"usuario":"leticia.blanco","correo":null}` → 201 `{"data":{"usuario":"leticia.blanco","contrasena_temporal":"Fa7-Kmq4-Ru9","enviada_a":null,"caduca_en":"…"},"message":"Cuenta activada."}`. Crea la cuenta con rol `Docente` y la une al docente. 409 `{"codigo":"YA_TIENE_CUENTA"}`; 422 `usuario` en uso. Asienta `docente.activar_cuenta`.
**33.** `?periodo=` (por defecto, todos los vigentes). Entero: `{"data":[{"id":501,"codigo":"2","asignatura":{"id":90,"codigo":"2010010","nombre":"Introducción a la Programación"},"nivel":"Semestre 1","facultad":"FCyT","periodo":"2/2026","horarios":[{"dia":"LU","hora":"08:15-09:45","aula":"691B"}],"inscritos":73,"con_lista":true}],"meta":{"periodo":"2/2026","asignaturas":2,"sin_lista":1}}`.

### 3.5 Estudiantes — `routes/estudiantes.php`

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 34 | `GET /api/estudiantes` | `padron_estudiantes` | `estudiantes/Padron` (pestaña Estudiantes) |
| 35 | `GET /api/estudiantes/resumen` | `padron_estudiantes` | `estudiantes/Padron` (cifras) |
| 36 | `POST /api/estudiantes/cargas` | `padron_estudiantes` | `estudiantes/Padron` (Cargar inscripciones) |
| 37 | `GET /api/estudiantes/conflictos` | `padron_estudiantes` | `estudiantes/Padron` (pestaña Conflictos) |
| 38 | `POST /api/estudiantes/conflictos/{conflicto}/resolucion` | `padron_estudiantes` | «Mantener padrón» / «Usar carga nueva» |
| 39 | `GET /api/inscritos/plantilla` | `padron_estudiantes` o `mis_grupos` | «Descargar plantilla» |
| 40 | `GET /api/docente/grupos/{grupo}/inscritos` | `mis_grupos` + grupo propio | `docente/MisGrupos` (lista) |
| 41 | `POST /api/docente/grupos/{grupo}/inscritos/carga` | `mis_grupos` + grupo propio | `docente/MisGrupos` (Cargar archivo) |

**34.** `?facultad=&carrera=&buscar=` (apellidos y nombres, código, documento) `&pagina&por_pagina=25`, orden por apellidos y nombres. `{"data":[{"id":9,"codigo":"202105877","nombre":"Aguilar Cossío, Mariana","documento":"7361424","facultad":"FCyT","carrera":"Ing. Informática","grupos":5,"origen":"Verificado"}],"meta":{"total":4218,"pagina":1,"por_pagina":25,"conteos":{"todas":4218,"FCyT":1874,"FCE":1102,"FHCE":768,"FACH":474}}}`. `origen`: `Verificado` (`verificado = true`) \| `Docente`. `grupos`: inscripciones en períodos vigentes.
**35.** `{"estudiantes":4218,"inscripciones":21090,"cargados_por_docentes":1054,"conflictos_pendientes":45}`.
**36.** `multipart/form-data`: `facultad` (clave), `archivo` (`.csv` o `.xlsx`, máx. 10 MB). Columnas, en este orden, con encabezado opcional: `codigo_universitario`, `documento_identidad`, `nombres`, `apellidos`, `codigo_asignatura`, `grupo`, `carrera` (código, opcional). Una fila por inscripción. Reglas de 3.5.1 con `via = ADMINISTRACION` (los estudiantes quedan `verificado = true`). 200:

```json
{"message":"Carga procesada.","archivo":"inscripciones_fcyt.xlsx",
 "resumen":{"filas":1248,"nuevos":310,"reutilizados":921,"ya_inscritos":12,"rechazados":3,"conflictos":2},
 "rechazos":[{"fila":31,"motivo":"Sin documento de identidad"}],
 "conflictos":[{"fila":17,"codigo":"202400458","motivo":"Documento distinto al del padrón"}]}
```

**37.** `?estado=pendiente&pagina&por_pagina=5`. `{"data":[{"id":4,"codigo":"202400311","tipo":"Documento distinto","guardado":{"nombre":"Rojas Lima, Carla","documento":"8123456","por":"Administración · 5 ago 2026"},"nuevo":{"nombre":"Rojas Lima, Carla","documento":"8123465","por":"Blanco Coca Leticia · Introducción a la Programación, grupo 2"},"inscripcion_en_espera":true}],"meta":{…}}`.
**38.** `{"resolucion":"MANTENER_PADRON"}` o `"USAR_CARGA"` → 200 `{"message":"202400311 sin cambios."}` / `"202400311 actualizado."`. Crea la inscripción en espera; con `USAR_CARGA` reemplaza documento, nombres y apellidos y marca `verificado`. 409 si ya estaba resuelto. Asienta `padron.resolver_conflicto`.
**39.** `?alcance=grupo|facultad` → `.xlsx` con la fila de encabezados (`plantilla_inscritos.xlsx`).
**40.** `?buscar=` (nombre, código, documento) `&pagina&por_pagina=25`. `{"data":[{"id":9,"codigo":"202105877","nombre":"Aguilar Cossío, Mariana","documento":"7361424","origen":"Administración"}],"meta":{"total":73,…}}`. `origen` = `via` de la inscripción (`Docente` \| `Administración`). 403 de alcance si el grupo no es suyo.
**41.** `multipart`: `archivo`. Columnas: `codigo_universitario`, `documento_identidad`, `nombres`, `apellidos`. Misma respuesta que la 36, con `via = DOCENTE`. 409 `{"codigo":"PERIODO_CERRADO"}` si el período del grupo no está vigente. Asienta `inscritos.cargar`.

#### 3.5.1 Reglas de una carga (`Estudiantes/Application/Actions/CargarInscritos`)

Por fila, en este orden: (1) faltan columnas o datos obligatorios, código no numérico de 5 a 10 dígitos, o código repetido en el archivo para el mismo grupo → **rechazada** con su número de fila y motivo (`Faltan datos obligatorios`, `Sin documento de identidad`, `Código universitario no válido`, `Se repite en el archivo`, `El grupo no existe en la oferta`); (2) el código no existe → se **crea** al estudiante (`origen` = vía) y se lo inscribe (*nuevo*); (3) existe y el documento difiere, o apellidos y nombres difieren una vez normalizados → **conflicto**: no se toca al estudiante, la inscripción queda en espera; (4) existe, coincide y ya está inscrito en el grupo → *ya inscrito*, sin cambios; (5) existe y coincide → se lo inscribe (*reutilizado*). La carga es **aditiva**: nunca quita inscritos. El docente **nunca modifica** a un estudiante existente (decisión 7); una carga de administración sobre un estudiante de origen docente que coincide lo marca `verificado`. Todo en una transacción por archivo; cada carga deja fila en `cargas_inscritos`.

### 3.6 Exámenes — `routes/examenes.php`

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 42 | `GET /api/examenes` | `examenes` | `examenes/Examenes`, selector de examen |
| 43 | `GET /api/examenes/tipos` | `examenes` | `examenes/RegistrarExamen` (paso 1) |
| 44 | `GET /api/examenes/{examen}` | `examenes` | `examenes/RegistrarExamen` (edición) |
| 45 | `POST /api/examenes` | `examenes` | «Registrar examen» |
| 46 | `PUT /api/examenes/{examen}` | `examenes` + lo registró | «Guardar examen» |
| 47 | `DELETE /api/examenes/{examen}` | `examenes` + lo registró | sin botón en la maqueta (ver D-12) |
| 48 | `GET /api/examenes/opciones/grupos` | `examenes` | `RegistrarExamen` (paso 2) |
| 49 | `GET /api/examenes/opciones/aulas` | `examenes` | `RegistrarExamen` (paso 3: sugeridas y compartidas) |
| 50 | `GET /api/auxiliares` | `examenes` | `RegistrarExamen` (paso 4) |
| 51 | `POST /api/auxiliares` | `examenes` | «Auxiliar nuevo» |
| 52 | `GET /api/examenes/{examen}/aulas/{aula}/codigo` | `examenes` + docente del examen | diálogo «Código de acceso» |
| 53 | `POST /api/examenes/{examen}/aulas/{aula}/codigo` | ídem | «Regenerar» |

**42.** `?periodo=` (por defecto, vigentes). Entero (un docente tiene como mucho unas decenas), orden por fecha y hora:

```json
{"data":[{"id":1,"asignatura":{"id":90,"codigo":"2010010","nombre":"Introducción a la Programación"},
  "tipo":"PRIMER_PARCIAL","tipo_texto":"Primer parcial","fecha":"2026-10-12","hora":"08:15","duracion":90,
  "grupos":["1","2","3","6"],"inscritos":264,"aulas":["691A","691B","691E","617","624"],"auxiliares":4,
  "habilitados":255,"no_habilitados":9,"sin_revisar":0,"qr_emitidos":255,
  "avance":{"grupos":"ok","aulas":"ok","auxiliares":"ok","habilitacion":"ok","qr":"ok"},
  "estado":"Listo","accion":{"clave":"ver_qr","paso":null},
  "propio":true,"registrado_por":"Blanco Coca Leticia","junto_con":0,"es_hoy":true,"rendido":false}],
 "meta":{"periodo":"2/2026","hoy":"2026-10-12","hora_servidor":"2026-10-12T08:21:00-04:00"}}
```

`avance.*`: `ok` \| `ahora` \| `falta`, calculado así: `grupos` siempre `ok`; `aulas` `ok` si tiene al menos una, si no `ahora`; `auxiliares` (quién atiende) `ok` si hay aulas, si no `falta`; `habilitacion` `ok` si `sin_revisar = 0` y `habilitados > 0`, `ahora` si hay aulas, si no `falta`; `qr` `ok` si todos los habilitados tienen código, `ahora` si la habilitación está `ok`, si no `falta`. `estado`: `Faltan aulas` \| `Falta habilitar` \| `Faltan códigos QR` \| `Listo`. `accion.clave`: `elegir_aulas` (paso 2) \| `habilitar` \| `emitir_qr` \| `ver_qr`. `auxiliares`: personas distintas con rol Auxiliar entre encargados y apoyo. `junto_con`: otros exámenes del mismo docente con igual fecha y hora y al menos un aula en común. `rendido`: la fecha y hora de fin ya pasaron.
**43.** `{"data":[{"valor":"PRIMER_PARCIAL","etiqueta":"Primer parcial"},{"valor":"SEGUNDO_PARCIAL","etiqueta":"Segundo parcial"},{"valor":"FINAL","etiqueta":"Examen final"},{"valor":"SEGUNDA_INSTANCIA","etiqueta":"Segunda instancia"}]}`.
**44.** Lo de la 42 más `"normas":"Sin celular…"`, `"grupos_detalle":[{"id":501,"codigo":"2","docente":"Blanco Coca Leticia","inscritos":73,"propio":true}]` y `"aulas_detalle":[{"aula_id":31,"nombre":"624","ubicacion":"Edificio Académico 2 · 1° Piso","edificio_id":7,"encargado":{"id":12,"nombre":"Vargas Arce Sergio","es_docente":false},"apoyo":[{"id":9,"nombre":"Rojas Ledezma Andrea"}],"usa_codigo":true}]`.
**45 y 46.** Todo el asistente se guarda de una vez (la maqueta no guarda por pasos):

```json
{"asignatura_id":90,"tipo":"PRIMER_PARCIAL","fecha":"2026-10-12","hora_inicio":"08:15","duracion_minutos":90,
 "normas":"Sin celular. Hoja de fórmulas A4.",
 "grupos":[501,502,503,506],
 "aulas":[{"aula_id":30,"encargado_id":7,"apoyo":[9]},{"aula_id":31,"encargado_id":12,"apoyo":[]}]}
```

Validación: `asignatura_id` existe y el docente tiene al menos un grupo suyo en ella en un período vigente; `tipo` de la 43; `fecha` dentro del período de los grupos; `hora_inicio` `HH:MM`; `duracion_minutos` entero 15–480 (`Entre 15 y 480`); `normas` opcional, máx. 2.000; `grupos` mínimo 1, todos de esa asignatura y del mismo período, **de cualquier docente**, y ninguno en otro examen del mismo `tipo` (422 `El grupo 3 ya tiene un primer parcial.`); `aulas` puede ir vacía (queda «Faltan aulas»), sin repetir `aula_id`; `encargado_id` un usuario con rol Docente o Auxiliar (por defecto, quien registra); `apoyo` usuarios con rol Auxiliar, distintos del encargado. **No se valida capacidad ni solape de aulas.** 201/200 `{"data":{…como la 44…},"message":"Examen registrado."}` (`"Examen guardado."`).
Al modificar: quitar un aula deja sin aula (`aula_id = null`) a sus habilitados; quitar un grupo borra las habilitaciones y los códigos QR de quienes ya no están inscritos en ningún grupo del examen; cambiar fecha, hora o aulas **no** invalida los códigos QR (el token no lleva esos datos). Con ingresos registrados solo se pueden cambiar `normas`, encargados y apoyo: lo demás da 409 `{"codigo":"EXAMEN_CON_INGRESOS"}`. Asienta `examen.registrar` / `examen.modificar` y, si cambia el personal, `examen.asignar_auxiliar`.
**47.** 204; 409 `EXAMEN_CON_INGRESOS`.
**48.** `?asignatura_id=90` → `{"propios":[{"id":501,"codigo":"2","docente":"Blanco Coca Leticia","inscritos":73}],"otros":[{"id":503,"codigo":"3","docente":null,"inscritos":61}]}`. Enteros.
**49.** `?grupos[]=501&grupos[]=502&fecha=2026-10-12&hora_inicio=08:15&duracion_minutos=90&examen_id=1` (`examen_id` para excluirse a sí mismo) → `{"sugeridas":[30,31],"compartidas":{"30":["Cálculo I · Primer parcial"]}}`. `sugeridas`: aulas **ubicadas** de los horarios de esos grupos. `compartidas`: por aula, los exámenes que se solapan en fecha y hora (`inicio < fin_otro` y `inicio_otro < fin`). Es un **aviso**: la 45 no lo impide. La lista de aulas es la 29.
**50.** `?buscar=` → `{"data":[{"id":12,"nombre":"Vargas Arce Sergio","usuario":"sergiovargas","mio":true}]}`: primero los que registró o ya usó este docente; con `buscar` (mín. 3 letras), cualquier cuenta de rol Auxiliar activa (máx. 20).
**51.** `{"nombre":"Vargas Arce Sergio","codigo_universitario":"202000123"}` (`nombre` mín. 3; código 5–10 dígitos). Crea la cuenta con rol Auxiliar, usuario propuesto por D-02, correo `202000123@est.umss.edu`, contraseña temporal enviada a ese correo, y la fila de `auxiliares`. 201 `{"data":{"id":12,"nombre":"…","usuario":"sergiovargas","mio":true},"message":"Auxiliar agregado."}`; 409 `{"message":"Auxiliar ya registrado.","data":{…el existente…}}` si ya hay un auxiliar con ese código. Asienta `auxiliar.registrar`.
**52.** `{"data":{"codigo":"482913","generado_en":"…","encargado":"Vargas Arce Sergio","examen":"2026-10-12 08:15"}}`; si no hay código, lo genera.
**53.** 200, mismo cuerpo, `"message":"Código regenerado."`. El anterior deja de servir; los accesos ya abiertos con él se cierran.

### 3.7 Habilitación — `routes/habilitacion.php`

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 54 | `GET /api/examenes/{examen}/habilitaciones` | `habilitacion` + docente del examen | `habilitacion/Habilitacion` |
| 55 | `POST /api/examenes/{examen}/habilitaciones` | ídem | «Habilitar» / «Inhabilitar» |
| 56 | `POST /api/examenes/{examen}/reparto` | ídem | «Repartir» |

**54.** `?grupo=501&aula=31&condicion=habilitado|no|pendiente&buscar=` (nombre, código, documento) `&pagina&por_pagina=25`; orden por apellidos y nombres.

```json
{"data":[{"estudiante_id":9,"codigo":"202105877","nombre":"Aguilar Cossío, Mariana","documento":"7361424",
          "grupo":"2","estado":"habilitado","aula":"691B","motivo":null}],
 "meta":{"total":264,"pagina":1,"por_pagina":25,
  "cifras":{"inscritos":264,"habilitados":255,"no_habilitados":9,"sin_revisar":0,"sin_aula":0},
  "por_aula":[{"aula_id":30,"nombre":"691A","asignados":51}],
  "condiciones":{"todos":264,"habilitado":255,"no":9,"pendiente":0},
  "grupos":[{"id":501,"codigo":"2","propio":true}]}}
```

`cifras` y `por_aula` son del examen entero; `condiciones` cuenta sobre lo filtrado por grupo, aula y texto (sin el filtro de condición). Un estudiante inscrito en dos grupos del examen sale una vez, con el grupo de menor código.
**55.** `{"habilitado":false,"motivo":"Adeuda la matrícula del semestre","estudiantes":[9,14]}` o, para «Seleccionar los N», `{"habilitado":true,"todos":true,"filtros":{"grupo":501,"aula":null,"condicion":"pendiente","buscar":""}}`. `motivo` obligatorio al inhabilitar (5–1.000 caracteres; 422 `Mínimo 5 caracteres`) y se ignora al habilitar. Habilitar pone `habilitado = true, motivo = null` y **no toca el aula**; inhabilitar pone `habilitado = false`, el motivo y `aula_id = null`. Los `estudiantes` deben estar inscritos en un grupo del examen (422). 200 `{"message":"2 inhabilitados.","afectados":2,"cifras":{…}}`. 409 `{"codigo":"ESTUDIANTE_CON_INGRESO"}` al inhabilitar a quien ya ingresó. Asienta `habilitacion.habilitar` / `habilitacion.inhabilitar` (una fila por lote, con la cantidad).
**56.** Sin cuerpo. Toma **solo** los habilitados sin aula, por apellidos y nombres, y manda cada uno al aula con menos asignados en ese momento (empate: la primera en el orden de `examen_aula.id`). No mueve a quien ya tiene aula. 200 `{"message":"12 estudiantes en 5 aulas.","repartidos":12,"por_aula":[…]}`; `"Sin estudiantes por repartir."` con `repartidos: 0`; 409 `{"codigo":"SIN_AULAS"}`. Asienta `habilitacion.repartir`.

### 3.8 Ingreso — `routes/ingreso.php`

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 57 | `GET /api/examenes/{examen}/codigos-qr` | `codigos_qr` + docente del examen | `docente/CodigosQr` (lista) |
| 58 | `POST /api/examenes/{examen}/codigos-qr` | ídem | «Emitir códigos» |
| 59 | `GET /api/examenes/{examen}/codigos-qr/{estudiante}` | ídem | tarjeta «Credencial» |
| 60 | `POST /api/examenes/{examen}/codigos-qr/envios` | ídem | «Enviar por correo» |
| 61 | `GET /api/examenes/{examen}/codigos-qr/hoja` | ídem | «Imprimir credenciales» |
| 62 | `GET /api/puerta/aulas` | `puesto` | `ingreso/PuntoControl` (diálogo «Aula») |
| 63 | `GET /api/puerta/aulas/{aula}` | `puesto` + atiende el aula | `ingreso/PuntoControl` (cabecera e ingresos) — **sondeo 10 s** |
| 64 | `POST /api/puerta/aulas/{aula}/verificaciones` | ídem | leer QR / «Verificar» |
| 65 | `POST /api/puerta/aulas/{aula}/excepciones` | ídem | «Situación excepcional» / «Admitir como excepción» |
| 66 | `POST /api/puerta/ingresos/{ingreso}/expulsion` | ídem | diálogo «Expulsión» |
| 67 | `GET /api/examenes/{examen}/incidencias` | `seguimiento_vivo` | `docente/EnCurso`, `monitoreo/Asistencia` |
| 68 | `POST /api/examenes/{examen}/incidencias` | `seguimiento_vivo` o `puesto` | botón «Incidencia» |
| 69 | `POST /api/incidencias/{incidencia}/atencion` | `seguimiento_vivo` + docente del examen | «Atender» |

`puesto` es el middleware `Ingreso/Http/Middleware/ExigirPuesto`: deja pasar a un usuario con permiso `punto_control` **o** a una sesión abierta con código de aula (ruta 7), y en ese caso solo para su aula.

**57.** `?grupo=&aula=&buscar=` (nombre, código) `&pagina&por_pagina=20`. Solo habilitados. `{"data":[{"estudiante_id":9,"codigo":"202105877","nombre":"…","grupo":"2","aula":"691B","emitido":true,"enviado_en":null}],"meta":{"total":255,"pagina":1,"por_pagina":20,"habilitados":255,"emitidos":255,"enviados":0,"estado":"emitido"}}`. `estado` del examen: `sin_emitir` \| `emitido` (todos los habilitados tienen código) \| `enviado` (todos enviados).
**58.** Idempotente: emite a los habilitados que no tienen. 200 `{"message":"255 códigos emitidos.","emitidos":255}`; 409 `{"codigo":"SIN_HABILITADOS"}`. Asienta `qr.emitir`.
**59.** `{"data":{"facultad":"FCyT","examen":"Primer parcial · Introducción a la Programación","fecha":"2026-10-12","hora":"08:15","duracion":90,"estudiante":"Aguilar Cossío, Mariana","codigo":"202105877","aula":{"nombre":"691B","edificio":"Edificio Académico 2","piso":"1° Piso","edificio_id":7},"emitido":true,"qr":"data:image/png;base64,…"}}`. `aula` nulo = «Sin aula»; `qr` nulo si no está emitido. El QR codifica **solo el token**.
**60.** `{"grupo":null,"aula":null,"buscar":""}` (los mismos filtros de la lista; vacío = todos). Encola un correo por estudiante con código emitido a `<codigo>@est.umss.edu`, registra `envios_correo` y marca `enviado_en` al enviarse. 202 `{"message":"255 códigos enviados.","encolados":255}`; 409 `{"codigo":"SIN_EMITIR"}`. Asienta `qr.enviar`.
**61.** Mismos filtros por *query*. 200 `text/html`: hoja imprimible con una credencial por estudiante (QR, nombre, código, examen, fecha y hora, **aula, edificio y piso**); el navegador imprime o guarda como PDF. Es la `resources/views/qr/hoja.blade.php` actual, ampliada.
**62.** `{"data":[{"aula_id":30,"nombre":"691A","ubicacion":"Edificio Académico 2 · 1° Piso","examenes":[{"id":1,"texto":"Primer parcial · Introducción a la Programación","hora":"08:15"}],"abierta":true}],"hoy":"2026-10-12"}`. Docente: las aulas de sus exámenes de hoy; auxiliar: las que atiende hoy; código de aula: la suya. `abierta`: ahora está entre 60 min antes del inicio y el final de alguno de sus exámenes.
**63.** `?ingresos=recientes|todos&buscar=`.

```json
{"aula":{"id":30,"nombre":"691A","ubicacion":"Edificio Académico 2 · 1° Piso","edificio_id":7},
 "hora_servidor":"2026-10-12T08:21:07-04:00","abierta":true,
 "examenes":[{"id":1,"asignatura":"Introducción a la Programación","tipo":"Primer parcial","hora":"08:15",
              "ingresados":38,"asignados":51,"normas":"Sin celular. Hoja de fórmulas A4."},
             {"id":8,"asignatura":"Cálculo I","tipo":"Primer parcial","hora":"08:15","ingresados":21,"asignados":40,"normas":null}],
 "ingresos":[{"id":7001,"estudiante_id":9,"codigo":"202105877","nombre":"Aguilar Cossío, Mariana",
              "hora":"08:14:52","examen_id":1,"excepcion":false,"expulsado":false}],
 "total_ingresos":59}
```

`examenes`: los que tienen esa aula y cuya ventana (60 min antes del inicio → fin) contiene la hora del servidor; si ninguno, los de hoy en esa aula con `abierta: false`. `ingresos`: los 5 últimos (`recientes`) o todos, filtrables por nombre o código.
**64.** `{"token":"k3J…48 caracteres"}` (QR) **o** `{"identificador":"202105877"}` (código universitario o documento; 5–10 dígitos, 422 `Entre 5 y 10 dígitos`). **Verificar y registrar es una sola operación atómica.** Siempre 200 con un `veredicto`:

| `veredicto` | Cuándo | Efecto | Campos |
|---|---|---|---|
| `admitido` | Inscrito en un examen del aula, habilitado, es su aula, sin ingreso | **Crea el ingreso** | `ingreso:{id,hora}`, `dato:"Registrado a las 08:21:07"` |
| `no_habilitado` | Inscrito, con `habilitado = false` o sin revisar | Incidencia `SISTEMA`/`NO_HABILITADO` | `dato` = motivo (o `Sin revisar`), `pie:"Registrado por Omonte Ojalvo Jose Roberto · 6 oct"` |
| `otra_aula` | Habilitado, pero su aula es otra | Nada | `aula_correcta:{id,nombre,edificio,piso,edificio_id}` |
| `ya_ingreso` | Ya tiene ingreso en ese examen (o perdió la carrera contra otro puesto: se captura la `QueryException` del único) | Nada | `dato:"Ingresó a las 08:02:41 en esta aula"` (o `en el aula 617`) |
| `expulsado` | Fue expulsado de ese examen | Nada | `dato:"Expulsado a las 08:40"` |
| `no_corresponde` | El estudiante existe pero no está en ningún examen del aula a esta hora; o el QR es de un examen que no está en el aula; o el token no existe | Incidencia `SISTEMA`/`NO_INSCRITO` o `CODIGO_INVALIDO`, con `examen_correspondiente_id` si tiene otro examen en la ventana | `dato:"Sin examen en el aula 691A a esta hora"`, `pie:"Intento registrado"`, `examen_correspondiente:{texto,aula}` o nulo |

Cuerpo común: `{"veredicto":"…","estudiante":{"id":9,"codigo":"202105877","nombre":"…"},"examen":{"id":1,"asignatura":"…","tipo":"…"},"dato":"…","pie":"…"}` (`estudiante` y `examen` nulos cuando no se resolvieron). Con `identificador` que no está en el padrón: 422 `{"errors":{"identificador":["Sin registro"]}}` (la maqueta lo muestra en el campo). Con el aula sin examen abierto: 409 `{"codigo":"AULA_SIN_EXAMEN"}`. Si el estudiante está en **dos** exámenes del aula a la vez, se resuelve el que no tiene ingreso; con QR, el del token. Medio: `QR` con token; `CODIGO` o `DOCUMENTO` según con qué coincidió. Asienta `ingreso.registrar`.
**65.** `{"tipo":"CAMBIO_AULA","motivo":"Su aula está llena","estudiante_id":9,"examen_id":1}`. `tipo`: `PROBLEMA_IDENTIFICACION` \| `CAMBIO_AULA` \| `NO_HABILITADO`; `motivo` obligatorio (5–500); `estudiante_id` y `examen_id` obligatorios en `CAMBIO_AULA`. Crea una incidencia `PERSONA` (tipos: `CAMBIO_AULA` → `CAMBIO_AULA`; `NO_HABILITADO` → `INTENTO_NO_AUTORIZADO`; `PROBLEMA_IDENTIFICACION` → `OTRA`). **Solo `CAMBIO_AULA` admite**: crea el ingreso en esta aula con `es_excepcion = true` (mismas comprobaciones de habilitado, sin ingreso y sin expulsión). 201 `{"message":"Situación excepcional registrada: cambio de aula.","incidencia_id":40,"ingreso":{"id":7002,"hora":"08:22:10"}}` (`ingreso` nulo en los otros dos tipos). Asienta `incidencia.registrar`.
**66.** `{"motivo":"Usó el celular"}` (5–500). Crea la incidencia `EXPULSION` sobre ese ingreso; el estudiante no vuelve a entrar a ese examen. 201 `{"message":"Expulsión registrada."}`; 409 `{"codigo":"YA_EXPULSADO"}`. El ingreso no se modifica. Asienta `incidencia.expulsion`.
**67.** `?aula=&estado=sin_atender|todas`. Solo `origen = PERSONA`, más recientes primero, entero. `{"data":[{"id":40,"tipo":"MATERIAL_NO_PERMITIDO","tipo_texto":"Material no permitido","aula":"617","estudiante":"Rocha Peredo, Fabiola · 222011223","detalle":"Calculadora programable","hora":"08:19","por":"Peredo Claros Lucía","rol":"Auxiliar","atendida":false}],"meta":{"sin_atender":2,"todas":3}}`. Docente del examen: todas; auxiliar: `aula` obligatorio y debe ser la suya.
**68.** `{"aula_id":31,"tipo":"MATERIAL_NO_PERMITIDO","estudiante":"Nombre o código","detalle":"…"}`. `tipo`: `INTENTO_NO_AUTORIZADO` \| `SUPLANTACION` \| `MATERIAL_NO_PERMITIDO` \| `CAMBIO_AULA` \| `OTRA` (la expulsión va por la 66, que exige el ingreso); `aula_id` un aula del examen (el auxiliar, la suya); `estudiante` opcional, texto libre (si son solo dígitos y coincide con un código del padrón se guarda también `estudiante_id`); `detalle` obligatorio, mín. 5 (`Mínimo 5 caracteres`). 201 `{"data":{…},"message":"Incidencia registrada."}`. Solo el día del examen.
**69.** 200 `{"message":"Incidencia atendida."}`; guarda quién y cuándo; no se deshace. 409 si ya estaba atendida.

### 3.9 Seguimiento en vivo — `routes/monitoreo.php`

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 70 | `GET /api/en-curso` | `seguimiento_vivo` | selector de `docente/EnCurso`; destino de «Mi aula» |
| 71 | `GET /api/examenes/{examen}/en-curso` | `seguimiento_vivo` + docente del examen | `docente/EnCurso` — **sondeo 10 s** |
| 72 | `GET /api/examenes/{examen}/aulas/{aula}/asistencia` | `seguimiento_vivo` + docente del examen o auxiliar del aula | `monitoreo/Asistencia` (`/en-curso/aula`, `/mi-aula`) — **sondeo 15 s** |

**70.** Docente: sus exámenes de hoy y, después, los del próximo día con examen. Auxiliar: las aulas que atiende en esos mismos días. `{"data":[{"examen_id":1,"texto":"12 oct · Primer parcial · Introducción a la Programación","asignatura":"…","fecha":"2026-10-12","hora":"08:15","abierto":true,"aulas":[{"aula_id":30,"nombre":"691A"}]}]}`. Para el auxiliar, `aulas` trae solo las suyas; vacío → la pantalla muestra «Sin aula asignada».
**71.**

```json
{"examen":{"id":1,"asignatura":"Introducción a la Programación","tipo":"Primer parcial","fecha":"2026-10-12","hora":"08:15"},
 "abierto":true,"hora_servidor":"2026-10-12T08:21:07-04:00",
 "cifras":{"ingresaron":108,"faltan":147,"aulas":5,"incidencias_sin_atender":2},
 "aulas":[{"aula_id":30,"nombre":"691A","edificio_id":7,
           "encargado":{"nombre":"Salazar Serrudo Carla","es_docente":true},"apoyo":["Rojas Ledezma Andrea"],
           "ingresados":38,"asignados":51,"incidencias_sin_atender":0,
           "compartida":"Cálculo I · Primer parcial","usa_codigo":false,"a_mi_cargo":false}]}
```

`abierto`: la hora del servidor está entre 60 min antes del inicio y el final. `compartida`: texto de los otros exámenes que se solapan en esa aula, o nulo. `usa_codigo`: el aula tiene un código de acceso generado. `a_mi_cargo`: soy el encargado.
**72.** `?lista=faltan|ingresaron&buscar=` (nombre, código) `&pagina&por_pagina=25`. `{"examen":{…},"aula":{"id":30,"nombre":"691A"},"abierto":true,"hora_servidor":"…","cifras":{"ingresaron":38,"faltan":13,"asignados":51},"data":[{"codigo":"202105877","nombre":"Aguilar Cossío, Mariana","grupo":"2","hora":"08:14:52"}],"meta":{"total":13,"pagina":1,"por_pagina":25}}`. `faltan`: habilitados con esa aula y sin ingreso, por apellidos; `ingresaron`: ingresos por esa aula, el más reciente primero (`hora` solo aquí). Un expulsado sigue en `ingresaron`, con `"expulsado":true`.

### 3.10 Reportes — `routes/reportes.php`

| # | Método y ruta | Permiso | Pantalla |
|---|---|---|---|
| 73 | `GET /api/reportes/examenes` | `reportes_universidad` | `reportes/Reportes` (tabla y cifras) |
| 74 | `GET /api/reportes/horarios/exportar` | `reportes_universidad` | «Horarios de ingreso» |
| 75 | `GET /api/reportes/registrados/exportar` | `reportes_universidad` | «Registrados por examen» |
| 76 | `GET /api/reportes/intentos/exportar` | `reportes_universidad` | «Intentos no correspondientes» |
| 77 | `GET /api/reportes/mis-examenes` | `reportes_examenes` | `reportes/ReportesDocente` (lista y cifras) |
| 78 | `GET /api/reportes/asistencia/exportar` | `reportes_examenes` + docente del examen | «Asistencia por examen» |
| 79 | `GET /api/reportes/ausentes/exportar` | ídem | «Estudiantes ausentes» |
| 80 | `GET /api/reportes/grupos/{grupo}/estudiantes` | `mis_grupos` + grupo propio | `docente/MisGrupos` («Reporte de mis estudiantes») |

**73.** `?periodo=&facultad=&buscar=` (asignatura, docente que lo registró) `&desde=&hasta=&examen=&pagina&por_pagina=10`; `hasta ≥ desde`. Orden por fecha y hora. `{"data":[{"id":1,"asignatura":"Introducción a la Programación","tipo":"Primer parcial","docente":"Blanco Coca Leticia","facultad":"FCyT","fecha":"2026-10-12","hora":"08:15","aulas":5,"ingresados":108,"habilitados":255}],"meta":{"total":212,"pagina":1,"por_pagina":10,"conteos":{"todas":212,"FCyT":90,"FCE":61,"FHCE":40,"FACH":21},"cifras":{"ingresos":8012,"examenes":212,"intentos":64}}}`. `cifras` se calcula sobre **todo el alcance** (los filtros, o solo `examen` si viene), no sobre la página; `conteos` ignora el filtro de facultad.
**74–76.** `?formato=pdf|xlsx` + los filtros de la 73 (o `examen=`). Archivo `horarios_ingreso_<alcance>_<hoy>.pdf` (`alcance`: `examen1`, sigla en minúsculas o `umss`). 422 con formato desconocido; 409 `{"codigo":"SIN_DATOS"}` si el alcance no tiene exámenes. Contenido (tablas de `ArmarTabla`, encabezado con período y filtros):
 - `horarios`: Tramo de 15 min · Ingresos; totales: primer y último ingreso, total.
 - `registrados`: Fecha · Examen · Facultad · Docente · Aula · Habilitados · Registrados (una fila por examen y aula).
 - `intentos`: Hora · Código · Estudiante · Aula · Examen que le correspondía · Motivo (`No inscrito en la materia` / `No habilitado` / `Código no válido`). Fuente: incidencias `SISTEMA` cuyo `examen_id` es del alcance, o con `examen_id` nulo y ocurridas en un aula y ventana de un examen del alcance.
**77.** `?periodo=&asignatura=&pagina&por_pagina=8`. `{"data":[{"id":1,"asignatura":{"id":90,"nombre":"…"},"tipo":"Primer parcial","fecha":"2026-10-12","hora":"08:15","rendido":true,"presentes":198,"habilitados":255,"no_habilitados":9,"ausentes":57,"asistencia":78}],"meta":{"total":4,"rendidos":1,…}}`. `rendido`: la fecha del examen ya llegó; si no, las cifras van nulas.
**78–79.** `?examen=1&formato=pdf|xlsx` → `asistencia_2026-10-12.pdf`. `asistencia`: Código · Estudiante · Grupo · Aula · Hora de ingreso · Medio · Situación (`Rindió` / `Expulsado` / `Excepción`). `ausentes`: Código · Estudiante · Grupo · Carrera · Aula asignada. 409 `{"codigo":"SIN_RENDIR"}` antes de la fecha.
**80.** `?formato=xlsx` → `estudiantes_<codigo_asignatura>_g<grupo>.xlsx`: Código · Estudiante · Documento · Correo institucional · Origen.

**Total: 80 rutas** (7 de acceso, 10 de administración, 16 académicas, 8 de estudiantes, 12 de exámenes, 3 de habilitación, 13 de ingreso, 3 de seguimiento, 8 de reportes).

---

## 4. Importación de datos

### 4.1 Archivos

Se copian **una vez** de `/Users/rodri/Documents/GENDA/genda-app/scraper/jsons/` a `database/umss/genda/` y se versionan (son datos públicos; no hay estudiantes):

| Destino | Origen | Tamaño |
|---|---|---|
| `database/umss/genda/fcyt.json`, `fce.json`, `fhce.json`, `fach.json` | los del mismo nombre | 0,2–0,7 MB |
| `database/umss/genda/pensum.json` | `pensum.json` | 1,3 MB |
| `database/umss/genda/locations.json` | `locations.json` | 56 KB |

No se copian `fcjp.json` ni `fodo.json`. El importador lee **solo** de `database/umss/genda/` (ruta en `config/umss.php`, clave `umss.ruta_genda`, para que las pruebas apunten a `tests/Fixtures/genda/`). Actualizar la oferta es volver a copiar los archivos e importar.

### 4.2 Estructura real de los JSON (inspeccionada)

**Oferta por facultad** (`<facultad>.json`): objeto cuyas claves son nombres de carrera, más la clave especial `INFO`.

```
{
  "INFO": { "SEMESTER": "Semestre 2/2026", "DATE": "8/3/2026" },
  "LICENCIATURA EN INGENIERIA DE SISTEMAS": {
    "_CAREER_CODE": "411702",
    "SEMESTRE 1": {
      "ALGEBRA I": {
        "_CODE": "2008019",
        "5": { "PROFESSOR": "TABORGA ACHA FIDEL", "AUX_INDEX": -1,
               "DAY": ["MI","VI","SA"], "HOUR": ["14:15-15:45","08:15-09:45","11:15-12:45"],
               "CLASS": ["617","623","692B"] }
      }
    }
  }
}
```

| Campo | Significado | Cuidados comprobados |
|---|---|---|
| `INFO.SEMESTER` | Período de la oferta | Dos formas: `Semestre 2/2026` (FCyT, FCE, FHCE) y `Gestión 2/2026` (FACH). Se extrae con `/(\d)\/(\d{4})/` |
| `INFO.DATE` | Fecha de la fuente, `M/D/AAAA` | Falta en FHCE y FACH |
| clave de carrera | Nombre de la carrera | — |
| `_CAREER_CODE` | Código de la carrera | **Falta en las cinco carreras de FCE**: se resuelve por nombre normalizado contra `pensum.json` (las cinco coinciden: `109401`, `089801`, `059801`, `125091`, `126091`). `111111` (Curso preparatorio de Música, FHCE) no está en el pensum: se crea igual |
| clave de nivel | `SEMESTRE 1`…`SEMESTRE 10`, `PRIMER AÑO`…`QUINTO AÑO`, `PREPARATORIO` | Un nivel con `AÑO` marca la carrera como `ANUAL` (cinco de las seis de FACH) |
| clave de asignatura | Nombre | — |
| `_CODE` | Código de la asignatura | Presente siempre. 1.339 códigos, ninguno con dos nombres. Uno (`1801033`) aparece en dos facultades |
| clave de grupo | Código del grupo: `3`, `07`, `A1`, `H2` | La misma asignatura se repite en varias carreras con los mismos grupos: 3.169 grupos únicos por (facultad, asignatura, grupo) de 4.115 apariciones. Las apariciones repetidas son idénticas (docente, días, horas y aulas); se toma la primera |
| `PROFESSOR` | Docente, en mayúsculas, «PATERNO MATERNO NOMBRES» | **Falta en 20 grupos de FHCE** (solo tienen filas de auxiliatura). Marcadores de «sin docente»: `POR DESIGNAR DOCENTE`, `POR DESGINAR DOCENTE` (errata real), cadena vacía y cualquier valor de menos de 5 letras (hay un `VI` de una fila mal leída) → `docente_id` nulo. 849 docentes reales; 29 en más de una facultad |
| `DAY`, `HOUR`, `CLASS` | Listas paralelas: una posición por sesión | Días válidos `LU MA MI JU VI SA`. Hay filas corruptas (día `1845-2015`, hora o aula vacías): **se descarta la sesión** cuyo día no sea válido o cuya hora no cumpla `HH:MM-HH:MM`. Aula vacía → sesión sin aula |
| `AUX_INDEX` | Qué posiciones son auxiliatura | `-1` = ninguna; un entero = esa posición; una lista = esas posiciones → `horarios.es_auxiliatura` |

Volumen: FCyT 20 carreras, 469 asignaturas, 1.100 grupos; FCE 5, 254, 1.007; FHCE 10, 452, 679; FACH 6, 165, 383. 282 nombres de aula distintos en la oferta.

**`locations.json`**: objeto con una clave por facultad (`fcyt`, `fce`, `fhce`, `fach`; se ignoran `fcjp` y `fodo`), cada una con una lista de edificios:

```
{ "id": "blk_0_50366", "nombre": "EDIFICIO DE LABORATORIOS BASICOS",
  "polygon": [[-66.14435, -17.39445], …],      // [longitud, latitud], anillo cerrado
  "aulas": ["680B", "680J", …],                 // aulas sin piso conocido
  "pisos": [ { "nombre": "1° Piso", "aulas": ["A740", "A741"] } ] }
```

Un edificio trae `aulas` **o** `pisos`, nunca los dos con contenido. 56 edificios y 237 aulas en las cuatro facultades; ningún aula está en dos edificios. Caja del campus: longitud −66,14864 a −66,14414, latitud −17,39548 a −17,39258. **49 aulas de la oferta no están ubicadas** (virtuales, laboratorios, auditorios de FCyT: `AULVIR`, `AUDCIV`, `AUDIND`, `AUDINF`, `CAMPO`, `E599`…): se crean con `edificio_id` nulo. `TP-7`, `SP-8` y `CAMPO` las usan dos facultades: el aula es una sola (único por nombre).

**`pensum.json`**: lista de 18 facultades `{ "name", "code", "careers": [ { "code", "name", "type": "Carrera"|"Programa", "metadata": {"gestion"}, "subjects": [ { "nivel": "A", "codigo", "materia", "tipo", … } ] } ] }`. Se usa **solo** para resolver el código de carrera cuando falta y para comprobar la facultad (`code` 20 = FCyT, 13 = FCE, 18 = FHCE, 17 = FACH). El plan de estudios se arma con los niveles de la oferta, no con las letras del pensum.

### 4.3 Casos de uso (módulo `Academico`)

| Acción | Qué hace |
|---|---|
| `Application/Actions/DetectarPeriodos` | Lee `INFO.SEMESTER` de los cuatro archivos y `database/umss/fuentes.json` (`calendarios_academicos[].periodo` y `fechas_leidas`). Crea o actualiza `periodos` por código; para una carrera anual deriva `0/AAAA`. Pone fechas y ventanas donde `fuentes.json` las trae (`2/2026`: 10 ago – 26 dic; `1/2026`: 23 feb – 4 jul); el resto queda sin fechas. Nunca pisa fechas con `ajustado_por` no nulo |
| `Application/Actions/ImportarUbicaciones` | Por facultad: *upsert* de `edificios` por `clave`, con polígono y centro (promedio de los vértices sin repetir el último); *upsert* de `aulas` por `nombre` con edificio y piso |
| `Application/Actions/ImportarOferta` | Por facultad, en una transacción: registra `importaciones_oferta` (`IMPORTANDO`); llama a `ImportarUbicaciones`; *upsert* de carreras (código), asignaturas (código), `plan_estudios`, docentes (`nombre_normalizado`), aulas faltantes (nombre) y grupos (único de 2.3); borra y recrea los `horarios` de cada grupo tocado; cierra con `IMPORTADA` y el resumen `{carreras, asignaturas, grupos, grupos_sin_docente, docentes, aulas, sesiones_descartadas}`. Si algo falla: `rollback` y fila `FALLO` con el mensaje (fuera de la transacción). Asienta `oferta.importar` |

Reglas del importador: **reimportar no duplica** (todo es *upsert* por clave natural); un grupo que ya no viene en el archivo **no se borra** si tiene inscripciones o exámenes (queda como está) y se borra si no los tiene; un docente que deja de aparecer no se borra; nunca toca `docentes.user_id`. Nombres: se guardan con mayúscula inicial por palabra (`mb_convert_case(…, MB_CASE_TITLE)`), conservando `De`, `Del`, `La` como vengan.

Contratos: `Application/Contracts/{FuenteOfertaGateway,OfertaGateway,UbicacionGateway,PeriodoGateway,ImportacionGateway}`; lectura de archivos en `Infrastructure/Import/{LectorOfertaGenda,LectorUbicacionesGenda,LectorPensumGenda,LectorFuentesUmss}.php` (devuelven DTO planos: la capa de aplicación no ve JSON).

### 4.4 Comandos (`routes/console.php`)

| Comando | Efecto |
|---|---|
| `php artisan periodos:detectar` | `DetectarPeriodos`; imprime los períodos y su estado |
| `php artisan oferta:importar {facultad?}` | `ImportarOferta` para una facultad (`fcyt`…) o para las cuatro; imprime el resumen por facultad; código de salida 1 si alguna falla |
| `php artisan respaldo:generar` | Existente; pasa a las 03:00 |

Lo mismo se puede hacer desde la pantalla Período (`POST /api/periodos/detectar`, `POST /api/oferta/importaciones`): la importación corre **dentro de la petición** (unos segundos por facultad; `set_time_limit(120)`), porque el servidor de destino no tiene procesos en segundo plano.

### 4.5 Seeders

| Seeder | Contenido |
|---|---|
| `RolePermissionSeeder` | Tres roles de sistema y los 14 permisos de 3.1 |
| `FacultadSeeder` | Las cuatro facultades con clave, sigla, nombre, código y color |
| `DatabaseSeeder` | Llama a los dos anteriores (lo mínimo para que el sistema arranque vacío) |
| `DemoSeeder` | Ver abajo. `composer datos:demo` = `migrate:fresh` + `db:seed --class=DemoSeeder` |
| `VolumenSeeder` | `DemoSeeder` con carga alta. `composer datos:volumen` |

`DemoSeeder`, en orden, y **fechado respecto de hoy** (no del 12 de octubre): (1) roles, permisos y facultades; (2) `DetectarPeriodos` y, si hoy no cae en ningún período, extiende `2/2026` para que lo contenga; fija `0/2026` en 2 feb – 19 dic; (3) `ImportarOferta` de las cuatro facultades (datos reales); (4) cuentas; (5) padrón ficticio de 4.218 estudiantes (FCyT 1.874, FCE 1.102, FHCE 768, FACH 474; nombres de una lista interna, códigos `2021xxxxx`–`2026xxxxx`), con inscripciones en los grupos de los dos docentes de ejemplo y tres conflictos pendientes; (6) exámenes.

| Cuenta | Usuario | Contraseña | Notas |
|---|---|---|---|
| Administrador | `administracion.academica` | `Admin12345` | — |
| Docente, carga normal | `leticia.blanco` | `Docente12345` | Blanco Coca Leticia: sus grupos reales de 2/2026 (2 asignaturas) |
| Docente, carga alta | `carga.alta` | `Docente12345` | El docente real con más asignaturas de FCyT en la oferta, acotado a **6 asignaturas** (se elige en el seeder por conteo; sus demás grupos quedan sin tocar) |
| Docente, primer ingreso | `corina.flores` | temporal `Fa7-Kmq4-Ru9` | Para ver el cambio obligatorio |
| Auxiliar | `diego.mamani` | `Auxiliar12345` | A cargo de un aula del examen de hoy |
| Auxiliares de apoyo | `andrea.rojas`, `lucia.peredo`, `sergio.vargas` | `Auxiliar12345` | — |
| Bloqueada | `victor.montano` | — | `is_active = false` |

Todas con `password_changed_at` puesto salvo la de primer ingreso. Más un 25 % de los docentes con cuenta activa (contraseña aleatoria) para que los filtros de Docentes tengan qué mostrar.

Exámenes de `leticia.blanco`: (a) **primer parcial de Introducción a la Programación, hoy, con inicio 6 minutos antes de la hora de siembra, 90 min**, grupos 1, 2, 3 y 6, cinco aulas (`691B` a cargo de la docente con apoyo, `691A` a cargo de otra docente y compartida con un parcial de Cálculo I de otro docente a la misma hora, `691E` y `617` a cargo de auxiliares, `624` con código de acceso), habilitación completa con 9 no habilitados y su motivo, reparto hecho, QR emitidos, ~40 % de ingresos escalonados en los últimos 20 minutos, una expulsión, tres incidencias de personas (una atendida) y cuatro intentos del sistema; (b) parcial de Taller de Ingeniería de Software a dos días, registrado por otra docente, a medio habilitar; (c) segundo parcial de Introducción sin aulas; (d) segundo parcial de Taller con aula y sin habilitar. `carga.alta` recibe 18 exámenes: los primeros parciales de sus seis asignaturas (uno hoy en 12 aulas; tres mañana a la misma hora en la misma aula) y los segundos parciales y finales sin aulas. `VolumenSeeder` agrega 150 exámenes de otros docentes repartidos en la ventana de parciales, para los reportes del administrador.

El README (paquete I1) documenta la tabla de cuentas.

---

## 5. Frontend

### 5.1 Cómo se porta la maqueta

La maqueta (`../Maquetacion/src`) y el proyecto comparten React 19, react-router 7, Tailwind 4 y lucide. Portar una pantalla es: copiar el archivo a `resources/js`, **cambiar los datos importados de `datos/*.js` por llamadas a la API**, quitar el andamiaje, declarar `PropTypes` (la maqueta no tiene ninguno y `react/prop-types` es error) y pasar Prettier (`npm run format`; la maqueta tiene líneas de más de 100 columnas). El marcado, las clases y los textos **se conservan literalmente**: son el diseño aprobado. No se agregan textos explicativos.

Estructura objetivo de `resources/js`:

```
app.jsx                      punto de entrada (existente)
api/cliente.js               instancia de axios e interceptores
api/usarConsulta.js          hook de lectura: { datos, meta, cargando, error, recargar }
api/usarSondeo.js            usarConsulta + intervalo, con pausa si la pestaña está oculta
api/usarPaginaServidor.js    página, por_pagina y filtros → parámetros; vuelve a la página 1 al filtrar
api/descargar.js             GET con responseType 'blob' y descarga con el nombre del servidor
sesion/SesionContexto.jsx    <ProveedorSesion>, usarSesion(): { usuario, entrar, salir, puede(permiso) }
sesion/RutaProtegida.jsx     exige sesión, contraseña propia y permiso
navegacion/vistas.js         menú por rol (de ../Maquetacion/src/datos/actores.js, sin `usuarioDemo`, `resumen`, `modulo`, `nota`)
navegacion/Aplicacion.jsx    BrowserRouter y tabla de rutas
componentes/                 compartidos (5.2)
paginas/auth|admin|estudiantes|docente|examenes|habilitacion|ingreso|monitoreo|reportes/
utiles/texto.js              normalizar, plural, nombreCompleto, fechaCorta, fechaLarga, mesDe, colorDeAsignatura
test/setup.js, test/apoyo.jsx   (existente) + renderConSesion(), respuestas simuladas
```

Desaparecen `componentes/{Aplicacion,LayoutAdmin,PestanasPadron}.jsx` y `componentes/sesion.js` (los sustituyen `navegacion/` y `sesion/`), y `bootstrap.js` (lo sustituye `api/cliente.js`).

**Se quita por ser andamiaje**: el tablero `/` (`paginas/Inicio.jsx`), `componentes/{MarcoVista,SelectorDispositivo}.jsx`, `contexto/DispositivoContexto.jsx`, `datos/{mock,flujo,umss,volumen}.js`, `componentes/{habilitacionDocente,registroIncidencias}.js` (almacenes en memoria), el parámetro `?previsualizar=1` y la función `con()` que lo propaga, `?carga=alta`, el bloque «Simular estado · maqueta» del inicio de sesión, el bloque «Maqueta · simular» y la lista `LECTURAS` del punto de control, todos los `setTimeout` que fingen espera, `QrFalso`, `codigoDeAcceso` calculado, la constante `COMPARTIDAS`, `resumenDe` de Mis grupos, las constantes `HOY`, `HORA_ACTUAL`, `DOCENTE_DEMO`, y `../Maquetacion/public/_redirects`. La facultad fija `'FCyT'` de mapas y credenciales pasa a ser la del examen o del aula.

### 5.2 Componentes compartidos (los crea F0-D; nadie más los edita)

| Componente | Origen | Cambio al portar |
|---|---|---|
| `Aviso`, `Boton`, `Buscador`, `Campo`, `Chips`, `Dato`, `Dialogo`, `Insignia`, `Logo`, `Pasos`, `Seleccion` (+`AreaTexto`), `Tarjeta` | `../Maquetacion/src/componentes/` | Solo `PropTypes` y formato |
| `Encabezado` | ídem | Sin la lógica de `previsualizar` |
| `Paginacion` | ídem | El componente, tal cual. `usePaginas` (paginación en memoria) se conserva solo para los catálogos que la API devuelve enteros; las listas grandes usan `api/usarPaginaServidor.js` |
| `FiltroFacultad`, `PuntoFacultad`, `colorDeFacultad` | ídem | Las facultades salen de `GET /api/facultades` (se cargan una vez en `SesionContexto` y se exponen con `usarFacultades()`) |
| `MapaCampus` | ídem | Recibe `edificios` por *prop* (de `GET /api/edificios`, que un hook `usarEdificios()` guarda en memoria para toda la sesión) y proyecta longitud y latitud al lienzo de 1000 × 644 con la caja de `meta.caja` (proyección lineal; a esta escala no hace falta otra). `edificioDeAula` y `pisoDeAula` pasan a buscar en esos datos. Sin mapa base (D-10) |
| `SelectorExamen` | ídem | `examenes` deja de tener valor por defecto: siempre por *prop* |
| `usarExamen` | `useExamen.js` | Lee y escribe `?examen=` igual; la lista viene de `GET /api/examenes` |
| `Incidencias` (`BotonIncidencia`, `ListaIncidencias`) | ídem | `registrar` y `atender` llegan por *prop* (llamadas 68 y 69); `TIPOS_INCIDENCIA` se conserva como mapa clave → texto, sin `Expulsión` en el formulario |
| `EsquemaApp` | ídem | Sin `actorId`: toma el usuario, el rol y las vistas de `usarSesion()`. «Cerrar sesión» llama a `salir()`. La regla de la barra inferior y de «Más» se conserva |
| `EstadoCarga` | **nuevo** | Tres estados comunes (5.4) |
| `LectorQr` | **nuevo** | Envoltorio de `qr-scanner` (5.6) |

`resources/css/app.css` conserva sus cuatro `@source` y recibe de `../Maquetacion/src/index.css` el bloque `@theme` completo (escalas `primary`, `success`, `danger`, `warning`, `info`, `slate` y `--font-sans` con Inter), las reglas de `html` y `body` y el `@layer base` de `select` e `input[type=date|time]`. Inter se sirve **local** (`resources/fonts/`, `@font-face`), no desde Google Fonts: la aplicación tiene que funcionar instalada y con mala conexión. `public/favicon.svg` se copia de `../Maquetacion/public/`.

### 5.3 Cliente HTTP, sesión y rutas

**`api/cliente.js`**: `axios.create({ baseURL: '/api', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, withXSRFToken: true })`. Un interceptor de respuesta emite eventos que escucha `SesionContexto`: 401 o 419 fuera de `/auth/` → borra la sesión y va a `/login`; 409 con `debe_cambiar_contrasena` → va a `/primer-ingreso`. Las páginas importan `api` de aquí; **no se usa `window.axios`**. En las pruebas se simula el módulo con `vi.mock('../../api/cliente')` (un ayudante de `test/apoyo.jsx` devuelve respuestas por ruta).

**Sesión**: al montar, `ProveedorSesion` llama a `GET /api/auth/sesion`; mientras responde muestra la pantalla de carga; 401 → sin sesión. No se guarda nada en `sessionStorage`: la verdad es la cookie. `puede('examenes')` mira `usuario.permisos`.

**Rutas** (las mismas de la maqueta; la entrada depende del rol):

| Rol | Entrada | Rutas |
|---|---|---|
| — | | `/login`, `/primer-ingreso`, `/restablecer-contrasena`, `/acceso-aula` (puerta con código) |
| Administrador | `/periodo` | `/periodo`, `/oferta`, `/aulas`, `/docentes`, `/estudiantes`, `/reportes`, `/admin`, `/bitacora`, `/respaldo` |
| Docente | `/examenes` | `/examenes`, `/examenes/nuevo`, `/habilitacion`, `/codigos-qr`, `/en-curso`, `/en-curso/aula`, `/control`, `/mis-grupos`, `/mis-reportes` |
| Auxiliar | `/ingreso` | `/ingreso`, `/mi-aula` |

`/` redirige a la entrada del rol (o a `/login`). Una ruta de otro rol redirige a la entrada propia. El examen elegido viaja en `?examen=`, el aula en `?aula=` (ahora con el **id**), el paso del asistente en `?paso=`. `navegacion/Aplicacion.jsx` carga cada página con `React.lazy`; F0-D deja en cada ruta un archivo de página **provisional** (título y «En construcción») que el paquete dueño reemplaza: así la tabla de rutas no se vuelve a tocar.

### 5.4 Carga, vacío y error

La maqueta no los dibuja (todo es síncrono); se resuelven igual en todas las pantallas con `componentes/EstadoCarga.jsx`:

- **Cargando** (primera carga): esqueleto gris con `aria-busy="true"` del alto de la lista; nunca un texto «Cargando…» a pantalla completa. En recargas y sondeo **no** se muestra nada: los datos anteriores quedan a la vista.
- **Vacío**: el texto que ya trae la maqueta (`Sin resultados`, `Sin exámenes`, `Sin conflictos`, `Sin aulas`, `Sin lista cargada`, `Sin incidencias`…).
- **Error de lectura**: en el lugar de la lista, `No se pudo cargar` + botón «Reintentar». En el sondeo, tras tres fallos seguidos, una insignia `Sin conexión` en el encabezado; se quita al primer acierto.
- **Error de escritura**: validación 422 → el mensaje del campo bajo su `Campo` (prop `error`); cualquier otro → `avisar(mensaje, 'error')` con el `message` del servidor. Los botones de enviar se deshabilitan mientras dura la petición.
- **Éxito**: `avisar()` con el texto exacto de la maqueta.

### 5.5 Pantalla → rutas de la API

| Pantalla (`paginas/…`) | Lee | Escribe |
|---|---|---|
| `auth/Login` | — | 1, 5; enlace a `/acceso-aula` |
| `auth/PrimerIngreso` | 3 | 4 |
| `auth/RestablecerContrasena` | — | 6 |
| `auth/AccesoAula` | — | 7 |
| `admin/Periodo` | 19, 23 | 21, 22, 24 |
| `admin/Oferta` | 18, 20, 25, 26, 27 | — |
| `admin/Aulas` | 18, 28, 29 | — |
| `admin/Docentes` | 18, 30, 31 | 32 |
| `estudiantes/Padron` | 18, 25, 34, 35, 37 | 36, 38; 39 |
| `admin/Usuarios` | 8, 12 | 9, 10, 11 |
| `admin/Bitacora` | 13 | — |
| `admin/Respaldo` | 14 | 15, 17; 16 |
| `reportes/Reportes` | 18, 73 | 74, 75, 76 (descargas) |
| `examenes/Examenes` | 20, 42 | — |
| `examenes/RegistrarExamen` | 29, 33, 43, 44, 48, 49, 50, 52; 28 (mapa) | 45, 46, 51, 53 |
| `habilitacion/Habilitacion` | 42, 54 | 55, 56 |
| `docente/CodigosQr` | 42, 57, 59; 28 | 58, 60; 61 (pestaña nueva) |
| `docente/EnCurso` | 70, 71 (sondeo 10 s), 67; 28 | 68, 69 |
| `monitoreo/Asistencia` | 70, 72 (sondeo 15 s), 67 | 68, 69 |
| `ingreso/PuntoControl` | 62, 63 (sondeo 10 s); 28 | 64, 65, 66, 68 |
| `docente/MisGrupos` | 20, 33, 40 | 41; 39, 80 |
| `reportes/ReportesDocente` | 42, 77 | 78, 79 |

Notas de comportamiento que la maqueta simula y ahora son reales:

- **Listas grandes** (`Usuarios`, `Bitacora`, `Docentes`, `Padron`, `Oferta`, `Habilitacion`, `CodigosQr`, `Asistencia`, `Reportes`, inscritos de `MisGrupos`): paginación, búsqueda y filtros van al servidor; la búsqueda espera 300 ms tras la última tecla. Los conteos de fichas salen de `meta.conteos`.
- **`Habilitacion`**: la selección es un conjunto de `estudiante_id` que sobrevive al cambio de página; «Seleccionar los N» no descarga los N: envía `todos: true` con los filtros vigentes (ruta 55).
- **`RegistrarExamen`**: un examen nuevo arranca **en blanco** (asignatura: la primera del docente; grupos: los suyos de esa asignatura), no precargado con el primer examen. El aviso de aula compartida se recalcula (ruta 49) al entrar al paso 3 y al cambiar fecha, hora o duración. El botón de la llave («Código de acceso») solo se habilita con el examen ya guardado. Se agrega al paso 1 un `AreaTexto` opcional «Normas» (D-08).
- **`Periodo`**: «Importar» espera la respuesta de la ruta 24 con el botón en «Importando» (puede tardar varios segundos). Cada período con estado `Sin fechas` muestra un botón «Ajustar» que abre un diálogo con las dos fechas (D-11).
- **`Docentes` y `Usuarios`**: la contraseña temporal que devuelve el servidor se muestra una vez, con «Copiar» (`navigator.clipboard.writeText`), como en el detalle de Docentes de la maqueta; en `Usuarios` se muestra en el mismo diálogo tras crear o restablecer.
- **`EnCurso`**, **`Asistencia`**, **`PuntoControl`**: «abierto» lo decide el servidor (`abierto`), no la fecha del navegador.
- **Descargas** (reportes, plantilla, respaldo): `api/descargar.js`; el aviso «… descargado» sale cuando el archivo llegó.

### 5.6 Punto de control y lectura de QR

Librería: **`qr-scanner` 1.4.2**, que ya está en `package.json` e instalada y que la pantalla actual ya usa. No se agrega otra.

`componentes/LectorQr.jsx` (`{ activo, onLectura, onError }`): import dinámico (`await import('qr-scanner')`) para no cargarla en quien no controla; `new QrScanner(video, (r) => onLectura(r.data), { preferredCamera: 'environment', highlightScanRegion: true, maxScansPerSecond: 4 })`; `start()` al activarse; **`stop()` y `destroy()` al desactivarse y al desmontar** (falta en la pantalla actual); ignora una lectura repetida del mismo texto durante 3 s. Si `QrScanner.hasCamera()` es falso o `start()` falla (sin permiso, sin HTTPS), `onError` y la pantalla muestra `Cámara no disponible` con el ingreso manual abierto.

Flujo de `PuntoControl` (estados de la maqueta: `espera` → `verificando` → veredicto → `espera`): el botón cuadrado «Código QR del estudiante» enciende el lector en ese mismo recuadro; una lectura llama a la ruta 64 con `token`, se apaga la cámara y el veredicto ocupa el lugar del visor; «Siguiente» vuelve a `espera` **con la cámara encendida** si venía de QR. «Código o C.I. manual» es el respaldo siempre disponible (ruta 64 con `identificador`). Veredictos: los cinco de la maqueta (`admitido`, `no_habilitado`, `otra_aula` = «Otra aula», `ya_ingreso` = «Ya ingresó», `no_corresponde` = «No es de esta aula») más `expulsado` (tono peligro, título «Expulsado», solo «Siguiente»). El aula se toma de `?aula=`; sin parámetro, la primera abierta de la ruta 62; el diálogo «Aula» lista las de la 62. Con el aula cerrada (`abierta: false`) el visor se sustituye por `Sin examen a esta hora`.

`/acceso-aula` (quien entra con código): formulario «Código» (6 dígitos) y «Nombre»; al entrar muestra `PuntoControl` de esa aula **sin** `EsquemaApp` (no tiene cuenta ni menú), con un botón «Salir».

### 5.7 Aplicación instalable (PWA)

Paquete: **`vite-plugin-pwa` 2.0.0** (*devDependency*). Comprobado en el registro de npm: admite `vite ^7` (el proyecto tiene 7.3.6) y exige Node ≥ 20.19.0 (el de `.nvmrc`); sus pares `workbox-build` y `workbox-window` ^7.4.1 se instalan con él.

`vite.config.js`:

```js
VitePWA({
    registerType: 'autoUpdate',
    outDir: 'public/build',
    buildBase: '/build/',
    scope: '/',
    injectRegister: false,
    manifest: {
        name: 'Control de ingreso a exámenes', short_name: 'Ingreso UMSS', lang: 'es',
        start_url: '/', scope: '/', display: 'standalone',
        theme_color: '#2563eb', background_color: '#f1f5f9',
        icons: [/* 192, 512 y 512 maskable, en public/iconos/ */],
    },
    workbox: {
        globPatterns: ['**/*.{js,css,woff2,svg,png}'],
        navigateFallback: null,
        runtimeCaching: [{ urlPattern: ({ url }) => url.pathname.startsWith('/api/'), handler: 'NetworkOnly' }],
    },
})
```

- El *service worker* **solo precachea los estáticos compilados**. Las navegaciones y toda `/api/` van siempre a la red: los datos de un examen no se sirven de caché y no hay modo sin conexión (D-13).
- Vite deja `sw.js` y `manifest.webmanifest` en `public/build/`. Como un *service worker* solo controla su carpeta, `routes/web.php` los sirve desde la raíz: `GET /sw.js` (`response()->file(public_path('build/sw.js'), ['Content-Type' => 'application/javascript', 'Service-Worker-Allowed' => '/', 'Cache-Control' => 'no-cache'])`) y `GET /manifest.webmanifest`. La ruta de reserva de la SPA ya excluye solo `api`: estas dos rutas se declaran **antes** que ella.
- `resources/views/app.blade.php`: `<link rel="manifest" href="/manifest.webmanifest">`, `<meta name="theme-color" content="#2563eb">`, `<link rel="icon" href="/favicon.svg">`, `<link rel="apple-touch-icon" href="/iconos/icono-192.png">`.
- `resources/js/app.jsx` registra `/sw.js` con `navigator.serviceWorker.register('/sw.js', { scope: '/' })` solo en producción (`import.meta.env.PROD`).
- Íconos: PNG de 192 y 512 px generados a partir de `../Maquetacion/public/favicon.svg` (el dibujo de `Logo.jsx`), en `public/iconos/`.
- La cámara exige HTTPS (o `localhost`). El dominio de destino debe servir por HTTPS.

### 5.8 Correo

- Por defecto `MAIL_MAILER=log` (`config/mail.php` ya lo trae): en desarrollo los correos quedan en `storage/logs/laravel.log`. En pruebas, `array` (`phpunit.xml`), con `Mail::fake()`.
- **Códigos QR**: `Ingreso/Infrastructure/Mail/CodigoQrMail.php`, `Mailable` que **implementa `ShouldQueue`** (`use Queueable, SerializesModels`; `$tries = 3`), vista `resources/views/correos/codigo_qr.blade.php` con el examen, fecha, hora, aula, edificio y piso, y el QR como PNG **adjunto e incrustado** (`$message->embedData(...)`), generado con `GeneradorImagenQr`. Destinatario `<codigo_universitario>@est.umss.edu`. `EnviarCodigosQr` hace `Mail::to(...)->queue(...)` por estudiante y registra `envios_correo` (`PENDIENTE`); el propio `Mailable` marca `ENVIADO` y `codigos_qr.enviado_en` al salir, o `FALLIDO` con el error en `failed()`.
- **Cola**: `QUEUE_CONNECTION=database` en desarrollo (`composer dev` ya levanta `queue:listen`) y `sync` en pruebas. En el servidor de destino, sin procesos permanentes, el planificador que ya exige el respaldo (`* * * * * php artisan schedule:run`) ejecuta además `Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping()`. Sin tarea `cron`, se pone `QUEUE_CONNECTION=sync` y el envío ocurre dentro de la petición.
- **Credenciales y restablecimiento** (`Administracion/Infrastructure/Mail/*`): siguen siendo envíos inmediatos; solo salen si la cuenta tiene correo. El de credenciales muestra **usuario** y contraseña temporal.
- `deptrac.php` debe admitir `Illuminate\Contracts\Queue\ShouldQueue` en `Infrastructure` (lo agrega F0-A).

---

## 6. Paquetes de trabajo

### 6.1 Reglas para trabajar en paralelo

1. **Cada archivo tiene un solo dueño.** Un paquete crea, modifica y elimina solo lo que figura en su lista. Si necesita un cambio en un archivo ajeno, lo pide al dueño (los archivos compartidos son todos de la fase 0).
2. **Archivos que ningún paquete paralelo toca**: `database/migrations/*`, `database/seeders/*`, `app/Modules/*/Domain/Models/*`, `app/Modules/*/Domain/Enums/*`, `deptrac.php`, `deptrac.modules.php`, `docs/architecture/*`, `bootstrap/*`, `routes/web.php`, `routes/console.php`, `composer.json`, `package.json`, `vite.config.js`, `resources/css/app.css`, `resources/views/app.blade.php`, `resources/js/{app.jsx,api/*,sesion/*,navegacion/*,componentes/*,utiles/*,test/*}`, `tests/Support/*`, `tests/Architecture/*`. Si un paquete descubre que falta una columna, un valor de enumeración, una clase del marco en `deptrac.php` o un componente compartido, **lo anota para el integrador** y sigue; no lo arregla por su cuenta.
3. Cada paquete de backend es dueño de: su carpeta de módulo (salvo `Domain/Models` y `Domain/Enums`), su archivo de rutas, su *provider* de módulo, su carpeta de pruebas `tests/Feature/Modules/<Módulo>/` y sus vistas Blade. Cada paquete de frontend es dueño de sus archivos de página y de sus `.test.jsx`.
4. Los paquetes de backend no se necesitan entre sí: lo que uno lee de otro lo lee por contratos que deja listos la fase 0 o por `DB::table` sobre tablas que ya existen. Los de frontend se desarrollan contra la sección 3 con el cliente simulado y se prueban de punta a punta cuando su backend está listo.
5. Nadie hace `migrate:fresh` sobre la base de pruebas a mano: `RefreshDatabase` se encarga. Para probar a mano: `composer datos:demo`.
6. Mensajes de confirmación según `docs/convenciones.md`: Conventional Commits en español, sin líneas de coautoría.

Apoyos de prueba que deja la fase 0 en `tests/Support/` y que todos usan: `UsuarioConPermisos` (existente; ahora también pone `usuario`), `DatosAcademicos` (`periodoVigente()`, `facultad()`, `asignatura()`, `docenteConCuenta()`, `grupo(docente, asignatura)`, `aula()`), `DatosDeExamen` (`estudiantesInscritos(grupo, n)`, `examen(docente, grupos, aulas)`, `habilitar(examen, estudiantes, aula)`, `auxiliar()`, `ingresar(examen, estudiante, aula)`), y `tests/Fixtures/genda/` (una facultad mínima: 2 carreras, 3 asignaturas, 5 grupos, con un grupo sin docente, uno repetido en dos carreras y una sesión corrupta; `locations.json` con 2 edificios; `pensum.json` con 2 carreras).

### 6.2 Orden

```
F0-A ─► F0-B ─► F0-C ─► (control de calidad verde) ─┬─► B1 … B8   (en paralelo)
              └► F0-D (en paralelo con F0-C) ───────┴─► W1 … W4   (en paralelo, y a la vez que B*)
                                                              └─► I1 (al final)
```

La fase 0 es **secuencial** (F0-D puede correr junto a F0-C: no comparten archivos). Los doce paquetes B y W corren **todos a la vez**. I1 cierra.

### 6.3 Fase 0 — cimientos

#### F0-A · Esquema, modelos, estructura y limpieza

- **Objetivo**: dejar el esqueleto de los ocho módulos sobre el esquema nuevo, sin el código viejo, con los controles de arquitectura en verde.
- **Crea**: las migraciones de 2.1; `app/Modules/Academico/Domain/Models/{Periodo,Facultad,Carrera,Asignatura,PlanEstudio,Docente,Edificio,Aula,Grupo,Horario,ImportacionOferta}.php`; `Estudiantes/Domain/Models/{Estudiante,Inscripcion,CargaInscritos,ConflictoPadron}.php`; `Examenes/Domain/Models/{Examen,ExamenGrupo,ExamenAula,ExamenAulaApoyo,Auxiliar,AccesoAula}.php`; `Habilitacion/Domain/Models/Habilitacion.php`; `Ingreso/Domain/Models/{CodigoQr,Ingreso,Incidencia}.php`; `Administracion/Domain/Models/{Respaldo,EnvioCorreo}.php`; las enumeraciones: `Academico/Domain/Enums/{TipoPeriodo,EstadoPeriodo,RegimenCarrera,EstadoImportacion}`, `Estudiantes/Domain/Enums/{OrigenEstudiante,TipoConflicto,ResolucionConflicto}`, `Examenes/Domain/Enums/TipoExamen` (4 valores), `Ingreso/Domain/Enums/{MedioIdentificacion,TipoIncidencia,OrigenIncidencia,CausaIntento,Veredicto,TipoExcepcion}`, `Administracion/Domain/Enums/{TipoRespaldo,EstadoRespaldo,TipoCorreo,EstadoEnvio}` (todas `enum … : string` con `etiqueta()` y `valores()`); un `Infrastructure/Providers/<Módulo>ServiceProvider.php` por módulo; los archivos de rutas vacíos `routes/{usuarios,academico,estudiantes,habilitacion}.php`; `Ingreso/Http/Middleware/ExigirPuesto.php` en versión mínima (solo comprueba el permiso `punto_control`; B6 lo completa y pasa a ser su dueño); `Examenes/Application/Contracts/AlcanceExamenGateway.php` (`esDocenteDelExamen(int $usuarioId, int $examenId): bool`, `loRegistro(...)`, `atiendeAula(int $usuarioId, int $examenId, int $aulaId): bool`, `aulasQueAtiende(int $usuarioId, string $fecha): array`) con `Infrastructure/Persistence/EloquentAlcanceExamenGateway.php`; `Academico/Application/Contracts/AlcanceDocenteGateway.php` (`docenteDeUsuario(int $usuarioId): ?int`, `esGrupoDelDocente(int $usuarioId, int $grupoId): bool`) con su implementación; `Academico/Application/Contracts/PeriodoGateway.php` (`vigentes(): array`, `principal(): ?PeriodoData`) con su implementación; `docs/architecture/adr/000X-modulo-academico.md`; `tests/Support/{DatosAcademicos,DatosDeExamen}.php`; `tests/Integration/PostgreSQL/EsquemaReplanteoTest.php` (únicos, índices parciales, CHECK y el disparador de `ingresos`).
- **Modifica**: `docs/architecture/architecture-contract.json` (agrega `Academico`), `deptrac.modules.php` (capa `Academico` y la matriz de 1.2), `deptrac.php` (admite `Illuminate\Support\ServiceProvider` e `Illuminate\Contracts\Queue\ShouldQueue` en `Infrastructure` e `Illuminate\Validation\Rules\Password` en `Http`, si no están), `docs/architecture/{dependency-rules,module-dependencies}.md`, `bootstrap/providers.php`, `app/Providers/AppServiceProvider.php` (queda sin enlaces), `routes/web.php` (nuevos `require`; rutas `/sw.js` y `/manifest.webmanifest` de 5.7), `bootstrap/app.php` (alias `puesto`), `Administracion/Domain/Models/User.php` (`usuario` en `fillable`), `database/factories/UserFactory.php`, `tests/Architecture/ModulePublicApiGuardTest.php` (solo si las sondas chocan con archivos reales de `Habilitacion`: se renombran las sondas).
- **Elimina**: las 15 migraciones de 2.1; todo lo marcado «se elimina» en 1.3–1.6; **y también el interior** (`Application`, `Infrastructure`, `Http`, pruebas y rutas) de las piezas marcadas «se adapta» en `Examenes`, `Ingreso`, `Monitoreo` y `Reportes`, que no compilan contra los modelos nuevos: sus dueños las reescriben desde `git show pre-replanteo:<ruta>`. De `Administracion` se elimina lo de 1.3 (padrón, ambientes, edición de roles) y además `Http/Controllers/CuentaUsuarioController.php` con sus tres `Requests` y sus rutas, que B1 rehace como `UsuarioController`. Regla general: **al cerrar la fase 0 la suite está en verde con lo que quede**; toda prueba de `Administracion` que deje de pasar por un cambio de F0 y no figure en la lista de F0-B se retira y pasa a B1, que la rehace desde `pre-replanteo`. `database/factories/{Ambiente,Student}Factory.php`, `database/seeders/{DemoGuia,DatosMasivos}Seeder.php`, `routes/{ambientes,asignaturas,students,roles,examenes,ingreso,monitoreo,reportes}.php` (los cuatro últimos se recrean vacíos), `tests/Feature/Modules/{Examenes,Ingreso,Monitoreo,Reportes}/*`, las pruebas de `Administracion` de 6.6 marcadas «se elimina», `tests/Integration/PostgreSQL/Asignatura*Test.php`.
- **Depende de**: nada.
- **Pruebas**: `EsquemaReplanteoTest` (≈ 12 casos); adapta `PostgreSqlMigrationTest` y `BitacoraAtomicityTest` (esta última usa el alta de asignatura como vehículo: pasa a usar `usuario.crear`).
- **Terminado cuando**: `php artisan migrate:fresh` crea las 40 tablas; `composer quality:structure && composer quality:layers && composer quality:modules && composer quality:static && composer quality:style && composer quality:autoload` en verde; `composer test:postgresql` en verde.

#### F0-B · Acceso por usuario, roles y cuentas

- **Objetivo**: entrar con usuario y contraseña, contraseña temporal con cambio obligatorio y vencimiento, tres roles con sus permisos, y el contrato de cuentas que usarán otros módulos.
- **Archivos** (todos en `app/Modules/Administracion`): modifica `Application/Actions/{AuthenticateUser,CambiarContrasena,SolicitarRestablecimiento,RestablecerContrasena,CrearCuentaUsuario}`, `Application/Contracts/{AuthenticationSecurityGateway,CredencialesGateway,CuentaUsuarioGateway,RestablecimientoGateway}`, `Application/DTOs/NuevaCuentaData` (`nombre`, `usuario`, `?correo`, `rol`), sus cuatro implementaciones en `Infrastructure/Persistence`, `Infrastructure/Mail/*`, `Http/Controllers/{AuthenticationController,ContrasenaController}`, `Http/Requests/{LoginRequest,CambiarContrasenaRequest,SolicitarRestablecimientoRequest,RestablecerContrasenaRequest}`, `Http/Middleware/ExigirContrasenaPropia`; crea `Application/DTOs/{CuentaCreadaData,SesionData}`, `Application/Actions/{ProponerUsuario,ConsultarSesion}`, `Application/Contracts/GeneradorContrasenaTemporal` + `Infrastructure/Persistence/GeneradorContrasenaTemporalAleatorio` (formato `Xx9-Xxx9-Xx9`, alfabeto sin `i`, `l`, `o`, dígitos 2–9), `Infrastructure/Providers/AdministracionServiceProvider.php` (todos los enlaces del módulo). Modifica `routes/auth.php`, `resources/views/correos/*.blade.php`, `database/seeders/RolePermissionSeeder.php`, `tests/Support/UsuarioConPermisos.php`.
- **Rutas**: 1–6.
- **Depende de**: F0-A.
- **Pruebas** (`tests/Feature/Modules/Administracion/`): adapta `AuthenticationControllerTest`, `AuthenticationHardeningTest`, `LoginSecurityTest`, `BitacoraSesionTest`, `CambioContrasenaTest`, `RestablecimientoContrasenaTest`, `PermisosVigentesTest`, `UserModelConfigurationTest`; reescribe `RolesHeredadosTest` como `RolesDelSistemaTest` (tres roles, 14 permisos, reparto de 3.1); crea `SesionTest` (ruta 3) y `ContrasenaTemporalTest` (formato, vencimiento a 72 h, sin pedir la actual). Adapta `tests/Integration/PostgreSQL/{AuthenticationSchemaTest,AuthenticationSecurityLockingTest,SessionInactivityTest}`.
- **Terminado cuando**: `php artisan test --filter='Authentication|Login|Sesion|Contrasena|Permisos|Roles'` en verde y `composer quality:backend` en verde.

#### F0-C · Importadores y datos

- **Objetivo**: la base se llena sola con la oferta real y con los datos de demostración.
- **Archivos**: copia los seis JSON a `database/umss/genda/`; crea `config/umss.php`; en `app/Modules/Academico`: `Application/Actions/{DetectarPeriodos,ImportarOferta,ImportarUbicaciones}`, `Application/Contracts/{FuenteOfertaGateway,FuenteUbicacionesGateway,FuentePensumGateway,FuenteCalendarioGateway,OfertaGateway,UbicacionGateway,ImportacionGateway}`, `Application/DTOs/{OfertaFacultadData,CarreraOfertaData,AsignaturaOfertaData,GrupoOfertaData,SesionOfertaData,EdificioData,ResumenImportacionData,PeriodoData}`, `Domain/Exceptions/FuenteNoDisponibleException`, `Infrastructure/Import/{LectorOfertaGenda,LectorUbicacionesGenda,LectorPensumGenda,LectorFuentesUmss}`, `Infrastructure/Persistence/{EloquentOfertaGateway,EloquentUbicacionGateway,EloquentImportacionGateway}`, enlaces en `AcademicoServiceProvider`; `routes/console.php` (comandos de 4.4, tarea del respaldo a las 03:00, tarea de la cola); `database/seeders/{DatabaseSeeder,FacultadSeeder,DemoSeeder,VolumenSeeder}.php`; `composer.json` (guiones `datos:demo` y `datos:volumen`); `tests/Fixtures/genda/*`; `docs/ejemplos/inscri*.csv`.
- **Depende de**: F0-A y F0-B (cuentas del seeder).
- **Pruebas**: `tests/Feature/Modules/Academico/{ImportarOfertaTest,ImportarUbicacionesTest,DetectarPeriodosTest,ComandosTest}.php` (≈ 22): reimportar no duplica; «POR DESIGNAR» y sus variantes dejan el grupo sin docente; grupo repetido en dos carreras es uno; carrera sin código se resuelve por nombre; sesión corrupta se descarta y se cuenta; aula sin ubicar; aula de dos facultades es una; régimen anual → período `0/AAAA`; fallo deja fila `FALLO` y nada a medias; un grupo con inscritos no se borra; detección con y sin fechas; fechas ajustadas a mano no se pisan. `tests/Feature/DatosDemoTest.php` (2): `DemoSeeder` corre dos veces sin duplicar y deja un examen abierto ahora.
- **Terminado cuando**: `composer datos:demo` termina sin error en menos de 2 min; `php artisan oferta:importar` imprime FCyT 1.100 grupos, FCE 1.007, FHCE 679, FACH 383; `php artisan tinker --execute="echo App\Modules\Academico\Domain\Models\Docente::count();"` da 849; `composer quality:backend` en verde.

#### F0-D · Armazón del frontend

- **Objetivo**: entrar al sistema real y ver el menú de cada rol con páginas provisionales.
- **Archivos**: todo lo de 5.1 fuera de `paginas/` (`api/*`, `sesion/*`, `navegacion/*`, `componentes/*`, `utiles/*`, `test/*`), cada uno con su prueba; `paginas/auth/{Login,PrimerIngreso,RestablecerContrasena,AccesoAula}.jsx` y sus pruebas; **un archivo provisional por cada página** de 5.5; `resources/css/app.css`, `resources/fonts/*`, `resources/views/app.blade.php`, `vite.config.js`, `package.json` (`vite-plugin-pwa`), `public/{favicon.svg,iconos/*}`. Las rutas `/sw.js` y `/manifest.webmanifest` de `routes/web.php` las deja escritas F0-A. Elimina todo `resources/js/paginas/**` y `resources/js/componentes/**` anteriores y `resources/js/bootstrap.js`.
- **Depende de**: F0-A (rutas web) y F0-B (rutas 1–6) para probar de punta a punta; puede empezar antes contra el contrato.
- **Pruebas** (vitest, ≈ 60): cliente (401 → `/login`, 409 → `/primer-ingreso`), `usarSondeo` (intervalo, pausa con la pestaña oculta, tres fallos), `usarPaginaServidor`, `SesionContexto`, `RutaProtegida` (rol equivocado, sin permiso), `EsquemaApp` (menú por rol, barra inferior y «Más»), cada componente compartido, `Login` (error con intentos, bloqueo, recuperar), `PrimerIngreso` (reglas a la vista), `AccesoAula`.
- **Terminado cuando**: `npm run quality` en verde; con `composer datos:demo`, `php artisan serve` y `npm run dev`, las cuentas de 4.5 entran y cada una ve su menú; `corina.flores` cae en `/primer-ingreso`; `npm run build` genera `public/build/sw.js` y `GET /sw.js` responde 200 con `Service-Worker-Allowed: /`; Lighthouse marca la aplicación como instalable.

### 6.4 Paquetes paralelos de backend

Cada uno termina cuando `php artisan test tests/Feature/Modules/<Módulo>` y `composer quality:backend` están en verde, y cada ruta suya tiene, como mínimo, prueba de caso feliz, de validación, de 401, de 403 por permiso, de 403 por alcance (donde aplique) y de bitácora (en las escrituras).

| Paq. | Módulo y carpeta propia | Rutas | Depende de |
|---|---|---|---|
| **B1** | `Administracion` (menos lo de F0-B): `routes/{usuarios,bitacora,respaldos}.php` | 8–17 | F0 |
| **B2** | `Academico` (menos lo de F0-C): `routes/academico.php` | 18–33 | F0 |
| **B3** | `Estudiantes`: `routes/estudiantes.php` | 34–41 | F0 |
| **B4** | `Examenes`: `routes/examenes.php` | 42–53 | F0 |
| **B5** | `Habilitacion`: `routes/habilitacion.php` | 54–56 | F0 |
| **B6** | `Ingreso`: `routes/ingreso.php`, `resources/views/qr/*`, `resources/views/correos/codigo_qr.blade.php`; la ruta 7 en `routes/ingreso.php` (no en `auth.php`) | 7, 57–69 | F0 |
| **B7** | `Monitoreo`: `routes/monitoreo.php` | 70–72 | F0 |
| **B8** | `Reportes`: `routes/reportes.php` | 73–80 | F0 |

**B1 · Usuarios, bitácora y respaldo.** Crea `Application/Queries/{ListarUsuarios,ConsultarMatrizPermisos}`, `Application/Actions/RestablecerContrasenaTemporal`, `Application/Contracts/{ConsultaUsuariosGateway,RegistroCorreoGateway}`, sus implementaciones, `Http/Controllers/{UsuarioController,MatrizPermisosController}`, `Http/Requests/{ListarUsuariosRequest,GuardarUsuarioRequest}`; adapta `ConsultarBitacora` (paginación, búsqueda, rol, mapa de textos), `RespaldoSqlEnDisco` + `RespaldoGateway` + `RespaldoController` (tabla `respaldos`, `EXCLUIDAS`, `RESPALDO_INCOMPATIBLE`), `Actions/{ActualizarCuentaUsuario,CambiarEstadoCuenta}`. Pruebas: adapta `AdministracionCuentasTest`, `CredencialesInicialesTest`, `BitacoraConsultaControllerTest`, `BitacoraSoloLecturaTest`, `BitacoraRegistroAutomaticoTest` (con `usuario.crear`), `RespaldoTest`; crea `UsuariosListadoTest`, `MatrizPermisosTest`, `RespaldoHistorialTest` (fila fallida, restaurado, la restauración no borra el historial ni la sesión). ≈ 75 pruebas.

**B2 · Oferta, aulas y docentes.** Crea `Application/Queries/{ListarFacultades,ListarPeriodos,ConsultarResumenPeriodo,ListarCarreras,ListarAsignaturas,ListarGruposDeAsignatura,ListarEdificios,ListarAulas,ListarDocentes,ConsultarDocente,ListarGruposDelDocente}`, `Application/Actions/{AjustarPeriodo,ActivarCuentaDocente}` (usa `Administracion\…\CuentaUsuarioGateway` y `ProponerUsuario`), `Application/Contracts/{ConsultaOfertaGateway,ConsultaAulasGateway,ConsultaDocentesGateway}`, `Domain/Exceptions/DocenteYaTieneCuentaException`, implementaciones, controladores y peticiones. Los controladores de las rutas 22 y 24 llaman a las acciones de F0-C. Pruebas (≈ 60): filtros y conteos de cada listado; docente en dos facultades sale una vez; «temporal» frente a «activa»; activar cuenta devuelve la contraseña una vez y une el docente; usuario propuesto y su sufijo si ya existe; resumen del período; importación por HTTP (éxito, fallo, en curso).

**B3 · Padrón e inscripciones.** Crea `Application/Actions/{CargarInscritos,ResolverConflicto}`, `Application/Queries/{ListarEstudiantes,ConsultarResumenPadron,ListarConflictos,ListarInscritosDeGrupo,ArmarPlantilla}`, `Application/Contracts/{LectorListaGateway,PadronGateway,InscripcionGateway,ConflictoGateway}`, `Application/DTOs/{FilaInscritoData,ResultadoCargaData,RechazoCargaData,ConflictoCargaData,EstudianteData}`, `Domain/Exceptions/{PeriodoCerradoException,ConflictoYaResueltoException}`, `Infrastructure/Import/LectorListaPhpSpreadsheet` (a partir de `git show pre-replanteo:app/Modules/Administracion/Infrastructure/Import/LectorPadronPhpSpreadsheet.php`), `Infrastructure/Export/PlantillaInscritosXlsx`, persistencia, controladores, peticiones. Además expone `Application/Contracts/InscritosDeExamenGateway` (`estudiantesDe(array $grupoIds): array`) para quien lo necesite. Pruebas (≈ 55): las cinco salidas de 3.5.1 una por una; el docente no modifica a un existente; grupo ajeno 403; período cerrado 409; archivo con `;`, con y sin encabezado, `.xlsx`; 2.000 filas en menos de 10 s; carga de facultad con asignatura y grupo; resolver por las dos vías crea la inscripción; listados con filtros; bitácora.

**B4 · Exámenes.** Reescribe `Application/Actions/{RegistrarExamen,ActualizarExamen,EliminarExamen}`, `Application/Queries/{ListarExamenesDelDocente,ConsultarExamen,ConsultarOpcionesDeGrupos,ConsultarOpcionesDeAulas,ListarAuxiliares,ConsultarCodigoDeAula}`, `Application/Actions/{RegistrarAuxiliar,RegenerarCodigoDeAula}`, `Application/Contracts/{ExamenGateway,ConsultaExamenGateway,AuxiliarGateway,CodigoAulaGateway}`, `Application/DTOs/{GuardarExamenData,AulaDeExamenData,ExamenResumenData,AvanceData,…}`, `Domain/Exceptions/{ExamenConIngresosException,GrupoYaTieneExamenException,AuxiliarYaRegistradoException}`, persistencia, `Http/Controllers/{ExamenController,OpcionesExamenController,AuxiliarController,CodigoAulaController}`, `Http/Requests/{GuardarExamenRequest,…}`. Mantiene `ExamenGateway::existe(int): bool` (lo usan B5–B8). El avance (`habilitados`, `qr_emitidos`…) se calcula con `DB::table` sobre `habilitaciones` y `codigos_qr`. Pruebas (≈ 70): alta completa en una petición; grupos de otro docente; grupo de otra asignatura 422; grupo ya con ese tipo 422; aula compartida **no** bloquea y la 49 la avisa (solape parcial, sin solape, mismo examen excluido); sugeridas; efectos de quitar aula y de quitar grupo; con ingresos 409 salvo normas y personal; solo quien registró modifica; el docente de un grupo incluido lo ve con `propio: false`; cada valor de `avance` y `estado`; `junto_con`; auxiliar nuevo crea cuenta con correo `@est.umss.edu` y envía la temporal; código de 6 dígitos único y regenerable; bitácora.

**B5 · Habilitación.** Crea `Application/Actions/{CambiarHabilitacion,RepartirPorAula}`, `Application/Queries/ListarHabilitacion`, `Application/Contracts/{HabilitacionGateway,RepartoGateway}` (**`HabilitacionGateway::condicionDe(int $examenId, int $estudianteId): ?HabilitacionData` es público: lo usa `Ingreso`**), DTO, `Domain/Exceptions/{EstudianteConIngresoException,ExamenSinAulasException}`, persistencia, controladores, peticiones. Pruebas (≈ 40): la lista son los inscritos de los grupos (no el padrón); estudiante en dos grupos sale una vez; «sin revisar» es ausencia de fila; filtros y conteos; lote por ids y por `todos + filtros`; motivo obligatorio; inhabilitar libera el aula; habilitar no la toca; reparto al aula con menos carga, empate, no mueve a los ya ubicados, sin aulas 409; alcance; bitácora.

**B6 · Ingreso.** Crea `Application/Actions/{EmitirCodigosQr,EnviarCodigosQr,VerificarEnPuerta,RegistrarExcepcion,RegistrarExpulsion,ReportarIncidencia,AtenderIncidencia,AbrirAccesoConCodigo}`, `Application/Queries/{ListarCodigosQr,ConsultarCredencial,ArmarHojaDeCredenciales,ListarAulasDelPuesto,ConsultarPuerta,ListarIncidencias}`, `Application/Contracts/{CodigoQrGateway,GeneradorImagenQr,IngresoGateway,IncidenciaGateway,PuertaGateway,AccesoAulaGateway,CorreoQrGateway}`, DTO (`VeredictoData`, `OperadorData`…), `Domain/Exceptions/{AulaSinExamenException,YaExpulsadoException,CodigoDeAulaInvalidoException}`, `Infrastructure/{Persistence/*,QR/GeneradorImagenQrEndroid,Mail/CodigoQrMail}`, `Http/Middleware/ExigirPuesto` (alias `puesto`; F0-A deja el alias y una versión mínima), controladores, peticiones. Pruebas (≈ 95): cada veredicto de la ruta 64, por QR, por código y por documento; **la puerta resuelve el examen en un aula con dos exámenes a la misma hora**; estudiante en los dos; ventana de 60 min; hora del servidor; **carrera entre dos puestos → un ingreso y un «Ya ingresó», nunca un 500** (dos inserciones y captura de la `QueryException`); el ingreso no admite `UPDATE` ni `DELETE`; intentos del sistema con su causa y su examen correspondiente; excepción de cambio de aula admite, las otras dos no; expulsión única por ingreso y el expulsado no reingresa; incidencias: tipos, mínimo de detalle, el auxiliar solo en su aula, atender una vez, solo el docente atiende; código de aula: válido solo ese día y ventana, tres fallos → 429, la sesión con código no sale de su aula, regenerar cierra accesos; QR: emisión idempotente, token sin datos personales, credencial con aula, edificio y piso, hoja con filtros; envío: `Mail::fake()` + `Queue::fake()`, un correo por estudiante a `<código>@est.umss.edu`, fila en `envios_correo`, `enviado_en`; alcance; bitácora.

**B7 · Seguimiento.** Reescribe `Application/Queries/{ListarEnCurso,ConsultarEnCurso,ConsultarAsistenciaDeAula}`, `Application/Contracts/SeguimientoGateway`, DTO, persistencia, controladores. Solo `GET`. Pruebas (≈ 30): cifras por aula; quién atiende y apoyo; `compartida`; `usa_codigo`; incidencias sin atender (solo `PERSONA`); `abierto` según la hora; listas `faltan` e `ingresaron` con búsqueda y página; expulsado; el auxiliar solo su aula y solo ese día; el administrador recibe 403; no hay rutas de escritura.

**B8 · Reportes.** Conserva `Infrastructure/Export/ExportadorReporteArchivo`, `Application/Contracts/ExportadorReporte`, `Application/DTOs/TablaReporte` (se recuperan de `pre-replanteo` sin cambios); reescribe `Application/Queries/{ArmarTabla,ConsultarReporte,ListarExamenesParaReporte,ListarMisExamenes,ArmarListaDeGrupo}`, `Application/Contracts/ReporteGateway`, `Application/DTOs/{AlcanceReporte,Fila*}`, `Domain/Enums/MotivoIntento`, persistencia, `Http/Controllers/{ReporteUniversidadController,ReporteDocenteController}`, `Http/Requests/{AlcanceReporteRequest,ReporteExamenRequest}`. Pruebas (≈ 45): cada reporte con sus filas y totales; alcance por filtros y por examen; `cifras` sobre todo el alcance; intentos con `examen_id` nulo atribuidos por aula y ventana; cada exportación a PDF y a xlsx abre y conserva filtros y ceros a la izquierda; nombres de archivo; `SIN_DATOS`, `SIN_RENDIR`; el docente no descarga un examen ajeno; el administrador no usa los del docente y viceversa; solo `GET`.

### 6.5 Paquetes paralelos de frontend

Cada uno reemplaza sus páginas provisionales por las de la maqueta según 5.1, con su `.test.jsx` (carga, vacío, error de lectura, cada filtro envía sus parámetros, cada acción envía su cuerpo, cada error del servidor se muestra). Termina cuando `npm run quality` está en verde y, con `composer datos:demo`, el recorrido manual de su rol coincide con la maqueta a 390 px y a 1.360 px.

| Paq. | Páginas (`resources/js/paginas/`) | Rutas que consume | Backend que necesita para la prueba real |
|---|---|---|---|
| **W1** | `admin/{Periodo,Oferta,Aulas,Docentes}.jsx`, `estudiantes/Padron.jsx` | 18–32, 34–39 | B2, B3 |
| **W2** | `admin/{Usuarios,Bitacora,Respaldo}.jsx`, `reportes/Reportes.jsx` | 8–17, 73–76 | B1, B8 |
| **W3** | `examenes/{Examenes,RegistrarExamen}.jsx`, `habilitacion/Habilitacion.jsx`, `docente/{CodigosQr,MisGrupos}.jsx`, `reportes/ReportesDocente.jsx` | 33, 40–61, 77–80 | B3, B4, B5, B6, B8 |
| **W4** | `docente/EnCurso.jsx`, `monitoreo/Asistencia.jsx`, `ingreso/PuntoControl.jsx` | 62–72 | B6, B7 |

Pruebas esperadas: W1 ≈ 45, W2 ≈ 35, W3 ≈ 60, W4 ≈ 45 (W4 incluye el sondeo con `vi.useFakeTimers` y el lector de QR simulado con `vi.mock('qr-scanner')`).

### 6.6 Qué pasa con las 359 pruebas existentes

| Grupo | Hoy | Destino |
|---|---|---|
| `tests/Architecture/*` (4 archivos) | 21 | **Se conservan** las 21. Solo `ModulePublicApiGuardTest` puede necesitar renombrar sondas (F0-A) |
| `tests/Feature/ExampleTest.php` | 1 | Se conserva |
| Administración — acceso: `AuthenticationControllerTest` (8), `AuthenticationHardeningTest` (4), `LoginSecurityTest` (6), `BitacoraSesionTest` (4), `CambioContrasenaTest` (6), `RestablecimientoContrasenaTest` (8), `PermisosVigentesTest` (7), `UserModelConfigurationTest` (2) | 45 | **Se adaptan** (F0-B): `email` → `usuario`, regla de contraseña |
| `RolesHeredadosTest` (4) | 4 | **Se reescribe** como `RolesDelSistemaTest` (F0-B) |
| `AdministracionCuentasTest` (9), `CredencialesInicialesTest` (6), `BitacoraConsultaControllerTest` (8), `BitacoraSoloLecturaTest` (5), `BitacoraRegistroAutomaticoTest` (3), `RespaldoTest` (11) | 42 | **Se adaptan** (B1) |
| `RolesPermisosTest` (11) | 11 | **Se elimina** (ya no se editan roles); la vigencia sigue cubierta por `PermisosVigentesTest` |
| `AmbientesTest` (11), `CapacidadAmbienteTest` (8), `PadronEstudiantesTest` (10) | 29 | **Se eliminan** con lo eliminado |
| `CargaMasivaPadronTest` (13) | 13 | **Se muda y se adapta** a `Estudiantes/CargaInscritosTest` (B3): se conservan los casos de formato de archivo |
| Exámenes: `Asignatura*Test` (18), `DocenteControllerTest` (2), `RegistroDocenteTest` (8), `NormasExamenTest` (11), `ConsultaHabilitacionTest` (13) | 52 | **Se eliminan** |
| `RegistroExamenTest` (14), `AmbientesDelExamenTest` (13) | 27 | **Se reescriben** (B4); los casos de capacidad y de «ambiente no disponible» desaparecen, los de solape pasan a ser de aviso |
| `HabilitacionExamenTest` (13), `DistribucionExamenTest` (12) | 25 | **Se mudan y se reescriben** en `Habilitacion/` (B5); desaparecen los casos de cupo y de ajuste individual |
| Ingreso: `RegistroIngresoTest` (14), `DobleIngresoTest` (5), `LecturaQrTest` (7), `CodigosQrTest` (10), `ExpulsionTest` (8), `SituacionExcepcionalTest` (9), `ObservacionIngresoTest` (5) | 58 | **Se reescriben** (B6); desaparecen los casos de anulación y de observación libre del ingreso |
| `Monitoreo/TableroTest` (10) | 10 | **Se reescribe** (B7) |
| `Reportes/ReportesTest` (14), `ExportacionTest` (7) | 21 | **Se reescriben** (B8); `ExportacionTest` casi íntegro |
| **Total** | **359** | 22 intactas, 100 adaptadas, 145 reescritas, 92 eliminadas |

Integración (`tests/Integration/PostgreSQL`, 32): se eliminan los cuatro `Asignatura*Test` (11); se adaptan `Authentication*` y `SessionInactivityTest` (F0-B), `BitacoraAtomicityTest` y `PostgreSqlMigrationTest` (F0-A); el resto queda igual; se agrega `EsquemaReplanteoTest`. Frontend (147 vitest): todas las de páginas y de `Aplicacion` se sustituyen (F0-D y W1–W4).

Objetivo al cerrar: **≈ 640 pruebas de backend** (21 + 1 + ≈ 65 de F0-B + ≈ 36 de F0-A/C + ≈ 75 + 60 + 55 + 70 + 40 + 95 + 30 + 45 + algunas más), ≈ 245 de frontend, ≈ 35 de integración. La cifra exacta la fija I1 en el README.

### 6.7 I1 · Integración y cierre

- **Objetivo**: comprobar el sistema entero y dejar la documentación al día.
- **Archivos**: `README.md`, `docs/{arquitectura,modelo-datos,despliegue}.md`, `docs/guia/avance.md`, `docs/replanteamiento/README.md` (estado: implementado) y sus diagramas `.puml` (auxiliares por aula, aula compartida, incidencias, acceso por usuario), `.env.example`, `.env.testing.example`, `.github/workflows/ci.yml` (si cambia algún paso).
- **Depende de**: todos.
- **Terminado cuando**: `composer quality:backend`, `composer test:postgresql` y `npm run quality` en verde; `composer datos:demo` y el recorrido completo de los cuatro flujos de `docs/replanteamiento/README.md` con las cuentas de 4.5, en teléfono y escritorio, incluida una lectura real de QR con la cámara y un ingreso simultáneo desde dos navegadores; `grep -rn "ambiente\|Ambiente" resources/js` sin resultados visibles al usuario (se dice «aula»); `grep -rniE "capacidad|responsable acad" app resources/js` vacío.

---

## 7. Riesgos y decisiones abiertas

### 7.1 Decisiones: lo que el README deja propuesto o sin decidir, y el valor que se implementa

| # | Asunto | Estado en el diseño | Valor por defecto que se implementa |
|---|---|---|---|
| D-01 | Quién controla el ingreso (decisión 18) | Propuesto | Docente y auxiliares, asignados por examen y aula: un encargado (`examen_aula.encargado_id`) y cero o más de apoyo. No hay rol global de control |
| D-02 | Cómo se propone el usuario (decisión 26) | La maqueta se contradice: Docentes sugiere `ju.molina` (regla DTIC del correo) y las cuentas de ejemplo son `leticia.blanco` | **`nombreapellido`** (revisada el 23 oct 2026, antes `nombre.apellido`): primer nombre de pila + apellido paterno, sin punto, en minúsculas y sin tildes (en la oferta el nombre viene «Paterno Materno Nombres»: el de pila es la tercera palabra si hay más de dos, si no la última). Si está tomado, sufijo numérico (`leticiablanco2`). Es lo que dice el *placeholder* de todos los formularios. Siempre editable |
| D-03 | Cómo entra un auxiliar (decisión 19; pregunta abierta 1) | Propuesto: cuenta propia y, para el ocasional, código por aula. La maqueta genera el código pero **no dibuja dónde se usa** | Las dos formas. Cuenta: la crea el docente («Auxiliar nuevo») o el administrador. Código: seis dígitos por aula y examen, regenerable; se usa en `/acceso-aula` (enlace «Entrar con código de aula» en el inicio de sesión, **pantalla agregada**), pide además el nombre, vale solo el día del examen desde 60 min antes hasta el final, y esa sesión solo ve la puerta de su aula. Sus ingresos quedan con `acceso_aula_id` y sin usuario |
| D-04 | Aula compartida (decisión 20; pregunta 2) | Propuesto | Se avisa y no se impide ni se pide aceptación al otro docente. Sin notificaciones |
| D-05 | Puerta por aula y hora (decisión 21) | Propuesto | Implementada: ventana de cada examen = 60 min antes del inicio hasta su final (`config('umss.minutos_apertura', 60)`) |
| D-06 | Incidencias (decisión 25) | Propuesto | Una sola tabla; cinco tipos reportables más la expulsión; las ve y las atiende cualquier docente del examen; atender no se deshace |
| D-07 | Examen conjunto: quién cambia qué (pregunta 3; «pendiente de decidir» 3) | Sin decidir | Lo modifica y elimina solo quien lo registró. Los docentes de los grupos sumados lo ven («Registrado por …»), habilitan, emiten QR, siguen y controlan. **Sin aprobación ni notificación** al sumar un grupo ajeno: queda en la bitácora |
| D-08 | Normas del examen | El flujo 4 y el punto de control de la maqueta las muestran; el registro de examen no tiene dónde escribirlas | Un texto opcional `examenes.normas`, con un `AreaTexto` «Normas» **agregado** al paso 1 del registro. Desaparecen las normas particulares por estudiante |
| D-09 | Formato de la lista de inscritos de la WebSIS | Sin decidir: falta un archivo de ejemplo | `.csv` o `.xlsx`, columnas por posición `codigo_universitario, documento_identidad, nombres, apellidos` (más `codigo_asignatura, grupo, carrera` en la carga de facultad), encabezado opcional. El lector está aislado en `Estudiantes/Infrastructure/Import/`: cambiar el formato es cambiar ese archivo |
| D-10 | Mapa base | Sin decidir | Ninguno: se dibujan los polígonos de los edificios con el color de su facultad, como la maqueta. No se agrega MapLibre ni teselas externas (el sistema no depende de servicios externos) |
| D-11 | Ajuste manual de fechas del período | El README lo exige; la maqueta no lo dibuja | Botón «Ajustar» en los períodos sin fechas, con un diálogo de dos fechas (**agregado**) |
| D-12 | Eliminar un examen | La maqueta no lo tiene | La ruta existe (47) y está probada; **no se agrega botón** |
| D-13 | Uso sin conexión | No se pide | No hay: la aplicación instalada necesita red. El *service worker* solo guarda los estáticos |
| D-14 | Período de las carreras anuales | El README dice que `0/2026` está vigente; los archivos de FACH dicen `Gestión 2/2026` | Los grupos de una carrera con niveles «AÑO» van al período `0/AAAA`; los demás, al de `INFO.SEMESTER`. Las fechas de `0/2026` no están en ninguna fuente: quedan «Sin fechas» hasta que el administrador las ajuste (el seeder de demostración las pone) |
| D-15 | Auxiliares titulares de la oferta | Propuesto importarlos; GENDA los pierde al procesar | No se importan. Se conserva qué sesiones son de auxiliatura (`horarios.es_auxiliatura`) |
| D-16 | Auditorios de FCyT sin ubicar (pregunta 6) | Sin decidir | Se importan como aulas sin edificio: se pueden elegir para un examen y la credencial dice solo el nombre. No se sugieren ni salen en el mapa |
| D-17 | Vencimiento de la contraseña temporal | La maqueta dice «Caduca en 72 horas» | 72 h (`config('auth_security.horas_temporal', 72)`); vencida, hay que restablecerla |
| D-18 | Mensaje de intentos restantes en el inicio de sesión | La maqueta lo muestra; el sistema actual responde siempre igual para no revelar si la cuenta existe | Se muestra, como la maqueta. Revela que la cuenta existe; se acepta porque los usuarios se derivan del nombre público del docente |
| D-19 | Credenciales para imprimir | La maqueta dice «credenciales_….pdf descargado» | Hoja HTML imprimible (la actual), que el navegador guarda como PDF. Generar con Dompdf un PDF de 500 códigos QR en un alojamiento compartido es lento y arriesga el límite de memoria |
| D-20 | Nombre del archivo de respaldo | La maqueta dice `.sql.gz` | Se conserva `.sql` sin comprimir: `docs/despliegue.md` §5.3 lo importa directo desde el panel |
| D-21 | Corrección de un ingreso mal registrado | El sistema actual anulaba; el replanteo dice «inmutable» | No hay anulación. Se asienta una incidencia (`OTRA`) o, si corresponde, una expulsión |
| D-22 | Habilitación de un inscrito «sin revisar» que llega a la puerta | No se dice | No entra: veredicto «No habilitado» con el dato `Sin revisar`. El examen no está «Listo» mientras quede alguien sin revisar |
| D-23 | Mover a mano un estudiante de aula | El flujo 3 dice «ajusta a mano»; la maqueta no lo tiene | No se implementa. Para cambiarlo: inhabilitar y habilitar (libera el aula) y «Repartir», o la excepción «Cambio de aula» en la puerta |
| D-24 | Reportes | Las columnas no están en la maqueta | Las del sistema actual, con Grupo, Facultad y Docente agregados (3.10) |

### 7.2 Riesgos

| Riesgo | Efecto | Qué se hace |
|---|---|---|
| La fase 0 borra el interior de cuatro módulos antes de que nadie lo reescriba | Entre F0 y el cierre de B4–B8 el sistema no tiene exámenes ni puerta | Etiqueta `pre-replanteo`; F0 solo se da por buena con todos los controles en verde; los paquetes B corren a la vez y son cortos |
| `deptrac.php` con `--fail-on-uncovered`: una clase del marco no prevista rompe el control de un paquete | Bloqueo cruzado | F0-A agrega de antemano `ServiceProvider`, `ShouldQueue`, `Rules\Password`; cualquier otra se pide al integrador, que la agrega en un cambio aparte |
| Importar dentro de la petición HTTP | Con FCyT (1.100 grupos y unas 3.000 sesiones) puede acercarse al límite de tiempo del alojamiento | Inserciones por lotes (`upsert` de a 500); `set_time_limit(120)`; el comando `oferta:importar` queda como respaldo en desarrollo, y el resultado se sube con el guion SQL del despliegue |
| Cola de correos sin procesos permanentes | Los QR no salen si no hay `cron` | Tarea `queue:work --stop-when-empty` cada minuto en el planificador; alternativa `QUEUE_CONNECTION=sync` (5.8). 500 correos por `sync` pueden agotar el tiempo de la petición: con `sync`, la ruta 60 envía de a 100 y responde cuántos quedan |
| El correo `@est.umss.edu` existe solo si el estudiante tiene materias inscritas y pasó el alta (48 h) | Rebotes | `envios_correo` guarda el fallo; el docente siempre puede imprimir |
| Disparador de inmutabilidad en `ingresos` | Una prueba o un seeder que haga `DELETE` falla; `nullOnDelete` sobre `registrado_por` chocaría | FK en `restrict`; los seeders insertan y no corrigen; `RefreshDatabase` revierte por transacción; la restauración usa `TRUNCATE` |
| Respaldos anteriores al replanteo | No restauran sobre el esquema nuevo (el guion nombra tablas y columnas) | La ruta 17 los rechaza con `RESPALDO_INCOMPATIBLE`; `docs/despliegue.md` lo dice |
| Restaurar un respaldo borra las sesiones de código de aula y los accesos | Quien está en la puerta pierde el acceso | Es el comportamiento correcto de una restauración; la pantalla ya advierte «Sobrescribe los datos actuales» |
| Identidad del docente por nombre normalizado | Dos docentes homónimos se funden; una corrección de nombre en la fuente crea un docente nuevo sin cuenta | Aceptado (la fuente no trae otro identificador). La pantalla Docentes muestra «sin cuenta» al nuevo; unirlos a mano queda fuera de alcance |
| Código de carrera ausente (FCE) o desconocido | Carrera sin enlazar | Resolución por nombre contra el pensum; si tampoco coincide, se crea con código `SIN-<facultad>-<n>` y el resumen de la importación lo cuenta |
| El código SIS como única clave del estudiante en la carga del docente | Un error de tipeo crea un estudiante fantasma | La lista viene de un archivo de la WebSIS, no se teclea; el conflicto salta si el código existe con otro documento o nombre |
| La cámara exige HTTPS | En un dominio sin certificado el lector no abre | Ingreso manual siempre disponible; requisito de despliegue anotado en 5.7 |
| Sondeo cada 10 s con muchas aulas abiertas a la vez | Carga en el alojamiento compartido | Consultas agregadas con índices (`idx_ingreso_examen_aula`, `idx_incidencia_examen_atendida`); pausa con la pestaña oculta; respuestas pequeñas (sin listas de estudiantes en «En curso») |
| La maqueta trabaja con nombres (`?aula=691A`) y el sistema con ids | Enlaces rotos al portar | En todas las rutas del cliente `?aula=` y `?examen=` llevan el **id**; se revisa en I1 |
| Dos vocabularios en el código: «aula» (interfaz y esquema nuevo) y «ambiente» (pliego, documentos viejos) | Confusión | En código, tablas e interfaz: **aula**. «Ambiente» solo queda en documentos históricos (`docs/guia/*`) |

### 7.3 Dudas que no se resuelven leyendo el repositorio

1. El formato real de la lista de inscritos de la WebSIS (D-09) y si trae el documento de identidad: si no lo trae, la regla de conflicto por documento no aplica a las cargas de docentes y `documento_identidad` queda nulo.
2. Si la administración puede obtener las inscripciones de una facultad entera (la ruta 36 lo supone).
3. Si el servidor de destino permite una tarea `cron` (ya la necesita el respaldo nocturno) y si sirve por HTTPS.
4. Si PostgreSQL está disponible en el alojamiento (`docs/arquitectura.md` §3 lo deja pendiente): el esquema nuevo usa índices parciales, `CHECK` y un disparador que en MariaDB habría que rehacer.
5. Las fechas del período anual `0/2026` y de los semestres de FCE, FHCE y FACH.
6. Si el equipo aprueba las tres pantallas o campos agregados a la maqueta: `/acceso-aula` (D-03), «Normas» en el registro (D-08) y «Ajustar» fechas (D-11).
7. Si el nombre de usuario debe seguir `nombre.apellido` (D-02) o la regla DTIC de dos letras.

---

## 8. Notas de implementación

Lo que cada paquete resolvió distinto de lo escrito arriba, o encontró sin definir. Lo lee el paquete siguiente antes de empezar.

### F0-A

**Esquema**

- **Son 41 tablas, no 40**: las 40 de 2.1 más `migrations`. `PostgreSqlMigrationTest` afirma la lista exacta (tampoco puede quedar una tabla del esquema anterior).
- `users.usuario` es `NOT NULL` y único (`uq_users_usuario`). No se agregó `CHECK` del formato `^[a-z0-9._-]+$`: es regla de aplicación (F0-B). Todo lo que inserte en `users` tiene que dar `usuario`.
- Claves foráneas que 2.x deja sin regla de borrado: `nullOnDelete` en las anulables de contexto (`ajustado_por`, `ejecutada_por`, `cargada_por` de `inscripciones`, `resuelto_por`, `registrada_por`, `emitido_por`, `reportada_por`, `atendida_por`, `examen_correspondiente_id`, las de `respaldos` y `envios_correo`…) y `restrictOnDelete` en las obligatorias (`cargas_inscritos.cargada_por`, `conflictos_padron.reportado_por`, `examen_aula.encargado_id`, `examen_aula_apoyo.user_id`).
- **`ingresos`: ninguna clave foránea anula ni borra en cascada**, tampoco `acceso_aula_id` (va en `restrict`). Un `SET NULL` sobre `ingresos` es un `UPDATE` y lo rechaza el disparador. Consecuencia para B4 y B6: un `examen_aula` con accesos que ya registraron ingresos no se puede borrar, y «regenerar cierra accesos» **no puede borrar filas de `accesos_aula`**. `accesos_aula` no tiene columna de cierre: un acceso vale mientras `accesos_aula.creado_en >= examen_aula.codigo_generado_en`.
- `incidencias.estudiante_id` e `incidencias.ingreso_id` van en `restrict` (son historia).
- Nombres de las restricciones que no traían nombre en 2.x: `chk_examen_duracion`, `chk_habilitacion_aula_solo_habilitado`, `chk_incidencia_expulsion_con_ingreso`, `uq_periodo_codigo`, `uq_facultad_clave`, `uq_facultad_sigla`, `uq_carrera_codigo`, `uq_asignatura_codigo`, `uq_docente_user`, `uq_docente_nombre_normalizado`, `uq_edificio_clave`, `uq_estudiante_codigo`, `uq_auxiliar_user`, `uq_codigo_qr_token`, `uq_respaldo_archivo`, `idx_importacion_facultad`, `idx_acceso_examen_aula`. El disparador `trg_ingresos_inmutables` llama a la función `fn_ingresos_inmutables()` y su mensaje nombra al disparador.
- `cargas_inscritos.rechazos` es anulable y los seis contadores tienen valor por defecto 0. `estudiantes.verificado` tiene valor por defecto `false`.
- Sin marcas de tiempo (`$timestamps = false`): `facultades`, `plan_estudios`, `horarios`, `importaciones_oferta`, `examen_grupo`, `examen_aula_apoyo`, `accesos_aula`. Solo `created_at` (`UPDATED_AT = null`): `cargas_inscritos`, `ingresos`, `respaldos`.
- `database/postgresql/control_ingreso.sql` sigue describiendo el esquema anterior. No está en la lista de ningún paquete: lo rehace o lo retira I1 junto con `docs/modelo-datos.md`.

**Enumeraciones y modelos**

- Dos enumeraciones que 6.3 no lista y que ningún paquete paralelo puede crear (regla 6.1.2): `Estudiantes/Domain/Enums/AlcanceCarga` (`GRUPO`, `FACULTAD`) y `EstadoConflicto` (`PENDIENTE`, `RESUELTO`). `OrigenEstudiante` sirve también para `inscripciones.via` y `conflictos_padron.via`.
- `Veredicto` tiene los valores en minúsculas (`admitido`, `ya_ingreso`…), tal cual viajan en la ruta 64.
- Ayudas agregadas: `TipoIncidencia::reportables()` (todos menos `EXPULSION`, para la ruta 68), `TipoExcepcion::admite()` y `TipoExcepcion::tipoIncidencia()` (el mapeo de la ruta 65), `Periodo::estado()` y `estaVigente()`, `Estudiante::correo()` y `nombreCompleto()` (`Apellidos, Nombres`), `Docente::tieneCuenta()`, `Incidencia::estaAtendida()`.
- El dominio del correo de estudiantes es la constante `Estudiante::DOMINIO_CORREO` (`est.umss.edu`). Desde otro módulo no se puede importar el modelo: se arma con `config('umss…')` cuando F0-C cree `config/umss.php`, o se repite la regla.
- **B8 lista `Reportes/Domain/Enums/MotivoIntento` entre lo que crea, y la regla 6.1.2 dice que ningún paquete paralelo toca `Domain/Enums`.** F0-A no lo creó (sus valores dependen de las consultas de B8) y eliminó `TipoIntento`, cuyos valores ya no existen. Se entiende que B8 queda autorizado a crear ese único archivo.
- `Monitoreo/Domain/Enums/EstadoAsistencia` y las excepciones de dominio de piezas «se adapta» (`Examenes/…/ExamenConIngresosException`, `Ingreso/…/{CodigoDeOtroExamenException,ExpulsionNoPermitidaException,IngresoNoPermitidoException}`) **se conservaron tal cual**: compilan solas. Sus dueños (B4, B6, B7) las adaptan o las borran.
- Los modelos solo tienen relaciones dentro de su módulo (`deptrac` prohíbe importar un modelo ajeno): `Examen` no tiene `asignatura()`, `Inscripcion` no tiene `grupo()`. Lo ajeno se lee por `DB::table`.
- En `Domain` solo se admiten `Model`, `HasMany`, `BelongsTo`, `BelongsToMany` y `Carbon`. En `Infrastructure` **no** está `Illuminate\Database\Eloquent\Builder`: los cierres de consulta se tipan con `Illuminate\Database\Query\Builder` sobre `DB::table`.

**Contratos**

- `Academico\Application\DTOs\PeriodoData` lo creó F0-A (lo necesita `PeriodoGateway`), aunque 6.3 lo lista en F0-C: `id`, `codigo`, `tipo` (valor de `TipoPeriodo`), `tipoEtiqueta`, `fechaInicio`, `fechaFin` (`AAAA-MM-DD` o nulo), `ventanas`. F0-C lo amplía si le falta algo, sin quitar campos.
- `PeriodoGateway` tiene solo `vigentes()` y `principal()` (el vigente con más grupos; en empate, el más reciente). F0-C le suma lo que necesite `DetectarPeriodos`.
- `AlcanceExamenGateway::aulasQueAtiende()` devuelve `list<AulaAtendidaData>` (`examenId`, `aulaId`, `esEncargado`), DTO nuevo en `Examenes/Application/DTOs`.
- `AlcanceExamenGateway::atiendeAula()` responde solo por la asignación (encargado o apoyo). **No mira la fecha ni si la cuenta es docente del examen**: «el docente del examen controla cualquier aula» y «solo el día del examen» los combina la acción que llama (`esDocenteDelExamen() || atiendeAula()` y la fecha del examen).
- `Ingreso/Http/Middleware/ExigirPuesto` (alias `puesto`) comprueba el permiso con `Administracion\Application\Contracts\RolGateway::permisosVigentesDe()`: `VerificarPermiso` no es público entre módulos.
- Quedaron fuera del borrado, porque 1.6 los marca «se conservan tal cual» y compilan solos: `Reportes/Application/Contracts/ExportadorReporte`, `Reportes/Application/DTOs/TablaReporte` y `Reportes/Infrastructure/Export/ExportadorReporteArchivo`, con su enlace en `ReportesServiceProvider`. B8 no necesita recuperarlos.

**Cambios mínimos en archivos de F0-B y B1** (para que la suite quedara en verde; sus dueños los rehacen)

- `Administracion/Application/Contracts/RolGateway` y `EloquentRolGateway`: quedaron `listarUsuarios()`, `asignarRolAUsuario()` y `permisosVigentesDe()`. Se quitó lo que usaba los DTO eliminados (crear, eliminar y sincronizar roles, listar roles y permisos). `ConsultarMatrizPermisos` (B1) necesita su propia lectura.
- `NuevaCuentaData` pasó a `(nombre, usuario, ?correo)`; **falta `rol`** (F0-B).
- `CuentaUsuarioGateway::crear(NuevaCuentaData $datos, ?int $autorId)`: la cuenta y el asiento `usuario.crear` se escriben ahora **en la misma transacción, dentro del gateway** (antes el asiento lo hacía la acción, fuera de toda transacción, y `BitacoraAtomicityTest` no podía usarlo de vehículo). El correo de credenciales sale después de confirmar y solo si hay correo. `CrearCuentaUsuario` quedó como un pase directo al gateway. Se agregó `usuarioRegistrado(string): bool`. La contraseña inicial sigue siendo `Str::password(12)`: el formato `Xx9-Xxx9-Xx9` y el vencimiento son de F0-B.
- `ActualizarCuentaUsuario` y `CambiarEstadoCuenta` quedaron sin controlador ni rutas (B1).
- El inicio de sesión **sigue siendo por correo** (`AuthenticateUser`, `LoginRequest` sin tocar): funciona porque la columna `email` sigue ahí.
- `AdministracionServiceProvider` ya existe con los ocho enlaces vigentes; F0-B agrega los suyos ahí.
- `database/factories/UserFactory.php` genera `usuario` como `usuario.######`. `tests/Support/UsuarioConPermisos.php` no se tocó: hereda el usuario de la fábrica.
- Pruebas de F0-B tocadas lo mínimo: `usuario` en los `User::create` de `BitacoraSesionTest`, `PermisosVigentesTest` y `RestablecimientoContrasenaTest`; `PermisosVigentesTest` y `CambioContrasenaTest` usan `GET /api/bitacora` (permiso `bitacora`) en lugar de las rutas eliminadas `/api/roles` y `/api/auth/usuarios`. `RolesHeredadosTest` pasa sin cambios (el seeder sigue con cuatro roles hasta F0-B). En integración, `usuario` en las inserciones directas de `AuthenticationSchemaTest`, `AuthenticationSecurityLockingTest` y `PostgreSqlTransactionIsolationTest`.
- Pruebas de B1: `RespaldoTest` usa `Estudiantes\…\Estudiante` y la tabla `estudiantes` en lugar de `Student`; pasa entera. Se **retiraron** `AdministracionCuentasTest` (9), `CredencialesInicialesTest` (6) y `BitacoraRegistroAutomaticoTest` (3): dependían de las rutas de cuentas y de asignaturas. B1 las rehace desde `pre-replanteo`.

**Pendiente de otros paquetes, a la vista**

- `composer datos:demo` y `composer datos:volumen` apuntan a seeders eliminados y fallan hasta que F0-C cambie `composer.json`.
- `database/seeders/RolePermissionSeeder.php` sigue con los cuatro roles y los doce permisos anteriores (F0-B).
- `routes/console.php` sin tocar: el respaldo sigue a las 02:00 (F0-C). `RespaldoSqlEnDisco::EXCLUIDAS` todavía no tiene `respaldos` (B1): hoy un respaldo vuelca y restaura también esa tabla.
- `docs/ejemplos/padron_*.csv` y `resources/views/qr/hoja.blade.php` siguen en el árbol (F0-C y B6).
- `GET /sw.js` y `GET /manifest.webmanifest` responden 404 mientras no exista `public/build/sw.js` (lo genera F0-D con `vite-plugin-pwa`).
- `resources/js` no se tocó: las páginas actuales llaman a rutas que ya no existen (F0-D las reemplaza).

**Apoyos de prueba** (`tests/Support`)

- `DatosAcademicos`: `periodoVigente()` (`2/<año actual>`, de hace 60 días a dentro de 60), `facultad($clave = 'fcyt')`, `asignatura()`, `docenteConCuenta(?User $cuenta)`, `docenteSinCuenta()`, `grupo(?Docente, ?Asignatura, ?codigo, ?Periodo, ?Facultad)`, `edificio()`, `aula(?nombre, ?Edificio, ?piso)`.
- `DatosDeExamen`: `estudiantesInscritos(Grupo, n)`, `examen(Docente, grupos, aulas, atributos)` (por defecto **hoy, empezó hace 5 minutos, 90 min**, cada aula a cargo de quien lo registró), `habilitar(Examen, estudiantes, ?Aula)`, `inhabilitar(Examen, estudiantes, motivo)`, `auxiliar(?User $cuenta, ?User $registradoPor)` (devuelve la cuenta), `ponerACargo(Examen, Aula, User)`, `ponerDeApoyo(Examen, Aula, User)`, `ingresar(Examen, Estudiante, Aula, ?User)`.
- Ninguno de los dos da permisos: la cuenta con permisos se arma con `usuarioConPermisos([...])` y se pasa a `docenteConCuenta()` o a `auxiliar()`.
- Una prueba que espera un rechazo de PostgreSQL y sigue afirmando después tiene que correr la operación en `DB::transaction(...)`: la sentencia fallida deja inservible la transacción de `RefreshDatabase` (ver `EsquemaReplanteoTest::assertRechaza`).
- `tests/Fixtures/genda/` es de F0-C; F0-A no lo creó.

**Pruebas al cerrar F0-A**: `php artisan test` 104 (21 de arquitectura, 1 de ejemplo, 73 de Administración, 9 nuevas de `Academico` y `Examenes` sobre los contratos de alcance y de períodos); `composer test:postgresql` 37 (16 de `EsquemaReplanteoTest`).

### F0-D

Decisiones tomadas donde la especificación no llegaba, y pendientes para otros paquetes.

1. **Nombre de los hooks.** `react-hooks/rules-of-hooks` solo reconoce como hook una función `use…`; una llamada `usarConsulta` que por dentro use `useState` es error de ESLint. Los hooks se **definen** como `use…` y se exportan con el nombre en español de 5.1: los de exportación por defecto (`api/usarConsulta.js`, `usarSondeo.js`, `usarPaginaServidor.js`, `usarEdificios.js`, `componentes/usarExamen.js`) se importan con el nombre del archivo; los nombrados salen con los dos nombres (`usarSesion`/`useSesion`, `usarFacultades`/`useFacultades`, `usarAccesoAula`/`useAccesoAula`, `usarColorDeFacultad`/`useColorDeFacultad`). `useAviso` y `usePaginas` conservan el nombre de la maqueta. No se tocó `eslint.config.js`.
2. **Archivos agregados a la lista de 5.1**: `api/errores.js` (`mensajeDe`, `erroresDe`, `estadoDe`, `codigoDe`; aparte del cliente para que las pruebas que lo simulan los conserven), `api/usarEdificios.js`, `api/__mocks__/cliente.js` (lo toma solo `vi.mock('…/api/cliente')`), `sesion/AccesoAulaContexto.jsx`, `paginas/auth/reglasContrasena.jsx` (reglas compartidas por `PrimerIngreso` y `RestablecerContrasena`).
3. **`usarConsulta`**: `cargando` solo es cierto sin datos que mostrar; un fallo al recargar (o del sondeo) conserva los datos, no pone `error` y suma en `fallos`; `error` solo aparece si no quedan datos de esa misma consulta. `usarSondeo` deriva `sinConexion` de `fallos >= 3`. `Encabezado` recibe la prop nueva `sinConexion` para la insignia de 5.4.
4. **Ruta permitida por rol, no solo por permiso.** Docente y Auxiliar comparten `punto_control` y `seguimiento_vivo`: `RutaProtegida` comprueba que la ruta sea una vista del rol (`navegacion/vistas.js`, cada vista con su `permiso`), así el auxiliar no entra a `/control`. `/examenes/nuevo` figura como vista oculta del docente. Una cuenta sin ninguna vista cae en `/sin-acceso` (nombre, rol y «Cerrar sesión»).
5. **`EsquemaApp`** usa `Link` en lugar de `NavLink`: `NavLink` pisa `aria-current` con su propia coincidencia de ruta y la vista padre de una oculta (`/habilitacion` → Exámenes) no quedaba marcada. El aspecto no cambia.
6. **`MapaCampus`**: `edificios` y `caja` por *prop*; `resaltados` y `contorno` aceptan nombres de aula (texto) o ids de edificio (número), porque la API da `edificio_id` en varias respuestas y el nombre de un aula se repite entre facultades; `rotulosEdificio` rotula por id. `edificioDeAula(edificios, aula, facultad)` y `pisoDeAula(...)` reciben la lista como primer argumento.
7. **`FiltroFacultad`** entrega la sigla (como la maqueta) o, con `campo="clave"`, la clave que esperan los `?facultad=` de la API. `colorDeFacultad(sigla, facultades)` recibe la lista; dentro de un componente, `usarColorDeFacultad()`.
8. **`colorDeAsignatura(nombre)`**: sin la lista fija de la maqueta, el color sale del nombre (paleta de ocho colores, estable entre sesiones).
9. **`Incidencias`**: `aula`/`aulas` son `{ id, nombre }`; `registrar(datos)` y `atender(id)` devuelven la promesa de la llamada; `ListaIncidencias` muestra «Atender» solo si recibe `atender`.
10. **`Campo`** valida también el valor controlado (`value`), además del interno de la maqueta.
11. **Inicio de sesión**: los textos son los de la maqueta, no el `message` del servidor (`Usuario o contraseña incorrectos · 3 intentos restantes`, `Cuenta bloqueada · 15 minutos`); la contraseña temporal vencida se muestra como `Contraseña temporal vencida` (el mensaje del servidor le habla al usuario). Con la cuenta bloqueada los campos no se deshabilitan, para poder entrar con otra cuenta. `PrimerIngreso` agrega un «Cerrar sesión» bajo el formulario (sin él no hay forma de cambiar de cuenta).
12. **`/acceso-aula`**: tras entrar pone `?aula=<id>` y dibuja `paginas/ingreso/PuntoControl` sin menú, dentro de `AccesoAulaContexto` (`usarAccesoAula()` → `{ aula_id, aula, examen_id, nombre, hasta }`, nulo con sesión normal). Al recargar recupera el acceso preguntando a la ruta 62; en ese caso `nombre` y `hasta` quedan nulos porque ninguna ruta los devuelve.
13. **PWA**: a la configuración de 5.7 se le agregan `workbox.modifyURLPrefix: { '': '/build/' }` e `inlineWorkboxRuntime: true`. Sin ellos el precaché salía con direcciones relativas (`assets/…`) y el `sw.js` importaba `./workbox-*.js`; servido desde `/sw.js`, las dos cosas apuntaban a la raíz y la instalación del *service worker* fallaba.
14. **Inter** se copia de `@fontsource-variable/inter` 5.3.0 (subconjuntos `latin` y `latin-ext`, variable) a `resources/fonts/`, con su licencia OFL; no queda como dependencia.

Pendientes para otros paquetes:

- **B6 / integrador**: no hay ruta para cerrar un acceso con código de aula. «Salir» llama a `POST /api/auth/logout` (ignora el error) y olvida el acceso en el cliente, pero la cookie puede seguir abierta hasta que venza. Hace falta, por ejemplo, `DELETE /api/auth/codigo-aula`. Convendría además que la ruta 62 devuelva `nombre` y `hasta` del acceso.
- **W4**: `PuntoControl` se monta también sin `EsquemaApp` ni cuenta (`usarSesion().usuario` nulo); el operador sale de `usarAccesoAula()`.
- **I1**: sin el backend no se probó de punta a punta (cuentas de 4.5, `corina.flores` → `/primer-ingreso`, `GET /sw.js`, Lighthouse) ni se revisó en un navegador a 390 px y 1.360 px.

### F0-D · Guía rápida del armazón para W1–W4

Componentes de `resources/js/componentes/` (cada uno con su prueba; nadie más los edita):

| Componente | Props |
|---|---|
| `useAviso(duracion?)` | devuelve `[aviso, avisar(texto, 'exito' \| 'error')]`; se dibuja con `{aviso}` |
| `Boton` | `variante` (primario, secundario, peligro, fantasma, enlace, peligroContorno), `tamano` (chico, normal, grande), `className`, resto al `<button>` |
| `Buscador` | `valor`, `onCambiar(texto)`, `placeholder`, `etiqueta`, `className` |
| `Campo` | `etiqueta`, `error` (mensaje 422), `requerido`, `validar(v)`, `sufijo`, `ayuda`, `className`, resto al `<input>` |
| `Seleccion` / `AreaTexto` | `etiqueta`, `requerido`, `className`; `AreaTexto` suma `error`, `pie` |
| `Chips` | `opciones [{valor, etiqueta, color?, conteo?}]`, `valor`, `onCambiar`, `etiqueta`, `extra`, `maximo=6` |
| `FiltroFacultad` | `valor`, `onCambiar`, `conteo(sigla)`, `extra`, `campo='sigla' \| 'clave'` |
| `PuntoFacultad`, `colorDeFacultad`, `usarColorDeFacultad` | `PuntoFacultad {sigla, conTexto}`; `colorDeFacultad(sigla, facultades)`; el hook devuelve `(sigla) => color` |
| `Dato` | `etiqueta`, `valor`, `tono` (neutro, advertencia, exito, primario, peligro), `onClick` |
| `Dialogo` | `titulo`, `abierto=true`, `onCerrar`, `acciones`, `ancho` |
| `Insignia` | `tono` (exito, peligro, advertencia, info, primario, neutro), `punto` |
| `Logo` | `className` |
| `Pasos` | `pasos [texto]`, `actual`, `onIr(i)` |
| `Tarjeta` | `id`, `titulo`, `acciones`, `sinRelleno`, `className` |
| `Encabezado` | `titulo`, `subtitulo`, `volver {a, texto}`, `sinConexion`, `children` (acciones) |
| `Paginacion` / `usePaginas` | `total`, `pagina`, `porPagina`, `onCambiar`, `unidad` (texto o `[singular, plural]`), `className`; `usePaginas(lista, porPagina, clave)` solo para catálogos en memoria |
| `MapaCampus` | `edificios`, `caja`, `facultad` (sigla), `resaltados` y `contorno` (nombre de aula o id de edificio), `rotulos`, `rotulosEdificio`, `tamanoRotulo`, `seleccionado`, `onElegir(edificio)`, `alto` |
| `edificioDeAula`, `pisoDeAula`, `proyectar` | `edificioDeAula(edificios, aula, facultad?)`, `pisoDeAula(edificios, aula, facultad?)` |
| `SelectorExamen` | `valor`, `onCambiar(id)`, `examenes` (obligatorio, forma de la ruta 42) |
| `usarExamen(examenes)` | devuelve `[examen \| null, elegir(id)]`; lee y escribe `?examen=` |
| `BotonIncidencia` | `aula {id, nombre}` o `aulas [...]`, `por`, `hora`, `registrar({aula_id, tipo, estudiante, detalle})` → promesa, `alRegistrar`, `variante`, `className` |
| `ListaIncidencias`, `TIPOS_INCIDENCIA` | `items` (ruta 67), `atender(id)` → promesa (opcional; muestra «Atender»), `conAula` |
| `EsquemaApp` | solo `children`; usuario, rol y vistas salen de la sesión |
| `EstadoCarga` | `cargando`, `error`, `vacio`, `textoVacio='Sin resultados'`, `onReintentar`, `filas=5`, `className`, `children` |
| `LectorQr` | `activo`, `onLectura(texto)`, `onError(error)`, `className` |

Cliente, paginación del servidor, sondeo y sesión:

```jsx
import { api } from '../../api/cliente';
import { mensajeDe, erroresDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarSondeo from '../../api/usarSondeo';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { usarSesion, usarFacultades } from '../../sesion/SesionContexto';

const { usuario, puede, salir } = usarSesion();
const lista = usarPaginaServidor({ porPagina: 20, filtros: { facultad: null } });
const { datos, meta, cargando, error, recargar } = usarConsulta('/docentes', { parametros: lista.parametros });
const vivo = usarSondeo(`/puerta/aulas/${aulaId}`, { intervalo: 10000 }); // + vivo.sinConexion

<Buscador valor={lista.buscar} onCambiar={lista.ponerBuscar} />
<FiltroFacultad campo="clave" valor={lista.filtros.facultad} onCambiar={(v) => lista.ponerFiltro('facultad', v)} />
<EstadoCarga cargando={cargando} error={error} vacio={datos?.length === 0} onReintentar={recargar}>…</EstadoCarga>
<Paginacion {...lista.paginacion(meta)} unidad={['docente', 'docentes']} />

try { await api.post('/auxiliares', cuerpo); avisar('Auxiliar agregado'); recargar(); }
catch (e) { setErrores(erroresDe(e)); avisar(mensajeDe(e), 'error'); }
```

- `usarConsulta`: `datos` es `cuerpo.data` (o el cuerpo entero si no trae `data`); también devuelve `cuerpo`, `actualizando` y `fallos`. Un fallo al recargar conserva los datos.
- `usarPaginaServidor`: la búsqueda espera 300 ms y, como cualquier filtro, vuelve a la página 1. `lista.filtrosVigentes` es lo que viaja en `filtros` de un lote (ruta 55).
- Descargas: `const nombre = await descargar('/reportes/…', { parametros })` (de `api/descargar.js`).
- Pruebas de página: `vi.mock('../../api/cliente')`, luego `simularApi(api, { 'GET /examenes': {...}, 'GET /examenes/*/habilitaciones': (pedido) => ..., 'POST /x': errorHttp(422, {...}) })` y `renderConSesion(<Pagina />, { usuario: usuarioDePrueba('Docente'), ruta: '/examenes?examen=1' })` (todo en `resources/js/test/apoyo.jsx`).
- `PuntoControl` también se monta sin `EsquemaApp` ni cuenta desde `/acceso-aula`: el aula llega en `?aula=<id>` y el operador en `usarAccesoAula()` → `{aula_id, aula, examen_id, nombre, hasta}`.
- Pendiente para B6: una ruta para cerrar el acceso con código de aula, y que la ruta 62 devuelva `nombre` y `hasta`.

### F0-B

**Contrato de cuentas** (lo que usan B1, B2 y B4)

- `NuevaCuentaData(nombre, usuario, ?correo, rol)`: los cuatro son obligatorios al construir (ya no hay valores por defecto). `rol` es el **nombre** del rol; los tres válidos son las constantes `NuevaCuentaData::ROL_ADMINISTRADOR`, `ROL_DOCENTE`, `ROL_AUXILIAR` y la lista `NuevaCuentaData::ROLES` (sirve para el `Rule::in` de las peticiones). No se creó una enumeración de dominio: `Domain/Enums` no está en la lista de F0-B.
- `CuentaUsuarioGateway::crear(NuevaCuentaData, ?int $autorId): CuentaCreadaData` (antes devolvía `int`). `CuentaCreadaData`: `id`, `usuario` (ya en minúsculas), `contrasenaTemporal`, `enviadaA` (el correo o `null`), `caducaEn` (ISO 8601 con `-04:00`). Guarda `role_id`, `password_changed_at = null` y `password_temporal_expira_en`; asienta `usuario.crear` en la misma transacción; envía `CredencialesInicialesMail` después de confirmar, solo si hay correo.
- **El gateway no valida**: quien llama comprueba antes el formato del usuario, que usuario y correo estén libres (`usuarioRegistrado(string, ?int $exceptoId = null)`, `correoRegistrado(string, ?int $exceptoId = null)`; ambos comparan en minúsculas) y que el rol sea uno de los tres. Un usuario repetido acaba en `QueryException` por `uq_users_usuario`.
- **Los tres roles tienen que existir** (`RolePermissionSeeder`): `crear()` y `actualizar()` lanzan `RuntimeException` si no encuentran el rol. Toda prueba que cree cuentas por el gateway siembra antes `RolePermissionSeeder` (o usa `usuarioConRol()`, que lo hace). `BitacoraAtomicityTest` (de F0-A) se adaptó así.
- Método nuevo `emitirTemporal(int $usuarioId, ?int $autorId): ?CuentaCreadaData` para la ruta 11: temporal nueva, `password_changed_at = null`, vencimiento nuevo, levanta el bloqueo por intentos, asienta `usuario.restablecer_temporal` en la transacción y envía el correo si lo hay; `null` si la cuenta no existe. La acción `RestablecerContrasenaTemporal` de B1 es un pase directo.
- `actualizar(int $usuarioId, string $nombre, string $usuario, ?string $correo, string $rol): bool` (antes `nombre, correo`). Para que compilara se tocó lo mínimo `Actions/ActualizarCuentaUsuario` (de B1): ahora recibe `(usuarioId, nombre, usuario, ?correo, rol, ?administradorId)`. B1 la termina.
- **`ProponerUsuario` no se puede usar desde otro módulo** (6.4 dice que B2 la usa, pero las acciones son privadas según `deptrac.modules.php`). Se agregó el contrato público `Application/Contracts/ProponedorUsuario` (`proponer(string $nombreCompleto): string`), enlazado a la propia acción. `Academico` y `Examenes` inyectan `ProponedorUsuario`.
- `ProponerUsuario` (D-02): espera el nombre como en la oferta («Paterno Materno Nombres»). Con una sola palabra devuelve esa palabra; vacío, `usuario`. Quita tildes y todo lo que no sea `a-z0-9` (un apellido compuesto como «De La Fuente» da `fuente.de`: es una propuesta editable). Sufijo desde `2`; nunca pasa de 60 caracteres.
- `GeneradorContrasenaTemporal::generar()`: `Xx9-Xxx9-Xx9`, letras sin `i`, `l`, `o` (ni sus mayúsculas), dígitos 2–9. Cumple la regla de contraseña propia, pero la acción impide reutilizarla como propia.
- `config('auth_security.horas_temporal')` (72; variable `AUTH_HORAS_TEMPORAL`) se agregó a `config/auth_security.php`.
- `envios_correo` **no se llena todavía**: `RegistroCorreoGateway` es de B1. Los correos de credenciales y de restablecimiento salen sin dejar fila; B1 decide si los registra (habría que llamarlo desde `EloquentCuentaUsuarioGateway::enviar()` y `EloquentRestablecimientoGateway::solicitar()`).

**Acceso** (rutas 1–6; para F0-D)

- **Ruta 1.** El usuario se recorta y pasa a minúsculas antes de validar; `max:60`. Un correo en el campo `usuario` es un 422 de validación (`errors.usuario`), no un 401. 200 tal cual 3.2 (`rol` y `docente_id` pueden ser `null`; `permisos` en el orden de la matriz).
- 401 `{"message":"Usuario o contraseña incorrectos.","intentos_restantes":n}`: `n` llega solo si la cuenta existe y va de `max − 1` a `1`. **El intento que agota los intentos ya responde 423** `{"message":"Cuenta bloqueada.","minutos":15}` (no un 401 con `intentos_restantes: 0`). Durante el bloqueo `minutos` es lo que falta, redondeado hacia arriba. Cuenta con `is_active = false`: 423 `{"message":"Cuenta bloqueada."}` sin `minutos`, con cualquier contraseña.
- `TEMPORAL_VENCIDA` (401) se responde solo si la contraseña tecleada es la temporal correcta; no suma al contador de bloqueo. Con contraseña equivocada es un intento fallido normal. Una cuenta con `password_changed_at = null` y `password_temporal_expira_en = null` (creada fuera del gateway) no vence.
- **Ruta 2.** Sigue respondiendo **204 sin cuerpo**.
- **Ruta 3.** No lleva el middleware `auth`: el controlador responde él mismo `401 {"message":"No hay una sesión activa."}`. Si la cuenta fue bloqueada con la sesión abierta, esta ruta la cierra y responde 401. Una sesión de código de aula (sin usuario, B6) recibe 401 aquí: F0-D y B6 la distinguen por otro camino.
- **Ruta 4.** `contrasena_actual` es opcional en la validación; si la cuenta no está con temporal y falta o no coincide: 422 `{"message":"La contraseña actual no es correcta.","errors":{"contrasena_actual":[…]}}`. Si la nueva repite la que ya tiene (también la temporal): 422 con `errors.contrasena_nueva`. Con temporal, `contrasena_actual` se ignora aunque llegue. Mensajes de la regla en español: «…al menos 10 caracteres», «…una mayúscula y una minúscula», «…un número».
- **Ruta 5.** Responde igual si la cuenta no existe, está bloqueada o no tiene correo; en esos tres casos no envía nada ni asienta en bitácora.
- **Ruta 6.** 200 `{"message":"Contraseña restablecida. Ya puedes iniciar sesión."}`. La nueva sigue la misma regla (10, mayúscula, minúscula, número). Deja la cuenta con contraseña propia y sin bloqueo por intentos. `password_reset_tokens` sigue con clave `email`: el token se guarda bajo el correo de contacto de la cuenta, así que cambiar el correo invalida un enlace pendiente.
- Las respuestas nuevas del acceso salen de `ResultadoAccesoData` (DTO agregado, no listado en 6.3): `AuthenticationSecurityGateway::authenticate()` devuelve ese DTO en lugar de `?User`, y `AuthenticateUser` también. Se agregó `AuthenticationSecurityGateway::sesionDe(int): ?SesionData` (lee `docentes.user_id` por `DB::table`). Excepción de dominio nueva: `Domain/Exceptions/ContrasenaNoAceptadaException`.
- Bitácora: `sesion.intento_fallido` guarda ahora `registro_id` = la cuenta, cuando existe (`usuario_id` sigue nulo), y su descripción dice «Intento fallido con el usuario …».

**Roles y permisos**

- `RolePermissionSeeder`: tres roles de sistema y 14 permisos, insertados en el orden de la matriz de 3.1; `screen_name` es el texto de la columna «Pantalla». Es idempotente, pero **no borra** roles ni permisos del catálogo anterior: la base se rehace con `migrate:fresh`. Expone `RolePermissionSeeder::PERMISOS` (clave → pantalla) y `POR_ROL`.
- `EloquentRolGateway::permisosVigentesDe()` ordena ahora por `permissions.id` (el orden de la matriz) y no por `screen_name`: es el orden de `user.permisos`.
- `tests/Support/UsuarioConPermisos`: se agregó `usuarioConRol('Administrador'|'Docente'|'Auxiliar', array $atributos = [])`, que siembra los roles si faltan y devuelve una cuenta con el reparto real. `usuarioConPermisos([...])` sigue igual (rol «Rol de prueba»).

**Sin resolver, para el integrador**

- **El 401 de un invitado en las rutas con middleware `auth` dice `{"message":"Unauthenticated."}`**, no «No hay una sesión activa.» (3.1): lo responde Laravel antes de llegar a `ExigirPermiso`. Viene de antes del replanteo; se arregla en `bootstrap/app.php` (`withExceptions`), que no es de F0-B. El cliente debe guiarse por el código, no por el texto.
- `config/app.php` tiene `'timezone' => 'UTC'` fijo (ignora `APP_TIMEZONE`). Los instantes que devuelve F0-B (`caduca_en`) se convierten a `America/La_Paz` con una constante en `EloquentCuentaUsuarioGateway`; conviene una única fuente (`config/umss.php`, F0-C) para todos los módulos.
- No hay `lang/es`: los mensajes de validación sin texto propio salen en inglés. Las cuatro peticiones de F0-B traen sus mensajes en español.
- `DemoSeeder` (F0-C): las cuentas con contraseña fija se crean con el modelo (`forceFill` de `role_id` y `password_changed_at`), no con `CuentaUsuarioGateway::crear()`, que siempre genera una temporal aleatoria. La de primer ingreso (`corina.flores`, `Fa7-Kmq4-Ru9`) necesita además `password_temporal_expira_en` en el futuro (o nulo).

**Pruebas al cerrar F0-B**: `php artisan test` 163 (21 de arquitectura, 1 de ejemplo, 132 de Administración, 9 de `Academico` y `Examenes`); `composer test:postgresql` 37. Además de lo listado en 6.3 se creó `ProponerUsuarioTest` (11).

### B7

**Rutas 70–72** (`routes/monitoreo.php`, todas `GET`, `auth` + `permiso:seguimiento_vivo`). Forma de las respuestas como en 3.9, con estas precisiones:

- **Ruta 70.** No mira el rol sino los datos: trae los exámenes en los que la cuenta es docente del examen (con todas sus aulas) y aquellos en los que atiende un aula como encargada o de apoyo (solo con las suyas), de hoy y del primer día posterior que tenga alguno. Un docente puesto a cargo de un aula en un examen ajeno lo ve como un auxiliar. Orden: fecha, hora, id.
- **Ruta 71.** `cifras.faltan` = habilitados del examen sin ingreso (incluye a los que aún no tienen aula). `aulas[].ingresados` = ingresos **por la puerta de esa aula**; `asignados` = habilitados con esa aula. `cifras.incidencias_sin_atender` cuenta también las que no tienen aula. `encargado.es_docente` = la cuenta tiene fila en `docentes`. `compartida`: «Asignatura · Tipo» de cada examen del mismo día que se solapa en esa aula, separados por coma. Las aulas salen en el orden de `examen_aula.id`.
- **Ruta 72.** `lista` por defecto `faltan`. `faltan` = habilitados con esa aula sin ingreso en el examen (quien entró por otra aula como excepción deja de faltar y sale en `ingresaron` de la otra); se ordena por apellidos y nombres **sin tildes** (la «Ñ» va con la «N», no al final). Las filas de `faltan` no llevan `hora` ni `expulsado`; las de `ingresaron` llevan siempre los dos. `cifras` es del aula entera, sin el filtro de búsqueda; `meta.total` sí lo aplica. 422 con mensajes en español para `lista`, `buscar` (máx. 100), `pagina` y `por_pagina` (1–100).
- Códigos: 404 `Examen no encontrado.` / `Aula no encontrada.` (el aula no es del examen); 403 con `alcance: true` y mensaje `Este examen no es tuyo.` (71), `Esta aula no es tuya.` o `Solo puedes seguir tu aula el día del examen.` (72). La validación de la 72 corre antes que el alcance.
- La existencia del examen se comprueba con el propio `SeguimientoGateway` (no con `Examenes\…\ExamenGateway::existe`, que B4 está escribiendo). El alcance sí sale de `AlcanceExamenGateway`.
- `SeguimientoGateway` es de uso interno del módulo; B7 no expone contratos a otros módulos.

**Para el integrador**

- **La base de pruebas por paquete no funciona con `php artisan test`**: `tests/Integration/PostgreSQL/bootstrap.php` hace `putenv` de todo `.env.testing` y pisa el `DB_DATABASE` que se antepone a la orden, así que la corrida va a `control_ingreso_testing` (la compartida). Las dos primeras corridas de B7 fueron allí sin saberlo. Para usar de verdad la base propia sin tocar archivos ajenos: `DB_DATABASE=control_ingreso_testing_b7 vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Feature/Modules/Monitoreo`.
- **Zona horaria.** «Hoy», `abierto` y `hora_servidor` se calculan con `now()` en la zona de la aplicación, y la fecha y hora del examen se comparan tal cual están guardadas (así arma los exámenes `DatosDeExamen`). Con `config/app.php` en `'UTC'` fijo, en producción la ventana queda corrida cuatro horas y `hora_servidor` sale con `+00:00`: hay que dejar `'timezone' => env('APP_TIMEZONE', 'America/La_Paz')`. B7 no convierte por su cuenta para no desalinearse de los demás módulos.
- Los minutos de apertura salen de `config('umss.minutos_apertura', 60)`; `config/umss.php` todavía no existe y rige el 60.
- El texto del tipo de examen (`Primer parcial`…) está repetido en `EloquentSeguimientoGateway::TIPOS` porque `Examenes\Domain\Enums\TipoExamen` no es público entre módulos.
- `Monitoreo/Domain/Enums/EstadoAsistencia` quedó sin uso (la ruta 72 trabaja con dos listas, no con un estado por estudiante). No se borró porque 6.1.2 impide tocar `Domain/Enums`: se puede retirar.
- El 401 de un invitado sigue diciendo `Unauthenticated.` (lo ya anotado por F0-B); las pruebas afirman solo el código.

**Pruebas al cerrar B7**: 35 en `tests/Feature/Modules/Monitoreo` (`TableroTest` 16, `EnCursoListadoTest` 6, `AsistenciaDeAulaTest` 13); `EscenarioDeSeguimiento.php` es un rasgo de apoyo de esa carpeta.

### B5

**Contrato público** (lo usa `Ingreso`)

- `Habilitacion\Application\Contracts\HabilitacionGateway::condicionDe(int $examenId, int $estudianteId): ?HabilitacionData`. `null` = sin revisar (no hay fila). **No comprueba la inscripción**: solo lee `habilitaciones`.
- `Habilitacion\Application\DTOs\HabilitacionData`: `examenId`, `estudianteId`, `habilitado`, `?aulaId`, `?aulaNombre`, `?motivo`, `?registradaPorId`, `?registradaPor` (nombre de la cuenta), `?registradaEn` (ISO 8601 con `-04:00`; es `updated_at`).

**Rutas 54–56: lo que 3.7 no decía**

- **Existencia del examen**: `ExamenGateway::existe()` (B4) no existía al escribir B5; el 404 sale de `HabilitacionGateway::existeExamen()` (`DB::table('examenes')`). El integrador puede cambiarlo por el de B4. Orden de las respuestas: 422 de validación → 404 → 403 de alcance.
- **54.** El filtro `grupo` actúa sobre el grupo con el que el estudiante figura (el de menor código, comparado como texto: `10` va antes que `2`), así los conteos por grupo suman el total. `buscar` parte el texto en palabras y exige todas, en cualquier orden, sobre apellidos, nombres, código y documento (sin mayúsculas ni tildes; `%` y `_` son texto). `meta.grupos` va ordenado por código. `por_aula` va en el orden de `examen_aula.id` y cuenta las filas de `habilitaciones` con esa aula. Validación: `condicion` fuera de lista, `por_pagina` > 100, `pagina` < 1 o `grupo`/`aula` no numéricos dan 422.
- **55.** `afectados` cuenta los que **cambian**: al habilitar, solo quienes no estaban habilitados (a los ya habilitados no se les toca nada, tampoco `registrada_por`); al inhabilitar, todos los del lote (el motivo se reemplaza). Con `afectados = 0` responde 200 `0 habilitados.` y no asienta bitácora. Sin `todos`, `estudiantes` es obligatorio (mín. 1, máx. 5.000). Estudiantes no inscritos: 422 con `errors.estudiantes` y no se guarda nada. `ESTUDIANTE_CON_INGRESO` rechaza **el lote entero**. Con `todos`, `filtros` es opcional (sin filtros = toda la lista). Bitácora: `tabla_afectada = habilitaciones`, `registro_id` = el examen.
- **56.** «N estudiantes en M aulas»: M son las aulas que **recibieron** a alguien en ese reparto. Con `repartidos = 0` no asienta bitácora. Dos repartos simultáneos se ponen en fila (bloqueo de las filas de `examen_aula`). La regla está en `Domain/Rules/RepartoEquilibrado`.
- Excepciones de dominio además de las de 6.4: `ExamenAjenoException` (403 de alcance) y `EstudianteNoInscritoException` (422).

**Pruebas**

- `php artisan test` **no respeta** `DB_DATABASE` antepuesto: `tests/Integration/PostgreSQL/bootstrap.php` exporta lo de `.env.testing` y exige `control_ingreso_testing`. Para usar una base propia: `DB_DATABASE=control_ingreso_testing_b5 vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Feature/Modules/Habilitacion`.
- `usuarioConPermisos()` reutiliza un único «Rol de prueba» y le **suma** permisos: una segunda cuenta creada con otros permisos también tiene los de la primera. Para el 403 por permiso se usa una cuenta sin rol (`UserFactory::new()->createOne()`).

**Pruebas al cerrar B5**: 54 en `tests/Feature/Modules/Habilitacion` (`HabilitacionExamenTest` 38, `DistribucionExamenTest` 16).

### B8

**Rutas 73–80: lo que 3.10 no decía**

- **Filtros** (73–76): `periodo` es el `id` del período; `facultad` es la clave (`fcyt`) y también se admite la sigla (`FCyT`), se pasa a minúsculas antes de validar. Período, facultad o examen inexistentes son un 422 de validación (`errors.periodo|facultad|examen`), no un 404. Sin `periodo` **no se filtra por período** (rigen las fechas).
- **Facultad de un examen**: la de su primer grupo (`examen_grupo.id` más bajo). Un examen figura en una sola facultad, así `conteos` suma `todas`. `conteos` trae todas las facultades de la tabla, también las que quedan en 0.
- **`docente`** (73 y «Registrados»): `docentes.nombre_completo` de quien lo registró o, si la cuenta no es de un docente, `users.name`. `buscar` mira el nombre de la asignatura y ese nombre.
- **73 con `examen`**: `data`, `total` y `conteos` siguen a los filtros (el examen elegido no achica la tabla); `cifras` sale solo de ese examen e ignora los demás filtros. Lo mismo vale para el alcance de 74–76.
- **74–76.** Nombres de archivo con las bases de la maqueta: `horarios_ingreso_`, `registrados_examen_`, `intentos_no_correspondientes_` + `examen<id>` | sigla en minúsculas | `umss` + `_<hoy>`. Sin `formato` sale PDF. `buscar`, fechas y período no cambian el nombre (`umss`).
  - `horarios`: el tramo es la hora del día (`08:15`); con varios días en el alcance se suman. Primer y último ingreso llevan fecha y hora.
  - `registrados`: «Habilitados» cuenta por el aula **asignada**; «Registrados», por el aula **por la que entró** (`ingresos.aula_id`), así un cambio de aula suma donde ocurrió. Los habilitados sin repartir van en una fila «Sin aula»; un examen sin aulas ni habilitados figura con una fila en cero.
  - `intentos`: todas las incidencias con `origen = SISTEMA` (no se filtra por `tipo`). Las reportadas por una persona, aunque sean `INTENTO_NO_AUTORIZADO`, **no entran**: 3.10 fija la fuente en las del sistema. Sin estudiante identificado, la columna Estudiante lleva `estudiante_texto`. La ventana para las de `examen_id` nulo es `config('umss.minutos_apertura', 60)` minutos antes del inicio hasta el final, con los dos extremos incluidos.
- **77.** Sin `periodo`, los exámenes de los períodos vigentes (como la 42). `meta`: `total` (con el filtro de asignatura), `pagina`, `por_pagina`, `examenes` y `rendidos` (todos los del período, sin el filtro de asignatura) y `asignaturas: [{id, nombre, examenes}]` para las fichas. Sin rendir van nulos `presentes`, `ausentes` y `asistencia`; `habilitados` y `no_habilitados` se dan siempre (la maqueta los muestra). `asistencia` con cero habilitados es 0.
- **78–79.** `examen` es obligatorio (422); examen inexistente 404; ajeno 403 `{"message":"Este examen no es tuyo.","alcance":true}`; el día del examen ya se puede descargar. En `asistencia` la expulsión pesa más que la excepción, y los totales agregan «N expulsados». «Grupo» es el del estudiante entre los del examen (si está en dos, el de `id` más bajo).
- **80.** `formato` es opcional y solo admite `xlsx` (otro valor, 422). Grupo inexistente 404; ajeno o sin docente 403 `{"message":"Este grupo no es tuyo.","alcance":true}`. «Origen»: `Verificado` | `Docente`, como la ruta 34.

**Estructura**

- Además de lo listado en 6.4: `Application/Queries/ExportarTabla` y `Application/DTOs/ArchivoReporte` (tabla → archivo con nombre y tipo), el rasgo `Http/Controllers/EntregaArchivo` (la respuesta de descarga) y `Domain/Exceptions/{ReporteSinDatosException,ExamenSinRendirException,RecursoAjenoException}`. `TablaReporte::clave` lleva ahora el nombre del archivo sin extensión.
- No se usa `ExamenGateway::existe()` (B4): la existencia sale de la propia lectura. De otros módulos solo se inyectan `Examenes\…\AlcanceExamenGateway` y `Academico\…\AlcanceDocenteGateway` (fase 0).
- Repetidos por no ser públicos entre módulos: las etiquetas de `TipoExamen` (`EloquentReporteGateway::TIPOS`) y de `MedioIdentificacion` (`ArmarTabla::MEDIOS`), y el dominio `est.umss.edu` (`ArmarListaDeGrupo`).
- Las dos rutas de descarga por rol no pueden compartir `/api/reportes/{reporte}/exportar` (Laravel se queda con la última): son cinco rutas literales, `reportes.<clave>.exportar`.

**Para el integrador**

- **Zona horaria**: las horas se muestran como están guardadas y «hoy» es `now()` en la zona de la aplicación, igual que hizo B7. Con `config/app.php` fijo en `UTC`, las horas de ingreso de los archivos y el corte de `rendido`/`SIN_RENDIR` van cuatro horas adelantados hasta que la aplicación use `America/La_Paz`.
- `config/umss.php` no existe: rige el 60 por defecto de la apertura.
- El 401 de invitado sigue con el texto de Laravel; las pruebas afirman el código.

**Pruebas al cerrar B8**: 49 en `tests/Feature/Modules/Reportes` (`ReportesTest` 29, `ExportacionTest` 20); `EscenarioDeReportes` es un rasgo de apoyo de esa carpeta. Usan `usuarioConRol()` y no `usuarioConPermisos()`, que acumula permisos en un único rol.

### B2

**Rutas 18–33** (`routes/academico.php`), todas implementadas. Diferencias y precisiones respecto de 3.4:

- **Período por defecto.** Donde la ruta admite `?periodo=` (26, 27, 30, 33) y no llega, se toman **todos los vigentes**; si hoy no cae en ningún período, no se filtra por período. Lo mismo vale para las cifras de 23 y para el detalle 31 (que no recibe `periodo`). `periodo` es el `id`; `facultad` es la clave (`fcyt`, sin distinguir mayúsculas) y una clave que no existe es un 422.
- **Ruta 19.** `nota` = `«N carreras · M grupos»` (miles con punto); sin grupos, `«Sin oferta importada»`; si los grupos no tienen carrera en el plan, solo `«M grupos»`. Las carreras de un período se cuentan por el plan de estudios, separando las anuales (período `ANUAL`) de las semestrales.
- **Ruta 20.** `ventanas` siempre es un objeto (`{}` si no hay). `principal` es `null` si no hay vigentes.
- **Ruta 21.** 404 `{"message":"Período no encontrado."}`. Guarda `ajustado_por`.
- **Ruta 23.** `periodo` y `ventana` son `null` si no hay período vigente. `ventana` es la abierta hoy o, si no hay ninguna abierta, la próxima; su `nombre` sale de la clave (`primeros_parciales` → «Primeros parciales»). `docentes_sin_cuenta.de` cuenta todos los docentes. `importacion.fecha` es `fecha_fuente` y, si la fuente no la trae, el día de la corrida.
- **Ruta 24.** El `resumen` trae además `carreras_sin_codigo` y `grupos_eliminados` (los agrega `ResumenImportacionData` de F0-C). El 409 lleva también `message`. `fecha` es la de la fuente o, si falta, hoy.
- **Ruta 25.** `facultad` es opcional (sin ella, todas). Como `permiso:` acepta una sola clave, la ruta usa `Academico\Http\Middleware\ExigirAlgunPermiso` (`periodo_oferta` **o** `padron_estudiantes`); su 403 informa `permiso_requerido: "periodo_oferta"`.
- **Ruta 26.** `nivel` sale con mayúscula inicial (`Semestre 1`, `Primer año`) y el filtro `nivel` acepta cualquiera de las dos formas. Sin `carrera`, el nivel de una asignatura es el menor entre las carreras de la facultad (nulo si no está en ningún plan). `meta.niveles` son los del período, facultad y carrera elegidos: no se achican con `nivel` ni con `buscar`. `meta.grupos` sí sigue todos los filtros.
- **Ruta 27.** `buscar` **no filtra**: solo pone primero los grupos cuyo docente coincide. Orden natural por código (`2`, `5`, `10`). Los horarios incluyen las sesiones de auxiliatura, sin marcarlas.
- **Ruta 28.** `pisos` es `[]` cuando no se conocen. `meta.caja` es `null` si no hay edificios.
- **Ruta 30.** La lista son **todos** los docentes (también los que no tienen grupos en el período: salen con `facultades: []`); el período solo decide qué facultades y cuántos grupos se le cuentan. `meta.conteos` no depende de `facultad`, `sin_cuenta`, `varias_facultades` ni `buscar`; sí del período.
- **Ruta 31.** `usuario_sugerido` es la propuesta de `ProponedorUsuario` si el docente no tiene cuenta y, si la tiene, su usuario actual.
- **Ruta 32.** `usuario` y `correo` se pasan a minúsculas antes de validar. 422 con `errors.usuario` o `errors.correo` si ya son de otra cuenta. La cuenta, la unión con el docente y los asientos `usuario.crear` y `docente.activar_cuenta` van en una sola transacción (`EloquentCuentaDocenteGateway::activar`, que envuelve a `CuentaUsuarioGateway::crear`).
- **Ruta 33.** Una cuenta con el permiso pero que no es de un docente recibe 200 con la lista vacía (no un 403). `con_lista` = tiene al menos un inscrito. `meta.periodo` es el principal o, con `?periodo=`, el pedido.

**Además de lo listado en 6.4** se crearon: `Application/Contracts/{AjustePeriodoGateway,CuentaDocenteGateway}`, `Application/Queries/{ConsultarPeriodosVigentes,ResolverPeriodos}`, `Application/DTOs/{PaginaData,FiltroAsignaturasData,FiltroDocentesData}`, `Domain/Exceptions/DatoDeCuentaEnUsoException`, `Http/Middleware/ExigirAlgunPermiso`, el rasgo `Http/Requests/ValidaEnEspanol` (mensajes en español y página) y el rasgo `Infrastructure/Persistence/LeeFilas`. Las consultas devuelven arreglos con la forma de la respuesta (tipados con *array shapes*), no un DTO por fila.

**Avisos**

- **La base ordena por bytes** (`Álgebra` queda después de `Zoología`): todo `ORDER BY` sobre nombres usa `translate(lower(col), 'áéíóúüñ', 'aeiouun')`, la misma expresión de la búsqueda sin tildes. Vale para cualquier otro paquete que ordene por nombre.
- «Hoy» y los períodos vigentes se calculan con `now()` (UTC, como `Periodo::estado()` y `EloquentPeriodoGateway`), no con `config('umss.zona_horaria')`: entre las 20:00 y la medianoche de La Paz el día va adelantado. Conviene resolverlo en un solo sitio para todos los módulos.
- Las rutas 22 y 24 llaman directo a `DetectarPeriodos::execute()` e `ImportarOferta::execute(string $facultad, ?int $usuarioId)`. La comprobación de «importación en curso» la hace `ConsultaOfertaGateway::importacionEnCurso()` (misma regla que `ImportacionGateway::enCurso()` de F0-C).
- `tests/Support/UsuarioConPermisos::usuarioConPermisos()` reutiliza un único «Rol de prueba» y le va sumando permisos: dos llamadas en la misma prueba comparten permisos. Para un 403 después de otra cuenta hay que usar `usuarioConRol()`.
- PHPStan marca tres errores en `Infrastructure/Persistence/EloquentImportacionGateway.php` (de F0-C: `->first()` devuelve `object`); no se tocó.

**Pruebas al cerrar B2**: `tests/Feature/Modules/Academico` 71 (62 de B2 en `PeriodosTest`, `OfertaConsultaTest`, `AulasEdificiosTest`, `DocentesTest`, `GruposDelDocenteTest`, `ImportacionPorHttpTest`, más las 9 de F0-A). `ImportacionPorHttpTest` arma su propia carpeta de fuentes temporal y apunta `umss.ruta_genda` y `umss.ruta_fuentes` a ella.

### B3 · Padrón e inscripciones

**Rutas 34–41**: implementadas como en 3.5, con estas precisiones.

- **34.** `?facultad=` acepta la clave (`fcyt`) o la sigla; `?carrera=` es el **id** de la carrera (el de la ruta 25). `buscar` parte el texto en palabras y cada una tiene que estar en apellidos, nombres, código o documento (sirve «Aguilar Cossío, Mariana» y «mariana aguilar»). `meta.conteos` cuenta **todo el padrón** por facultad (no cambia con la búsqueda ni con los filtros) y trae todas las facultades, también las que están en cero.
- **35.** `inscripciones` cuenta las de períodos vigentes (igual que `grupos` en la 34); `cargados_por_docentes` son los estudiantes con `verificado = false`.
- **36 y 41.** El archivo se valida por extensión (`extensions:csv,xlsx`, máx. 10 MB). Archivo ilegible o sin filas: 422 con `errors.archivo` («No se pudo leer el archivo.» / «El archivo no tiene filas.») y no queda fila en `cargas_inscritos`. La 36 responde 409 `PERIODO_CERRADO` si no hay ningún período vigente; `cargas_inscritos.periodo_id` es el período principal y los grupos se buscan, por código de asignatura y de grupo, entre los de la facultad en **todos** los vigentes. Las dos asientan `inscritos.cargar` (tabla `cargas_inscritos`).
- **Motivos de rechazo agregados a los cinco de 3.5.1**: `El documento ya pertenece a otro estudiante` (un código nuevo con el documento de otro, del padrón o del mismo archivo: sin esto el único parcial `uq_estudiante_documento` acababa en 500) y `Documento de identidad no válido` (más de 30 caracteres). Nombres y apellidos se recortan a 100.
- **Conteo por fila**: `filas = nuevos + reutilizados + ya_inscritos + rechazados + conflictos`. En la carga de facultad, la primera fila de un estudiante desconocido es *nuevo* y sus demás filas, *reutilizado*; si una de ellas difiere de la primera, es conflicto contra el estudiante recién creado. Las filas en blanco no cuentan y no corren la numeración (`fila` es la línea del archivo, con el encabezado).
- **Conflictos**: el documento se compara sin espacios ni mayúsculas; nombres y apellidos, cada uno por separado, sin tildes, mayúsculas ni espacios dobles. Un estudiante guardado sin documento es conflicto de documento. Subir dos veces el mismo archivo no abre dos veces el mismo conflicto pendiente (misma persona, grupo y datos), aunque la respuesta lo vuelve a contar. La carrera (columna opcional) solo se asigna a estudiantes nuevos; un código de carrera desconocido se ignora.
- **37.** `?estado=pendiente|resuelto|todos` (por defecto `pendiente`), `por_pagina` 5 por defecto. Cada fila suma `estado`; `meta.conteos = {pendientes, resueltos}`. `guardado.por`: «Administración · 5 ago 2026» (fecha de alta, hora de La Paz) o, si lo trajo un docente, «Docente · Asignatura, grupo N» de su primera inscripción por esa vía.
- **38.** Además del 409 `CONFLICTO_YA_RESUELTO`, responde 409 `DOCUMENTO_EN_USO` si con `USAR_CARGA` el documento nuevo ya es de otro estudiante (el conflicto queda pendiente).
- **39.** No usa el alias `permiso` (admite una sola clave): lleva `Estudiantes/Http/Middleware/ExigirPermisoDeLista`, que deja pasar con `padron_estudiantes` o `mis_grupos` y responde el mismo 401/403 (`permiso_requerido: padron_estudiantes`). Sin `alcance`, la plantilla es la de grupo. Las columnas van con formato de texto hasta la fila 2000.
- **40 y 41.** Grupo inexistente: 404 «Grupo no encontrado.»; grupo ajeno o «Por designar»: 403 `{"message":"Este grupo no es tuyo.","alcance":true}`.

**Contrato público**: `Estudiantes\Application\Contracts\InscritosDeExamenGateway::estudiantesDe(array $grupoIds): array` devuelve `list<EstudianteData>` (`id`, `codigoUniversitario`, `documentoIdentidad`, `nombres`, `apellidos`, `verificado`, `nombreCompleto()`): los estudiantes **activos** inscritos en alguno de los grupos, una vez cada uno, por apellidos y nombres.

**Archivos fuera de la lista de 6.4**: contrato `GeneradorPlantilla`; DTO `SolicitudCargaData`, `RegistroCargaData`, `GrupoDeCargaData`, `FiltroListaData`, `PaginaData`, `ConflictoData`; excepciones `GrupoAjenoException`, `ListaIlegibleException`, `DocumentoEnUsoException`; el rasgo `Infrastructure/Persistence/ConsultasDePadron`. `LectorListaGateway::leer()` recibe también la extensión (el archivo subido es un temporal sin extensión).

**Para el integrador y otros paquetes**

- `tests/Support/UsuarioConPermisos::usuarioConPermisos()` reparte **un solo rol «Rol de prueba»**: dos cuentas creadas así en la misma prueba comparten todos los permisos, y un 403 por permiso entre ellas no se puede probar. Las pruebas de B3 usan `usuarioConRol('Administrador'|'Docente'|'Auxiliar')`.
- En un `whereRaw`, `like ? escape '\'` rompe los parámetros de PDO en PostgreSQL (`SQLSTATE[HY093]`): la búsqueda usa `escape '!'`.
- No existe `config/umss.php`: la zona `America/La_Paz` de `guardado.por` es una constante en `EloquentConflictoGateway`.
- `docs/ejemplos/padron_*.csv` siguen en el árbol; los `inscritos_*.csv` de 1.7 son de F0-C y no se crearon aquí.
- El 401 de invitado sigue diciendo «Unauthenticated.» (ver F0-B); las pruebas afirman solo el código.
- Pruebas: `tests/Feature/Modules/Estudiantes` 89 (`CargaInscritosTest` 33, `CargaDeFacultadTest` 16, `PadronTest` 11, `ConflictosTest` 14, `InscritosDeGrupoTest` 15).

### B1

**Rutas 8–17** (`routes/{usuarios,bitacora,respaldos}.php`): implementadas con la forma de 3.3. Diferencias y precisiones:

- **Ruta 8.** `meta.conteos` cuenta sobre **todas** las cuentas (no cambia al buscar ni al filtrar). Una cuenta sin rol sale con `rol: null`.
- **Rutas 9 y 10.** Mensajes de validación como los de la maqueta: `Obligatorio`, `Mínimo 3 caracteres`, `Solo letras, números, puntos y guiones`, `Ya existe` (usuario y correo), `Correo no válido`, `El rol no existe`. `usuario` y `correo` se pasan a minúsculas antes de validar. En la 10, `activo` es opcional: si no viaja, el estado no se toca. El bloqueo propio responde 422 `{"message":"No puedes bloquear tu propia cuenta."}` (sin `errors`) y no guarda el resto de la edición.
- **Bitácora de cuentas.** `usuario.actualizar` solo se asienta si cambió nombre, usuario, correo o rol; el bloqueo asienta `usuario.bloquear` / `usuario.desbloquear` (antes `usuario.desactivar` / `usuario.activar`). Los datos, el estado y sus asientos van en una transacción (contrato nuevo `Application/Contracts/Transaccion`).
- **Ruta 13.** `desde` y `hasta` son días de `America/La_Paz` (la columna está en UTC). Se quitó el filtro `usuario_id` del diseño anterior. `rol` es el rol actual de la cuenta (`null` en «Sistema»). `meta.operaciones` trae las claves que existen en la bitácora, ordenadas por su texto; `meta.fecha_minima` es la de toda la bitácora. `ConsultarBitacora` se mudó de `Application/Actions` a `Application/Queries`; el mapa es `ConsultarBitacora::TEXTOS` y hay que completarlo con las claves que cada paquete termine usando (hoy incluye las vistas en el árbol: `examen.modificar`, `examen.asignar_auxiliar`, `padron.resolver_conflicto`, `inscritos.cargar`, `qr.emitir`…).
- **Ruta 15.** Tipo `Puntual` si la pide una cuenta, `Programado` si la llama el planificador (`GenerarRespaldo::execute()` sin usuario). El archivo se nombra con la hora local. Si falla: 500 con la fila `fallido` (`archivo` y `tamano_bytes` nulos), asiento `respaldo.fallar`.
- **Rutas 16 y 17.** El respaldo se identifica por `id` (`/api/respaldos/{respaldo}/descargar`; la ruta anterior `/api/respaldos/{archivo}` ya no existe). Además de lo escrito: 409 `{"codigo":"RESPALDO_FALLIDO"}` al descargar o restaurar una fila fallida, y 404 si la fila existe pero el archivo ya no está en disco. Un respaldo incompatible sí se puede descargar.
- **Restauración.** `respaldos` apunta a `users`, así que el `TRUNCATE … CASCADE` del guion la vacía aunque esté en `EXCLUIDAS`. `RespaldoSqlEnDisco::restaurar()` guarda el historial antes, lo repone después (las filas cuyo autor no existe en el respaldo quedan sin autor) y ajusta la secuencia; quita el `BEGIN`/`COMMIT` del guion y lo corre en una transacción propia junto con el asiento. **Importar el `.sql` a mano desde el panel sí vacía el historial de respaldos** (los archivos siguen en disco): conviene decirlo en `docs/despliegue.md`.

**Contratos públicos nuevos** (`Administracion\Application\Contracts`)

- `RegistroCorreoGateway`: `registrar(RegistroCorreoData $datos): int` (deja la fila `PENDIENTE`), `marcarEnviado(int $envioId): void`, `marcarFallido(int $envioId, string $error): void`. `RegistroCorreoData(string $tipo, string $destinatario, ?int $usuarioId = null, ?int $examenId = null, ?int $estudianteId = null, ?int $solicitadoPor = null)`, con `TIPO_CREDENCIALES`, `TIPO_RESTABLECIMIENTO`, `TIPO_CODIGO_QR`.
- `ConsultaUsuariosGateway`: `listar(FiltroUsuariosData): PaginaUsuariosData`, `buscar(int $usuarioId): ?UsuarioConRolData`, `permisosPorPantalla(): list<PermisoDePantallaData>`.
- `Transaccion`: `ejecutar(Closure $operacion): mixed`.
- Cambiaron de firma: `RespaldoGateway` (`generar(string $tipo, ?int $usuarioId)`, `listar(?string $filtro, int $pagina, int $porPagina)`, `buscar(int)`, `contenido(int): ?string`, `esCompatible(string)`, `restaurar(int, ?int)`), `ConsultaBitacoraGateway::consultar()` (devuelve `PaginaBitacoraData`), `UsuarioConRolData` (`id, nombre, usuario, ?correo, activo, ?rol`), `RespaldoData`. De `RolGateway` se quitó `listarUsuarios()`.

**Archivo de F0-B tocado**: `Infrastructure/Persistence/EloquentCuentaUsuarioGateway.php`, solo el método privado `enviar()` y el constructor (inyecta `RegistroCorreoGateway`): el correo de credenciales queda en `envios_correo` (`PENDIENTE` → `ENVIADO`, o `FALLIDO` con el error antes de relanzar la excepción). No cambia ninguna firma pública. `EloquentRestablecimientoGateway` **no** se tocó: el correo del enlace de restablecimiento sigue sin dejar fila.

**Pendiente para el integrador**

- `routes/console.php` sigue con el respaldo a las 02:00 y la pantalla dice `Diario, 03:00` (`ListarRespaldos::PROGRAMACION`). Además el comando imprime «Respaldo …: n tablas» aunque la generación falle: debería mirar `$respaldo->esCorrecto()` y devolver un código de error.
- Registrar en `envios_correo` el correo de restablecimiento (`EloquentRestablecimientoGateway::solicitar()`), si se quiere.
- Un usuario repetido por carrera entre dos altas simultáneas acaba en 500 (`uq_users_usuario`): `Http` no puede capturar `QueryException`.
- `docs/despliegue.md`: la importación manual del guion vacía el historial; `database/postgresql/control_ingreso.sql` aparte.

**Pruebas al cerrar B1**: `tests/Feature/Modules/Administracion` 212 (104 de B1: `UsuariosListadoTest` 10, `CredencialesInicialesTest` 12, `AdministracionCuentasTest` 15, `RestablecerTemporalTest` 7, `MatrizPermisosTest` 6, `BitacoraConsultaControllerTest` 15, `BitacoraSoloLecturaTest` 5, `BitacoraRegistroAutomaticoTest` 3, `RespaldoTest` 15, `RespaldoHistorialTest` 12, `RegistroCorreoTest` 4).

### B4 · Exámenes

**Rutas 42–53** (`routes/examenes.php`, todas con `auth` + `permiso:examenes`). Diferencias y precisiones respecto de 3.6:

- **42.** `?periodo=` admite el **id** del período (como el resto de la API) o su código (`2/2026`); otro formato es 422. Un período que no existe devuelve lista vacía y `meta.periodo: null`. Sin parámetro, `meta.periodo` es el código de `PeriodoGateway::principal()`. Las cifras (`habilitados`, `no_habilitados`, `qr_emitidos`) cuentan solo a quienes siguen inscritos en algún grupo del examen; `qr_emitidos` cuenta **habilitados con código**. `habilitacion` no es `ok` si nadie quedó habilitado, y `qr` no es `ok` sin habilitación `ok`. `accion.paso` es `2` solo con `elegir_aulas` (el índice del paso «Aulas» de la maqueta); en las demás, `null`.
- **44, 45, 46.** `registrado_por` y los nombres de encargado y apoyo usan `docentes.nombre_completo` si la cuenta es de un docente; si no, `users.name`. `ubicacion` es `null` en un aula sin edificio. `es_docente` / auxiliar se deciden por el rol **o** por tener fila en `docentes` / `auxiliares` (así lo arma `tests/Support/DatosDeExamen::auxiliar()`, que no da rol).
- **45, 46.** `aulas` puede faltar o ir vacía. `encargado_id` ausente o nulo = quien registra, al que no se le exige rol. Las reglas que dependen de lo registrado responden 422 con la misma forma que la validación (`message` + `errors.<campo>`): `asignatura_id` (sin grupo propio en un período vigente), `grupos` (de otra asignatura, de períodos distintos, o «El grupo 3 ya tiene un primer parcial.»), `fecha` (fuera del período), `aulas.N.encargado_id`, `aulas.N.apoyo`. Al **modificar** sin cambiar de asignatura no se vuelve a exigir el grupo propio, y los grupos del período del examen valen aunque ya haya cerrado.
- **46.** Con ingresos, cualquier cambio de asignatura, tipo, fecha, hora, duración, conjunto de grupos o conjunto de aulas da 409 `EXAMEN_CON_INGRESOS`; `normas`, encargados y apoyo sí se guardan. El orden de las comprobaciones es 422 de forma → 404 → 403 de alcance → 409 → 422 de reglas. `examen.asignar_auxiliar` se asienta al registrar solo si alguien distinto de quien registra atiende un aula, y al modificar si cambia un encargado, un apoyo o se agrega o quita un aula.
- **47.** También responde 409 `EXAMEN_CON_INGRESOS` si el examen tiene **incidencias** (`incidencias.examen_id` es `restrict`: son historia). Asienta `examen.eliminar`.
- **48.** Cada grupo trae además `"periodo":"2/2026"` (puede haber varios períodos vigentes). `asignatura_id` inexistente es 422.
- **49.** Todos los parámetros son opcionales: `grupos[]` da `sugeridas`; `fecha` + `hora_inicio` + `duracion_minutos` (van juntos) dan `compartidas`. `compartidas` es siempre un objeto (`{}` si no hay). No se considera el solape de un examen que cruza la medianoche.
- **50.** `buscar` de menos de 3 caracteres **se ignora** (devuelve los propios, no un 422); busca en nombre, usuario y código universitario; más de 60 caracteres es 422. Sin `buscar` no hay tope de filas. Las cuentas bloqueadas no salen.
- **51.** `nombre` se espera «Paterno Materno Nombres» (así lo lee `ProponedorUsuario`). 422 en `codigo_universitario` si ya hay una cuenta (no auxiliar) con el correo `<código>@est.umss.edu`. La cuenta la crea `CuentaUsuarioGateway::crear()` (asienta `usuario.crear` y envía el correo) y después se guarda la fila de `auxiliares` con `auxiliar.registrar`: son **dos transacciones**; si la segunda fallara quedaría una cuenta de rol Auxiliar sin fila en `auxiliares`, que igual aparece al buscar.
- **52, 53.** 404 `{"message":"Examen o aula no encontrados."}` si el aula no es del examen. Asientan `codigo_aula.generar` y `codigo_aula.regenerar` (tabla `examen_aula`); B1 no las tiene en su mapa de textos de la bitácora.

**Para B6: cómo cierra accesos «Regenerar».** No se borra nada de `accesos_aula`. Al regenerar, `examen_aula.codigo_generado_en` pasa a `now()`, o a **un segundo después del último acceso** de esa aula si ese acceso es del mismo segundo (las marcas son de un segundo: sin eso, un acceso abierto en el segundo de la regeneración seguiría valiendo). La regla sigue siendo la de F0-A: el acceso vale si `accesos_aula.creado_en >= examen_aula.codigo_generado_en`. Consecuencia: un acceso creado con el código nuevo dentro de ese mismo segundo nace cerrado; quien abre el acceso puede guardar `creado_en = max(now(), codigo_generado_en)`.

**Contratos públicos** (`Examenes/Application/Contracts`): `ExamenGateway` (`existe(int): bool`, `tieneIngresos(int): bool`, `tieneIncidencias(int): bool`, `actual(int): ?ExamenActualData`, `registrar`, `actualizar`, `eliminar`), `ConsultaExamenGateway` (`listar`, `detalle(int $examenId, int $usuarioId): ?ExamenDetalleData`, `gruposDeAsignatura`, `aulasSugeridas`, `aulasCompartidas(string $fecha, string $horaInicio, int $duracionMinutos, ?int $exceptoExamenId): array<int, list<string>>`, `personas`, `periodo`, `hoy`, `horaServidor`), `AuxiliarGateway`, `CodigoAulaGateway`, además del `AlcanceExamenGateway` de F0-A, que no cambió.

**Sin resolver, para el integrador**

- **Zona horaria.** `es_hoy`, `rendido` y `meta.hoy` comparan la fecha y la hora del examen con `now()` en la zona de la aplicación (`UTC`, fija en `config/app.php`), igual que `tests/Support/DatosDeExamen`; `hora_servidor` y `generado_en` salen en `America/La_Paz` (constante repetida en dos gateways del módulo). Con la aplicación en UTC, «hoy» cambia a las 20:00 de Bolivia: hace falta fijar `app.timezone` en `America/La_Paz` o una fuente única (`config/umss.php`).
- `usuarioConPermisos()` reutiliza el rol «Rol de prueba»: dos llamadas en una misma prueba **suman** permisos al mismo rol. Para probar un 403 por permiso hay que usar `usuarioConRol()`.
- El dominio `est.umss.edu` está repetido en `Examenes/Application/Actions/RegistrarAuxiliar` (no hay `config/umss.php` ni se puede importar `Estudiante::DOMINIO_CORREO`).
- Las escrituras sobre `habilitaciones` y `codigos_qr` al quitar un aula o un grupo se hacen con `DB::table` desde `EloquentExamenGateway` (son tablas de `Habilitacion` e `Ingreso`).
- `ExamenConIngresosException` conserva su nombre aunque también cubre el examen con incidencias al eliminar.

**Pruebas al cerrar B4**: `tests/Feature/Modules/Examenes` 73 (4 de F0-A sobre el alcance + 69: `RegistroExamenTest` 22, `ListadoExamenesTest` 10, `AmbientesDelExamenTest` 13, `OpcionesDeGruposTest` 4, `AuxiliaresTest` 12, `CodigoDeAulaTest` 8).

### B6 · Ingreso

**Rutas agregadas** (pendiente que dejó F0-D)

- `DELETE /api/auth/codigo-aula` (`auth.codigo-aula.cerrar`, pública, sin cuerpo): olvida el acceso con código de aula de esta sesión y responde **204**; tampoco falla si no había ninguno. No toca `accesos_aula` (no tiene columna de cierre). **Falta en el cliente**: «Salir» de `/acceso-aula` sigue llamando a `POST /api/auth/logout`, que para una sesión sin cuenta responde 401; debe llamar a esta ruta (F0-D / W4 / I1).
- **Ruta 62**: además de `data` y `hoy` devuelve `acceso`: `null` con una cuenta, o `{"aula_id","aula","examen_id","nombre","hasta"}` (la misma forma de la ruta 7) cuando la sesión es de código de aula. Con eso `/acceso-aula` recupera `nombre` y `hasta` al recargar.

**Diferencias con la sección 3**

- **Ruta 7.** El 422 trae también `errors.codigo` (`{"message":"Código no válido.","errors":{"codigo":["Código no válido."]}}`); no distingue código inexistente de examen fuera de su ventana. El límite es `throttle:3,5,codigo-aula` y **cuenta todas las peticiones**, también las correctas: la cuarta en cinco minutos desde una IP da 429. Asienta `acceso_aula.abrir` (sin usuario). Regenera el id de sesión y guarda `acceso_aula_id`.
- **`puesto`** (`ExigirPuesto`): con cuenta exige `punto_control`; `puesto:seguimiento_vivo` (ruta 68) admite además ese permiso. Sin cuenta busca el acceso de la sesión; si el examen terminó o el código se regeneró (`accesos_aula.creado_en < examen_aula.codigo_generado_en`), olvida el acceso y responde 401 `No hay una sesión activa.`. Con acceso, una ruta con `{aula}` distinta de la suya da 403 `{"alcance":true}`. Las rutas de la puerta **no llevan `auth`**: su 401 sí dice «No hay una sesión activa.». Deja al operador (`OperadorData`) en `$request->attributes->get('operador')`.
- **Alcance de la puerta (63–65).** La puerta es del aula: quien es docente de **alguno** de los exámenes de hoy en esa aula, o la atiende en alguno, opera la puerta entera (ve y admite también a los del otro examen del aula compartida). Sin ningún examen de hoy que atienda: 403 `{"message":"No atiendes esta aula.","alcance":true}`. El auxiliar deja de alcanzarla al día siguiente.
- **Ruta 57.** `meta` suma `grupos` (`[{id,codigo}]`) y `aulas` (`[{aula_id,nombre}]`) del examen, para los dos selectores de la pantalla. `estado`: `sin_emitir` si falta algún código (o no hay habilitados), `emitido` si todos lo tienen, `enviado` si además todos salieron. `emitidos` y `enviados` cuentan solo códigos de quienes siguen habilitados. `grupo` y `aula` filtran por **id**.
- **Ruta 58.** `emitidos` es lo emitido **en esta llamada** y se agrega `total` (códigos del examen). Mensajes: `N códigos emitidos.`, `1 código emitido.`, y `Los códigos ya estaban emitidos.` con `emitidos: 0`. Solo asienta `qr.emitir` si emitió alguno.
- **Ruta 59.** 404 si el estudiante no está **habilitado** en el examen. `facultad` es la sigla de la facultad del primer grupo del examen.
- **Ruta 60.** Responde además `pendientes`. Con cola sincrónica (`QUEUE_CONNECTION=sync`) cada petición envía como mucho **100**, primero a quienes aún no lo recibieron, y `pendientes` dice cuántos quedan sin recibirlo; con cola real van todos y `pendientes` es 0. Volver a enviar reenvía (no se salta a quien ya lo recibió). `SIN_EMITIR` si nadie del filtro tiene código. Un correo que falla dentro de la petición no la rompe: su fila queda `FALLIDO` con el error.
- **Ruta 61.** Solo salen los que tienen código emitido; con filtros que no dejan a nadie, la hoja dice «Sin credenciales». 403 y 404 se responden en JSON.
- **Ruta 63.** Sin `ingresos` devuelve los 5 recientes; `buscar` solo se aplica con `ingresos=todos`. `total_ingresos` no depende de la búsqueda.
- **Ruta 64.** Reglas que 3.8 no fijaba:
  - Habilitado **sin aula repartida**: `otra_aula` con `aula_correcta: null` y `dato: "Sin aula asignada"` (para admitirlo, la excepción «Cambio de aula»).
  - `no_habilitado` sin revisar: `dato: "Sin revisar"`, `pie: null`. El `pie` usa la fecha de la última modificación de la habilitación.
  - Token inexistente: `dato: "Código QR no válido"`, causa `CODIGO_INVALIDO`. Token de un examen que no está abierto en el aula: `no_corresponde`, causa `NO_INSCRITO`, aunque el estudiante tenga otro examen en el aula (con QR manda el token).
  - Estudiante en dos exámenes del aula (por código o documento): se elige, por este orden, el que puede admitirlo (sin ingreso, habilitado, con esta aula); luego habilitado en otra aula; luego no habilitado; al final el que ya tiene ingreso.
  - Los intentos del sistema son incidencias `INTENTO_NO_AUTORIZADO`/`SISTEMA`; `NO_INSCRITO` y `CODIGO_INVALIDO` quedan con `examen_id` nulo, `NO_HABILITADO` con el examen. No dejan fila de bitácora. `otra_aula`, `ya_ingreso` y `expulsado` no asientan nada.
  - `ingreso`, `aula_correcta` y `examen_correspondiente` solo vienen en su veredicto.
- **Ruta 65.** Sin `examen_id` (tipos que no admiten) la incidencia queda en el primer examen abierto del aula. `examen_id` que no está abierto en esa aula: 422 `errors.examen_id`. `CAMBIO_AULA` que no puede admitir: 409 con `codigo` `NO_INSCRITO` | `NO_HABILITADO` | `YA_INGRESO` | `EXPULSADO`, y no queda ni incidencia ni ingreso. Aula sin examen abierto: 409 `AULA_SIN_EXAMEN`. El ingreso por excepción se guarda con medio `CODIGO` y la incidencia apunta a él (`ingreso_id`).
- **Ruta 66.** 201 trae además `incidencia_id`. Puede expulsar el docente del examen o quien atiende **el aula por la que entró** ese ingreso (el día del examen); con código de aula, solo ingresos de su aula.
- **Ruta 67.** Cada fila trae además `aula_id`. Los conteos de `meta` respetan `aula` e ignoran `estado`. Quien no es docente del examen: sin `aula` 422 `errors.aula`, con un aula que no atiende 403. Con código de aula no se puede listar (lleva `auth`).
- **Ruta 68.** Fuera del día del examen: 409 `{"codigo":"FUERA_DE_FECHA"}`. `aula_id` que no es del examen: 422. `rol` de una incidencia reportada con código de aula: `Código de aula`, y `por` es el nombre que dio al entrar.
- **Ruta 69.** 404 también para una incidencia del sistema; 409 `{"codigo":"YA_ATENDIDA"}`. Asienta `incidencia.atender`.

**Contratos y piezas**

- Además de los de 6.4: `Application/Contracts/CondicionHabilitacionGateway` (lee `habilitaciones` con `DB::table`; **se puede sustituir** por `Habilitacion\Application\Contracts\HabilitacionGateway::condicionDe()`, que se escribía a la vez) y `Transaccion` (para que la excepción de cambio de aula asiente ingreso e incidencia juntos). `Application/Authorization/AlcancePuesto` reúne las reglas de alcance sobre `Examenes\…\AlcanceExamenGateway`.
- `PuertaGateway` lee aulas, exámenes, inscripciones y estudiantes por nombre de tabla. No se usó `Examenes\…\ExamenGateway::existe()` (se escribía a la vez): `PuertaGateway::examen()` cumple ese papel.
- `IngresoGateway::registrar()` devuelve `null` si choca con `uq_ingreso_examen_estudiante`: la carrera entre dos puestos acaba en un ingreso y un `ya_ingreso` (`IngresoUnicoTest`). Lo mismo `IncidenciaGateway::registrar()` con `uq_expulsion_por_ingreso` → `YA_EXPULSADO`.
- Ningún contrato de Ingreso se pensó para otros módulos; Monitoreo y Reportes leen `ingresos` e `incidencias` por tabla.
- Se eliminaron `Domain/Exceptions/{CodigoDeOtroExamenException,ExpulsionNoPermitidaException}`; `IngresoNoPermitidoException` ahora lleva `codigo`.

**Trabajado alrededor, para el integrador**

- **Zona horaria.** Todo el módulo compara y muestra en la zona de la aplicación (`now()`), la misma en que se guardan `examenes.fecha` y `hora_inicio`; los instantes salen en ISO 8601 con ese desfase. Con `config/app.php` fijo en `UTC` la ventana de la puerta se abre cuatro horas antes de lo que marca el reloj de Cochabamba: hay que poner `America/La_Paz` (las pruebas de Ingreso no dependen de la zona).
- `config/umss.php` no existe: `config('umss.minutos_apertura', 60)` cae en 60 y el dominio `est.umss.edu` está repetido como constante en `EloquentCorreoQrGateway`.
- Las etiquetas de `TipoExamen` están repetidas en `EloquentPuertaGateway::TIPOS` (no se puede importar la enumeración de `Examenes`).
- `envios_correo` se escribe con `DB::table` y los valores `CODIGO_QR`, `PENDIENTE`, `ENVIADO`, `FALLIDO` como texto: no se usó `RegistroCorreoGateway` de B1 (se escribía a la vez) ni las enumeraciones de `Administracion`.
- `CodigoQrMail` sobrescribe `send()` para marcar `ENVIADO` y `codigos_qr.enviado_en`, y `failed()` para `FALLIDO`. La credencial (con el token) viaja serializada en el trabajo de la cola.
- Con cola sincrónica, si los correos fallan siempre, `pendientes` no baja: el cliente no debe repetir la ruta 60 en bucle sin comprobar que avanza.
- El 401 de las rutas con `auth` (57–61, 67, 69) sigue siendo el `Unauthenticated.` de Laravel (ver F0-B).

**Pruebas al cerrar B6**: `tests/Feature/Modules/Ingreso` 127 (`VerificacionEnPuertaTest` 25, `PuertaTest` 16, `IngresoUnicoTest` 7, `ExcepcionEnPuertaTest` 11, `ExpulsionTest` 9, `IncidenciasTest` 19, `AccesoConCodigoDeAulaTest` 13, `CodigosQrTest` 18, `EnvioDeCodigosQrTest` 9). El apoyo común es `tests/Feature/Modules/Ingreso/EscenarioDePuerta.php` (hora fija `2026-10-12 08:20:00`).

### F0-C

**Importadores**

- Los seis JSON están en `database/umss/genda/`. `config/umss.php` trae `ruta_genda`, `ruta_fuentes`, `facultades` (las cuatro claves), **`zona_horaria` (`America/La_Paz`)**, `dominio_correo_estudiantes` y `minutos_apertura` (60).
- **Salen 847 docentes, no 849.** La oferta trae 852 valores distintos de `PROFESSOR`; se descartan cuatro (cadena vacía, `VI` y los dos «por designar») y la normalización sin tildes funde `ZAMBRANA TRIVEÑO JOSE EMILIO` con `ZAMBRANA TRIVENO JOSE EMILIO` (la misma persona escrita distinto en dos facultades). Con las reglas de 4.2 no hay forma de llegar a 849. En más de una facultad quedan 30, no 29, por la misma fusión. Aulas: 281 nombres (237 ubicadas, 44 sin edificio), no 282 y 49.
- Grupos por facultad: FCyT 1.100, FCE 1.007, FHCE 679, FACH 383 (3.169). FACH reparte los suyos entre `0/2026` (carreras anuales) y `2/2026`.
- No se tocó `PeriodoGateway` (B2 tampoco): la escritura de períodos va en un contrato nuevo, `DeteccionPeriodoGateway` (`registrar`, `asegurar`, `todos`). También son nuevos, fuera de la lista de 6.3: `DTOs/{CalendarioData,DeteccionPeriodosData}`, `Domain/Rules/{NombresDeOferta,CodigoPeriodo}`, `Infrastructure/Import/LectorJson`.
- Firmas para B2: `DetectarPeriodos::execute(): DeteccionPeriodosData` (`creados`, `actualizados`, `periodos`); `ImportarOferta::execute(string $facultad, ?int $usuarioId = null): ResumenImportacionData` (`importada`, `error`, `sigla`, `periodoCodigo`, `fechaFuente`, contadores, `importacionId`, `resumen()`); lanza `FuenteNoDisponibleException` solo si la clave no está en `facultades`, cualquier otro fallo vuelve como `importada = false` con la fila `FALLO`. `ImportacionGateway::enCurso(int $facultadId, int $minutos = 5): bool` sirve para el 409 de la ruta 24. `ImportarUbicaciones::execute(string $facultad): array{edificios, aulas}`.
- El resumen guardado tiene dos claves más que las siete de 4.3: `carreras_sin_codigo` y `grupos_eliminados`.
- La fila `IMPORTANDO` se confirma **antes** de abrir la transacción (si no, otra petición no la vería); todo lo demás va dentro y, si falla, se revierte y la fila pasa a `FALLO`.
- Nombres: docentes con mayúscula inicial en cada palabra; carreras, asignaturas y edificios además con los enlaces en minúscula y los números romanos en mayúscula («Introduccion a la Programacion», «Calculo II»). **La fuente no trae tildes**: los nombres quedan sin ellas. Las búsquedas tienen que ignorar tildes.
- Un docente existente no se modifica al reimportar (ni nombre ni cuenta). Los horarios de `time` vuelven de PostgreSQL como `08:15:00`.
- `routes/console.php`: `periodos:detectar`, `oferta:importar {facultad?}`, respaldo a las 03:00 y `queue:work --stop-when-empty --tries=3` cada minuto.

**Zona horaria (para el integrador y B4, B6, B7)**

- `DemoSeeder` guarda `examenes.fecha` y `hora_inicio` en **hora local** (`config('umss.zona_horaria')`), como las teclea el docente, y los instantes (`registrado_en`, `ocurrida_en`…) en UTC. **`tests/Support/DatosDeExamen::examen()` usa `now()` (UTC) para la fecha y la hora**: si un módulo compara contra `now()` sin convertir, sus pruebas pasan y el examen de hoy de la demostración le aparece terminado (cuatro horas de diferencia). Hay que unificar: comparar siempre contra `now(config('umss.zona_horaria'))` y corregir el apoyo de pruebas. `Periodo::estado()` usa `Carbon::today()` en UTC: entre las 20:00 y la medianoche local ya calcula con el día siguiente.

**Datos de demostración**

- `composer datos:demo` tarda unos 6 s; `composer datos:volumen`, unos 10 s. Los dos se pueden repetir sin duplicar: solo crecen `importaciones_oferta` y la bitácora (cada corrida del importador deja su fila). Al repetir, los exámenes se vuelven a fechar pero los ingresos ya asentados conservan su hora (son inmutables).
- Cuentas además de las de 4.5 (todas `Docente12345`): `carla.salazar` (a cargo de la 691A hoy), `erika.rodriguez` (registró el parcial conjunto de Taller) y `americo.fiorilo` (el parcial de Cálculo I que comparte la 691A). Código de acceso del aula 624: `482913`.
- **Blanco Coca Leticia tiene en la oferta real 5 asignaturas y 6 grupos**, no 2. No se alteró la oferta: sus listas y exámenes de ejemplo están en dos (Introducción a la Programación y Taller de Ingeniería de Software) y los otros cuatro grupos quedan sin lista. Lo mismo `carga.alta` (Soruco Maita Jose Antonio, 9 asignaturas): los 18 exámenes están en seis.
- `leticia.blanco` es la única cuenta con correo (`leticia.blanco@fcyt.umss.edu.bo`), para probar el restablecimiento.
- `docs/ejemplos/`: `inscritos_grupo_ejemplo.csv`, `inscritos_grupo_con_errores.csv`, `inscripciones_facultad_ejemplo.csv` (formato de D-09). `README.md` y `docs/guia/{README,hu04}.md` siguen nombrando los `padron_*.csv` eliminados (I1).

**Pruebas**: 29 (`ImportarOfertaTest` 15, `ImportarUbicacionesTest` 3, `DetectarPeriodosTest` 4, `ComandosTest` 5, `DatosDemoTest` 2). `tests/Fixtures/genda/` trae las cuatro facultades en pequeño (`fcyt.json` es la de 6.1), `locations.json`, `pensum.json` y un `fuentes.json`; se usan con `config(['umss.ruta_genda' => base_path('tests/Fixtures/genda'), 'umss.ruta_fuentes' => base_path('tests/Fixtures/genda/fuentes.json')])` y sembrando `FacultadSeeder`.

### P2 · Estudiantes (puesta al día con el repositorio del equipo)

Tres mejoras traídas del módulo `Estudiantes` del equipo, sin migraciones. Las rutas 34 a 41 siguen como en «B3 · Padrón e inscripciones», salvo lo que se dice aquí.

- **Ficha** `GET /api/estudiantes/{estudiante}` (`estudiantes.show`, permiso `padron_estudiantes`). 200 `{"data":{"id":9,"codigo":"202104821","nombre":"Aguilar Cossío, Mariana","nombres":"Mariana","apellidos":"Aguilar Cossío","documento":"7361424","correo":"202104821@est.umss.edu","facultad":"FCyT","carrera":"Ing. Informática","origen":"Verificado","materias":[{"asignatura":{"codigo":"2010010","nombre":"Introducción a la Programación"},"grupo":"2","grupo_id":14,"docente":"Blanco Coca Leticia","periodo":"2/2026","via":"Administración"}]}}`. `documento`, `facultad` (sigla) y `carrera` pueden ser nulos; `origen` es `Verificado` \| `Docente`; `materias[].via` es `Administración` \| `Docente`; `docente` nulo = por designar. Solo inscripciones de períodos vigentes, por asignatura y grupo; `periodo` es el código del período. El correo es el código con `config('umss.dominio_correo_estudiantes')`. 404 `{"message":"Estudiante no encontrado."}`. Efecto lateral: `PUT` y `DELETE` sobre esa ruta responden ahora 405 (antes 404).
- **Archivo rechazado entero** (rutas 36 y 41): 422 `{"message":"…","codigo":"…","orden_esperado":[…],"errors":{"archivo":["…"]}}` y, solo con `FALTAN_COLUMNAS`, `"columnas":[…]` (las que faltan). No se guarda nada: ni fila en `cargas_inscritos` ni asiento en bitácora. `orden_esperado` va en los cuatro códigos: `codigo_universitario, documento_identidad, nombres, apellidos` en la carga de grupo, y esas más `codigo_asignatura, grupo` en la de facultad (`carrera` es opcional y no figura).

| `codigo` | `message` | Cuándo |
|---|---|---|
| `ARCHIVO_NO_CORRESPONDE` | «El archivo no es una lista de inscritos.» | No es lo que dice su extensión (imagen o documento renombrado, `.xlsx` dañado, `.xlsx` renombrado a `.csv`, UTF-16) o, sin encabezado, ninguna celda parece un código universitario |
| `ARCHIVO_VACIO` | «El archivo no tiene filas.» | Sin filas con datos, o solo el encabezado |
| `FALTAN_COLUMNAS` | «Faltan columnas: nombres, apellidos.» | Falta una obligatoria en el encabezado o, sin encabezado, el archivo es más angosto |
| `ORDEN_DE_COLUMNAS` | «Las columnas están en otro orden. Orden esperado: ….» | Están todas pero alguna fuera de su posición o, sin encabezado, el código no va en la primera columna |

  El 422 del formulario (sin archivo, extensión, tamaño, facultad) sigue siendo el de Laravel, **sin `codigo`**. La revisión ocurre dentro de `CargarInscritos`, después del alcance: grupo inexistente 404, grupo ajeno 403 y `PERIODO_CERRADO` 409 salen antes de mirar el archivo. El encabezado se reconoce con tildes, mayúsculas y nombres cortos (`codigo`, `ci`, `documento`, `nombre`, `apellido`, `materia`…); las columnas de más se ignoran; un `.csv` en ISO-8859-1 se lee con sus tildes. Las filas en blanco se saltan sin correr la numeración. Cambia respecto de B3: el mensaje del archivo ilegible ya no es «No se pudo leer el archivo.», y `ListaIlegibleException` se sustituye por `Domain/Exceptions/ArchivoRechazadoException`; la regla está en `Domain/Rules/FormatoDeLista` y el tipo real del archivo lo comprueba `Infrastructure/Import/InspectorDeArchivo`.
- **Descarga** `GET /api/docente/grupos/{grupo}/inscritos/descarga` (`docente.grupos.inscritos.descarga`, permiso `mis_grupos` + grupo propio). 200 con el `.xlsx` (`Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `Content-Disposition: attachment; filename="estudiantes_<código de asignatura>_g<grupo>.xlsx"`): hoja «Estudiantes», columnas `Código`, `Estudiante`, `Documento`, `Correo institucional`, `Origen` (la vía de la inscripción, como en la ruta 40), todo como texto, por apellidos y nombres. No exige período vigente. 404 «Grupo no encontrado.»; 403 `{"message":"Este grupo no es tuyo.","alcance":true}`; 409 `{"message":"El grupo no tiene lista cargada.","codigo":"SIN_LISTA"}`.
- **Lo que no se trajo del equipo**: la plantilla (ruta 39) conserva `ExigirPermisoDeLista` y su 403 con `permiso_requerido: padron_estudiantes`; el Administrador sigue sin `mis_grupos` (403 de permiso en las rutas del docente); `users`/`name` en `EloquentConflictoGateway`.
- **Para la integración**: `GET /api/reportes/grupos/{grupo}/estudiantes` (Reportes) ya entrega la lista de un grupo; conviven las dos y «Mis grupos» usa la descarga de este módulo. La bitácora no cambia: ninguna de las tres mejoras escribe.
- Pruebas: `tests/Feature/Modules/Estudiantes` 130 (nuevas `FichaDelEstudianteTest` 7, `ValidacionDeArchivoTest` 26, `DescargaDeListaTest` 8).

### P1 · Exámenes (puesta al día con el repositorio del equipo)

Dos mejoras traídas del equipo; nada de lo que B4 dejó cambia de forma (auxiliares, encargado y apoyo, código de aula, `qr_emitidos`, estado «Listo»).

- **Tablas** (migración nueva `2026_10_09_000100_create_normas_de_examen_tables.php`): `plantillas_norma` (`id`, `user_id` nulo → `users` en cascada, `texto` 300, marcas de tiempo; único `(user_id, texto)` y, en PostgreSQL, único parcial de `texto` con `user_id` nulo) y `examen_norma` (`id`, `examen_id` → `examenes` en cascada, `plantilla_id` nulo → `plantillas_norma` con `SET NULL`, `texto` 300, `orden`; único `(examen_id, plantilla_id)`). `user_id` nulo = norma predefinida. La columna se llama `user_id` (en el equipo, `usuario_id`). Las 6 predefinidas las siembra `NormasPredefinidasSeeder` (no duplica), llamado desde `DatabaseSeeder` y desde `DemoSeeder`.
- **Rutas nuevas** (`routes/examenes.php`, `auth` + `permiso:examenes`):
  - `GET /api/normas/plantillas` (`normas.plantillas.index`) → `{"data":[{"id":1,"texto":"Documento de identidad a la vista","predefinida":true,"propia":false},{"id":9,"texto":"Hoja de fórmulas A4","predefinida":false,"propia":true}]}`. Entero, sin paginar: primero las predefinidas y después las de la cuenta, por `id`. Nunca salen las de otra cuenta.
  - `POST /api/normas/plantillas` (`normas.plantillas.store`) con `{"texto":"…"}` → 201 `{"data":{id,texto,predefinida:false,propia:true},"message":"Plantilla creada."}`. El texto se guarda sin espacios a los lados ni repetidos. 422 `errors.texto`: `Obligatorio`, `Entre 3 y 300 caracteres`, `Ya existe` (otra plantilla de la misma cuenta con el mismo texto, sin distinguir mayúsculas ni tildes; puede coincidir con una predefinida o con la de otra cuenta). Asienta `norma.plantilla_crear`.
  - `PUT /api/normas/plantillas/{plantilla}` (`normas.plantillas.update`) con `{"texto":"…"}` → 200 `{"data":{…},"message":"Plantilla guardada."}`; mismos 422; 404 `{"message":"Plantilla no encontrada."}`; 403 `{"message":"Las normas predefinidas no se modifican.","alcance":true}` o `{"message":"Esta plantilla no es tuya.","alcance":true}`. Orden: 422 de forma → 404 → 403 → 422 `Ya existe`. No cambia los exámenes ya registrados. Asienta `norma.plantilla_editar`.
  - `DELETE /api/normas/plantillas/{plantilla}` (`normas.plantillas.destroy`) → 204; 404 y 403 como la anterior. Los exámenes que la tenían marcada conservan la norma con `plantilla_id: null`. Asienta `norma.plantilla_quitar`.
  - Las tres escrituras se asientan sobre la tabla `plantillas_norma`. `ConsultarBitacora::TEXTOS` (Administración) no tiene esas tres claves: se muestran tal cual hasta que se agreguen.
- **Campos nuevos del examen.**
  - Rutas 44, 45 y 46, respuesta: `"normas_marcadas":[{"id":7,"plantilla_id":2,"texto":"Sin celular"}]`, siempre presente (lista vacía si no hay), en el orden guardado; `id` es el de `examen_norma`; `plantilla_id: null` = la plantilla ya no existe. `texto` es la copia hecha al marcar, no el texto actual de la plantilla. El listado (ruta 42) no lo trae. `normas` (texto libre) sigue igual.
  - Rutas 45 y 46, cuerpo: `"normas_marcadas":[2,9]` (opcional; ids de plantillas predefinidas o propias, sin repetir, hasta 50, en el orden en que se muestran) y, solo con efecto en la 46, `"normas_conservadas":[7]` (opcional; ids de `examen_norma` con `plantilla_id` nulo que siguen en el examen). Al modificar, `normas_marcadas` **reemplaza** lo marcado (ausente o vacía = ninguna); la norma que ya estaba marcada conserva su texto y la nueva copia el texto actual de la plantilla. Las normas sin plantilla quedan al final: todas si `normas_conservadas` falta o es nula; solo las nombradas si va (lista vacía = ninguna).
  - 422: `errors.normas_marcadas.0` `La norma no existe.` (id desconocido o plantilla de otra cuenta); de forma, `errors["normas_marcadas.N"]` `Hay una norma repetida.` / `La norma no es válida.`, `errors.normas_marcadas` `Hasta 50 normas marcadas.`
  - Con ingresos registrados, `normas`, `normas_marcadas` y `normas_conservadas` se pueden cambiar (además de encargados y apoyo). Al eliminar el examen sus normas marcadas se van en cascada. El docente de un grupo sumado ve `normas_marcadas` en la 44.
- **Tipo «examen de mesa».** `TipoExamen::Mesa = 'MESA'`, etiqueta `Examen de mesa`; la ruta 43 devuelve cinco tipos (el último, `{"valor":"MESA","etiqueta":"Examen de mesa"}`); el rechazo por tipo repetido dice `El grupo 4 ya tiene un examen de mesa.` Los mapas de etiquetas repetidos en `Ingreso/…/EloquentPuertaGateway::TIPOS`, `Monitoreo/…/EloquentSeguimientoGateway::TIPOS` y `Reportes/…/EloquentReporteGateway::TIPOS` no tienen `MESA`: ahí un examen de mesa se muestra como `MESA` hasta que se agregue la línea.
- **Contratos**: nuevo `PlantillaNormaGateway` (`visiblesPara`, `buscar`, `crear`, `editar`, `quitar`); `GuardarExamenData` suma `normasMarcadas` y `normasConservadas` y `ExamenDetalleData` suma `normasMarcadas` (al final, con valor por defecto: quien ya los construía no cambia). `ComprobarDatosDeExamen` recibe además `PlantillaNormaGateway`.
- Pruebas: `tests/Feature/Modules/Examenes` 92 (73 de B4 + `PlantillasDeNormasTest` 9, `NormasDelExamenTest` 9 y una de examen de mesa en `RegistroExamenTest`).

### P3 · Usuarios y roles + Habilitación (puesta al día con el repositorio del equipo)

**Roles creables** (`routes/usuarios.php`, todo con `auth` + `permiso:usuarios_roles`). GUÍA ya tenía `roles.es_sistema` y el sembrador ya marca los tres de inicio: no hubo migración ni cambio en el sembrador.

- **Rol**: `{id, nombre, es_sistema, cuentas, permisos:[clave…]}`; `permisos` en el orden del catálogo y **solo los vigentes hoy** (la vigencia de `permission_role` es propia de GUÍA).
- `GET /api/roles` → `{data:[rol], meta:{permisos:[{clave, pantalla}]}}` (14 permisos; los roles de inicio primero y luego los creados, por `id`).
- `POST /api/roles` `{nombre, permisos:[clave]}` → 201 `{data: rol, message: "Rol creado."}`. `nombre` 3–40, único sin distinguir mayúsculas (se recortan los espacios); «bloqueadas» está reservado. Al menos un permiso. Asienta `rol.crear`.
- `PUT /api/roles/{rol}` `{nombre?, permisos}` → 200 `{data: rol, message: "Cambios guardados."}`. Sirve también para los roles de inicio (solo permisos). 422 con forma de validación: `errors.nombre` «El nombre de un rol de inicio no cambia.», `errors.permisos` «No puedes quitar «Usuarios y roles» a tu propio rol.» / «Al menos un permiso». Asienta `rol.modificar` solo si algo cambió. Lo que se guarda queda sin vencimiento (`vigente_desde` y `vigente_hasta` nulos).
- `DELETE /api/roles/{rol}` → 204; 409 `{message, codigo: "ROL_DE_INICIO"|"ROL_CON_CUENTAS"}` («Los roles de inicio no se eliminan.», «El rol tiene n cuenta(s) asignada(s).»). Asienta `rol.eliminar`.
- 404 `{"message":"Rol no encontrado."}`. Mensajes de campo: «Obligatorio», «Mínimo 3 caracteres», «Máximo 40 caracteres», «Ya existe», «Al menos un permiso», «El permiso no existe», «Permiso repetido».
- **Cuentas (rutas 8–11)**: `rol` admite ahora **cualquier rol existente** (`exists:roles,name`) en el filtro del listado, en el alta y en la edición. `meta` del listado suma `cuentas` (todas las cuentas, sin filtros) y `meta.conteos` trae una clave por rol existente (los de inicio primero, también con 0) más `bloqueadas`. Lo demás no cambia (acceso por `usuario`, temporal con `caduca_en`, `usuario.crear`).
- `GET /api/permisos/matriz` (ruta 12) se conserva tal cual: tabla fija de los tres roles de inicio, de lectura. La página nueva usa `GET /api/roles`.
- Archivos nuevos: `Http/Controllers/RolController`, `Http/Requests/GuardarRolRequest`, `Application/{Contracts/RolesGateway, DTOs/RolData, Queries/ListarRoles, Actions/{CrearRol,ModificarRol,EliminarRol}}`, `Infrastructure/Persistence/EloquentRolesGateway`, `Domain/Exceptions/{RolDeInicioException,RolConCuentasException,PermisoPropioException}`. `PaginaUsuariosData` suma `cuentas`. `ConsultarBitacora::TEXTOS` suma `rol.crear`, `rol.modificar`, `rol.eliminar`.
- Pruebas: nueva `RolesTest` (19); se ajustaron `UsuariosListadoTest` (`meta.cuentas`, rol creado), `AdministracionCuentasTest` (rol creado), `MatrizPermisosTest` (la vista fija sigue de lectura) y `RolesDelSistemaTest` (`/api/roles` ya existe: 401 sin sesión).

**Habilitación.** Cada fila de la ruta 54 suma `registrada_por` (nombre de la cuenta que registró la inhabilitación) y `registrada_el` («AAAA-MM-DD HH:MM», de `habilitaciones.updated_at`, en la zona en que se guarda); los dos son `null` en habilitados y sin revisar.

**Para el integrador**

- `GET /api/bitacora?rol=` sigue validando contra los tres roles de inicio (`ConsultarBitacoraRequest`, `Rule::in(NuevaCuentaData::ROLES)`): un rol creado da 422. Basta cambiarlo a `exists:roles,name`.
- `RolePermissionSeeder` vuelve a dejar los roles de inicio con su reparto exacto cada vez que corre: deshace lo editado en la matriz. Los roles creados no los toca.
- `RespaldoTest::test_restoring_reports_how_many_tables_were_put_back` espera 32 tablas y hoy son 34 por la migración `2026_10_09_000100_create_normas_de_examen_tables` (P1): hay que subir la cifra (o decidir si esas tablas se vuelcan).

### F0 · Armazón del frontend (puesta al día con el repositorio del equipo)

Sustituye, donde difiera, a «F0-D» (puntos 4 y 6) y a la «Guía rápida del armazón». Todo lo no nombrado aquí sigue como en esa guía: `api/{errores,usarConsulta,usarPaginaServidor,usarEdificios,descargar}`, `utiles/texto.js` y todos los componentes salvo `EsquemaApp`, `MapaCampus`, `MapaVivo` y `SinPermiso` son **idénticos byte a byte** a los del repositorio del equipo, así que una página portada los importa sin cambios.

**Qué cambió**

1. **Vistas por permiso, no por rol** (`navegacion/vistas.js`). Desaparecen `ACTORES`, `actorDe` y `TODAS_LAS_VISTAS`; queda una lista `VISTAS` de 20 rutas `{ruta, titulo, Icono, grupo, movil?, oculta?, padre?, permiso, con?, sin?, gemela?}`. `vistasDe(usuario)`, `entradaDe(usuario)`, `vistaDeRuta(vistas, ruta)` y `destinoDe(usuario)` conservan la firma; se agrega `gemelaDe(usuario, ruta)`. Un rol creado ve las vistas de sus permisos; `EsquemaApp` muestra `usuario.rol` tal cual.

   | Grupo | Vista | Ruta | Permiso |
   |---|---|---|---|
   | Preparación | Período · Oferta | `/periodo` · `/oferta` | `periodo_oferta` |
   | Preparación | Aulas y mapa · Docentes | `/aulas` · `/docentes` | `aulas_docentes` |
   | Preparación | Padrón | `/estudiantes` | `padron_estudiantes` |
   | Docencia | Exámenes · Registrar examen (oculta) | `/examenes` · `/examenes/nuevo` | `examenes` |
   | Docencia | Habilitación (oculta) | `/habilitacion` | `habilitacion` |
   | Docencia | Códigos QR (oculta) | `/codigos-qr` | `codigos_qr` |
   | Docencia | En curso · Asistencia del aula (oculta) | `/en-curso` · `/en-curso/aula` | `seguimiento_vivo` **con** `examenes` |
   | Docencia | Control | `/control` | `punto_control` **con** `examenes` |
   | Docencia | Control | `/ingreso` | `punto_control` **sin** `examenes` |
   | Docencia | Mi aula | `/mi-aula` | `seguimiento_vivo` **sin** `examenes` |
   | Docencia | Mis grupos | `/mis-grupos` | `mis_grupos` |
   | Docencia | Reportes | `/mis-reportes` | `reportes_examenes` |
   | Sistema | Reportes | `/reportes` | `reportes_universidad` |
   | Sistema | Usuarios y roles · Bitácora · Respaldo | `/admin` · `/bitacora` · `/respaldo` | `usuarios_roles` · `bitacora` · `respaldo_restauracion` |

   **Decisión no obvia.** Docente y Auxiliar comparten `punto_control` y `seguimiento_vivo` con pantallas distintas. Como ya no se puede mirar el rol, el juego de pantallas lo decide el permiso `examenes`: quien lo tiene (toma exámenes) ve En curso, Asistencia del aula y `/control`; quien no (solo atiende un aula) ve `/ingreso` y `/mi-aula`. Con los tres roles de inicio el menú es exactamente el de la maqueta. Las páginas siguen distinguiendo por la ruta, como en la maqueta (`pathname === '/control'`, `pathname.startsWith('/en-curso')`).
2. **`RutaProtegida`**: ante una vista que la cuenta no puede abrir dibuja `componentes/SinPermiso.jsx` dentro del armazón (título «Sin permiso», `nombre · rol`, enlace «Volver» a la entrada propia) en lugar de redirigir. Dos excepciones propias de este repositorio: con contraseña temporal sigue yendo a `/primer-ingreso`; y si la cuenta tiene la **gemela** de la ruta (`/control` ↔ `/ingreso`, `/en-curso` y `/en-curso/aula` ↔ `/mi-aula`) va a ella conservando `?examen=` y `?aula=`. Una cuenta sin ninguna vista va a `/sin-acceso`.
3. **`EsquemaApp`**: los rótulos de sección («Preparación», «Docencia», «Sistema») solo se dibujan si la cuenta tiene vistas de más de una sección (el docente y el auxiliar ven su menú sin rótulo, como en la maqueta). En el repositorio del equipo se dibujan siempre.
4. **Mapa con fondo real.** `MapaCampus` conserva su API completa (`edificios`, `caja`, `facultad`, `resaltados`, `contorno`, `rotulos`, `rotulosEdificio`, `tamanoRotulo`, `seleccionado`, `onElegir(edificio)`, `alto`). Con WebGL carga en diferido `componentes/MapaVivo.jsx` (MapLibre: CARTO si hay `VITE_CARTO_API_KEY`, OpenFreeMap Positron si no); sin WebGL, mientras carga la librería o sin edificios, queda el dibujo SVG de antes. Dos arreglos respecto del equipo: `onElegir` recibe **el edificio entero** también en el mapa real (allá recibía un objeto reducido sin `nombre` ni `aulas`), y el encuadre incluye el edificio de `contorno` («Aquí») junto a los resaltados. Sobre el mapa real los rótulos tienen tamaño fijo (`.mapa-rotulo` en `app.css`; `tamanoRotulo` solo vale para el SVG) y los resaltados van en el color de su facultad, no en azul. Dependencia nueva `maplibre-gl ^5.24.0`; variable `VITE_CARTO_API_KEY` en `.env.example`.
5. **`navegacion/Aplicacion.jsx`** exporta `PAGINAS` (ruta → componente). Su prueba sustituye las 18 páginas por su título (`vi.mock`), así que no depende de lo que cada página pida a la API ni de su `<h1>`.
6. **`test/apoyo.jsx`**: nuevo `CATALOGO_PERMISOS` (las 14 claves). `PERMISOS.Administrador` son los 7 de inicio, sin docencia.

**Guía para quien escribe una página**

- Nada del armazón cambia de firma respecto de la guía rápida anterior, salvo lo de arriba. Hooks: `usarConsulta(ruta, { parametros, activa = true })` → `{ datos, meta, cuerpo, cargando, actualizando, error, fallos, recargar }`; `usarSondeo(ruta, { parametros, intervalo = 10000, activa = true })` → lo mismo + `sinConexion`; `usarPaginaServidor({ porPagina, filtros })` → `{ pagina, porPagina, buscar, ponerBuscar, filtros, ponerFiltro, ponerFiltros, irA, parametros, filtrosVigentes, paginacion(meta) }`; `usarEdificios()` (y `olvidarEdificios()`); `descargar(ruta, { parametros, nombre = 'descarga' })` → promesa con el nombre del archivo; `usarExamen(examenes)` → `[examen | null, elegir(id)]`; `usarSesion()` → `{ usuario, cargando, entrar, salir, actualizar, puede(permiso) }`; `usarFacultades()`; `usarAccesoAula()`.
- **Destinos**: editar un examen `/examenes/nuevo?examen=<id>&paso=N`; habilitar `/habilitacion?examen=<id>`; credenciales `/codigos-qr?examen=<id>`; seguimiento `/en-curso?examen=<id>` y `/en-curso/aula?examen=<id>&aula=<id>` (auxiliar: `/mi-aula?examen=<id>&aula=<id>`); puerta `/control?aula=<id>` (auxiliar: `/ingreso?aula=<id>`). Para ofrecer un enlace a otra pantalla, `puede('<permiso>')`.
- **Pruebas**: `usuarioDePrueba('Administrador' | 'Docente' | 'Auxiliar', cambios)`; un rol creado, `usuarioDePrueba('Docente', { rol: 'Coordinación', permisos: [...] })`; una cuenta con todo, `usuarioDePrueba('Administrador', { permisos: CATALOGO_PERMISOS })`. La negativa de una página se prueba como en el equipo: `<RutaProtegida ruta="/aulas"><Aulas /></RutaProtegida>` y se espera el título «Sin permiso».
- En jsdom no hay WebGL: en las pruebas `MapaCampus` dibuja siempre el SVG (`svg[role=img]`, `polygon`, `text`), igual que antes.

**Diferencias con el armazón del equipo al portar una página o su prueba**

- La cuenta de prueba no trae `name` ni `email`, y `correo` es `null` (allá `correo` y `email` tienen valor). Se entra con `usuario`, no con correo: `entrar(usuario, password)` envía `{usuario, password}`.
- El Administrador de prueba tiene 7 permisos y 9 vistas (allá 6 vistas): suma Oferta, Reportes y Respaldo. El Docente tiene además `codigos_qr`, `punto_control`, `seguimiento_vivo` y `reportes_examenes` (9 vistas). **El Auxiliar aquí sí tiene pantallas** (`/ingreso`, `/mi-aula`): una prueba del equipo que lo use como «cuenta sin pantallas» o espere «Volver» → `/sin-acceso` tiene que usar `usuarioDePrueba('Docente', { permisos: [] })`.
- `api/cliente.js` emite además `CAMBIAR_CONTRASENA` (409 con `debe_cambiar_contrasena`) y existen `usarSondeo`, `AccesoAulaContexto`, `Incidencias` y `LectorQr`, que allá no están.

**Verificación**: `npx vitest run resources/js/{api,sesion,navegacion,componentes,utiles}` 34 archivos, 167 pruebas; `npx vitest run` completo 38 archivos, 201 pruebas; eslint y prettier sin observaciones sobre el armazón. El mapa real se probó en Chrome sin interfaz (390 px, OpenFreeMap): fondo, polígonos, rótulos «Aula 617» y «Aquí», clic → edificio entero.

**Pendiente para el integrador**

- `vite.config.js` no se tocó: el precaché de la PWA (`globPatterns: **/*.js`) incluirá el trozo de `maplibre-gl` (cerca de 1 MB); si supera el límite de workbox (2 MiB por archivo) avisa al compilar. Las teselas del mapa son de terceros y no se guardan: sin conexión el mapa real queda sin fondo (los polígonos sí se ven).
- No se ejecutó `npm run build`. El mapa no se probó con una clave de CARTO.

### WB · Padrón y Mis grupos

Las dos páginas se portaron del repositorio del equipo, con sus subcomponentes y pruebas. No se tocó el armazón ni el backend.

- **Archivos.** `resources/js/paginas/estudiantes/`: `Padron.jsx`, `FichaEstudiante.jsx`, `Conflictos.jsx`, `CargaInscripciones.jsx`, `Padron.test.jsx` (30 pruebas). `resources/js/paginas/docente/`: `MisGrupos.jsx`, `SelectorDeGrupos.jsx`, `InscritosDelGrupo.jsx`, `CargaDeLista.jsx`, `ResumenDeCarga.jsx`, `MisGrupos.test.jsx` (28 pruebas).
- **Contrato.** Las rutas 25, 33 a 41, la ficha y la descarga de «P2» responden aquí con la misma forma que en el equipo (comprobado contra el servidor), así que el código copiado no cambió. La única diferencia de «P2» que roza a las páginas, el permiso de la plantilla (`ExigirPermisoDeLista`), no las afecta: las dos piden `GET /inscritos/plantilla` (`alcance=facultad` el padrón, `alcance=grupo` el docente) con una cuenta que ya tiene el permiso de su pantalla, y ninguna lee `permiso_requerido`.
- **Único cambio respecto del equipo.** `Padron` repone el subtítulo «Período 2/2026» de la maqueta: lo toma de `principal` en `GET /periodos/vigentes` (ruta 20). Sin período vigente, o si esa consulta falla, el encabezado queda sin subtítulo y la página no se ve afectada.
- **Maqueta.** Fuera del subtítulo, el equipo no recortó nada en estas dos pantallas (no tienen auxiliares, código de aula, QR ni incidencias). Se conservan sus añadidos sobre la maqueta: ficha del estudiante, «Plantilla» dentro del diálogo de carga, resultado de la carga con filas rechazadas y en conflicto, carga del docente en dos pasos («Elegir archivo» → «Cargar lista») y el detalle del archivo rechazado entero. Las decisiones de cada página son las de «W4 · Padrón» y «W5 · Mis grupos» en `docs/sprint2-plan-de-portado.md` del equipo.
- **Comprobado contra el servidor** (puerto 8052, base `control_ingreso_guia`, solo lecturas y descargas). `administracion.academica`: resumen (4.218 estudiantes, 1.790 inscripciones, 106 por docentes, 3 conflictos), lista con `buscar`, `facultad` y `por_pagina`, carreras con y sin facultad, ficha 1 y su 404, conflictos (`meta.conteos`), períodos vigentes (`principal: "2/2026"`), plantilla de facultad (7 columnas) y 403 en `/docente/grupos`. `leticia.blanco` (6 grupos, 5 asignaturas, 4 sin lista) y `carga.alta` (9 grupos, 3 sin lista): lista paginada y con búsqueda (grupo 796: 300 inscritos, 12 páginas), grupo sin lista → `data: []`, grupo ajeno → 403 `alcance`, inexistente → 404, descarga `estudiantes_2010010_g2.xlsx` (cinco columnas, 65 filas), 409 `SIN_LISTA` y plantilla de grupo (4 columnas).
- **Sin comprobar**: la carga de archivos (rutas 36 y 41) y la resolución de conflictos (ruta 38) solo están probadas con el cliente simulado; tampoco se abrieron las páginas en un navegador (sin Vite ni `npm run build`): quedan para la integración la revisión a 390 px y una carga de punta a punta.
- **Para la integración**: `Bitacora` debería rotular `inscritos.cargar` y `padron.resolver_conflicto` si aún no lo hace.
- **Verificación**: `npx vitest run resources/js/paginas/estudiantes/ resources/js/paginas/docente/MisGrupos` 2 archivos, 58 pruebas; con `resources/js/navegacion`, 4 archivos y 88 pruebas; eslint y prettier sin observaciones sobre los archivos del paquete.

### WD · Usuarios y roles, y Respaldo

Archivos, todos en `resources/js/paginas/admin/`: `Usuarios.jsx`, `PermisosPorRol.jsx`, `Usuarios.test.jsx` (portados del equipo) y `Respaldo.jsx`, `Respaldo.test.jsx` (nuevos). No se tocó el armazón ni el backend.

**Usuarios y roles** (`/admin`, HU-15; rutas 8 a 11 y las cuatro de `/api/roles`)

- La página del equipo ya hablaba el contrato de GUÍA (`usuario` obligatorio, `correo` opcional que viaja `null`, `rol` por nombre, `activo` solo si cambió, `?bloqueadas=1`, `meta.cuentas` y `meta.conteos`, `GET /api/roles` con `meta.permisos`). Se portó sin cambios de comportamiento salvo uno: **el vencimiento de la temporal sale de `caduca_en`** («Caduca el 12 oct 2026, 12:50»), tras crear y tras restablecer; allá era el texto fijo «Caduca en 72 horas», que queda solo si la respuesta no trae `caduca_en`. `Docentes.jsx` (otro paquete) sigue con el texto fijo.
- No usa `GET /api/permisos/matriz` (ruta 12): la matriz editable sale de `GET /api/roles`. Los avisos son texto de la página («Usuario creado», «Cambios guardados», «Cuenta bloqueada», «Rol creado», «Rol eliminado»), no el `message` del servidor; los 422 por campo y los 409 de roles sí muestran el texto del servidor.
- **Maqueta.** El equipo no recortó nada de esta pantalla; es un superconjunto de la maqueta (roles creables, matriz editable, temporal a la vista con «Copiar», confirmación al restablecer). Diferencias menores que se conservan del equipo: el aviso del alta es «Usuario creado» (la maqueta: «Usuario creado · contraseña temporal enviada»; el envío se dice en el diálogo, `usuario · enviada a <correo>` / `· sin correo`), y la fila móvil dice «Sin correo».
- Prueba: el rol Administrador simulado tiene los 7 permisos de GUÍA (allá, los 14).

**Respaldo** (`/respaldo`, HU-25; rutas 14 a 17)

- Subtítulo `meta.programacion · último: <meta.ultimo>` (sin «último» si es nulo). Fichas Todos, Programados, Puntuales y Fallidos con `meta.conteos`; el filtro viaja como `?filtro=programados|puntuales|fallidos` («Todos» no envía el parámetro). Historial de a 15 paginado en el servidor: fecha (`fechaCorta`), tipo, tamaño (`tamano_bytes` → «5,1 MB», «48 MB», «20 KB»; nulo → «—») y estado (`Correcto`, `Fallido`, `Restaurado` si `restaurado`). Vacío: «Sin respaldos» («Sin resultados» con filtro).
- «Respaldar ahora» (`POST /respaldos`): el botón queda en «Respaldando» y deshabilitado; al terminar, «Respaldo generado», página 1 y, si el filtro era Programados o Fallidos, se quita. Con el 500 muestra el `message` y vuelve a leer el historial (la fila fallida queda a la vista).
- «Descargar» usa `api/descargar.js` con `/respaldos/{id}/descargar` y avisa «`<archivo>` descargado»; un rechazo (404 sin archivo en disco, 409) muestra su `message`. Solo las filas `correcto` tienen acciones.
- «Restaurar» pide confirmación en la misma fila («Sobrescribe los datos actuales», «Cancelar» / «Restaurar»), como la maqueta; mientras dura, «Restaurando» y los demás «Restaurar» y «Respaldar ahora» quedan deshabilitados. Aviso «Restaurado al `<fecha>`» (texto de la página) y relectura.
- **Respaldo incompatible** (409 `RESPALDO_INCOMPATIBLE`): aviso de error «Respaldo incompatible», la fila pasa a la insignia `Incompatible` y deja de ofrecer «Restaurar» (sigue «Descargar»). La marca vive en el estado de la página (se pierde al recargar): la ruta 14 no dice si un respaldo es compatible.
- Respecto de la maqueta: el archivo es `.sql` con el nombre del servidor (la maqueta inventaba `.sql.gz`); se agregaron los estados de carga, error y espera, y la insignia `Incompatible`.

**Comprobado contra el servidor** (puerto 8054, `administracion.academica`, base `control_ingreso_guia`): rutas 8 y 14 con filtros, `GET /api/roles`; rol de prueba creado, renombrado, cambiado de permisos y eliminado (204); 422 de nombre repetido, sin permisos, renombrar un rol de inicio y quitarse `usuarios_roles`; 409 `ROL_DE_INICIO`; un respaldo generado y descargado (`application/sql`, 5.387.077 bytes); 404 al descargar uno inexistente. **Quedó en la base**: el respaldo `id` 1 (`respaldo-20261009-161024.sql`, Puntual, correcto) con su archivo en disco y, en la bitácora, los asientos de esas escrituras (`rol.crear`, `rol.modificar`, `rol.eliminar`, `respaldo.generar`; no se contaron); los roles son los tres de inicio con su reparto. **No se probó contra el servidor**: restaurar (rehace la base compartida), el rechazo por incompatible, crear o editar cuentas, bloquear y restablecer la temporal; están cubiertos con el cliente simulado. Tampoco se abrió en un navegador.

**Para la integración**

- Si se quiere marcar el incompatible sin intentar restaurarlo, la ruta 14 tendría que sumar un campo (por ejemplo `compatible`) a cada fila.
- Tras restaurar, la sesión propia puede dejar de valer (la base se rehace): el cliente lo resuelve con el 401 de siempre. No se comprobó en vivo.
- Lo ya anotado por B1 y P3 sigue pendiente: la pantalla dice `Diario, 03:00` y `routes/console.php` programa a las 02:00; `GET /api/bitacora?rol=` rechaza un rol creado.

**Verificación**: `npx vitest run resources/js/paginas/admin/Usuarios.test.jsx resources/js/paginas/admin/Respaldo.test.jsx` 2 archivos, 38 pruebas (23 + 15); con `resources/js/navegacion`, 4 archivos y 68 pruebas; eslint y prettier sin observaciones sobre los cinco archivos.

### WG · Reportes del docente y Reportes de la universidad

Páginas nuevas (no existen en el repositorio del equipo), en `resources/js/paginas/reportes/`: `Reportes.jsx` (HU-24, `/reportes`), `ReportesDocente.jsx` (HU-23, `/mis-reportes`) y el subcomponente compartido `BotonesDescarga.jsx`, con sus pruebas. Solo lecturas y descargas; no se tocó el backend ni el armazón.

**`Reportes` (administrador).** Lee la ruta 73 con `usarPaginaServidor` (10 por página; `periodo`, `facultad`, `desde`, `hasta`, `buscar`) y descarga con las 74–76.

- **Período**: lista «Período» con «Todos» por defecto (sin `periodo`, rigen las fechas). Las opciones salen de `GET /api/periodos?por_pagina=100` si la cuenta tiene `periodo_oferta` (incluye los cerrados) y de `GET /api/periodos/vigentes` si no. Por eso no hay subtítulo «Período …» como en la maqueta, y las fechas arrancan vacías (la maqueta las fijaba en la ventana de parciales).
- **Facultad**: `FiltroFacultad` con la **sigla** (`?facultad=FCyT`, que la ruta admite), porque `meta.conteos` viene por sigla.
- **Rango invertido** (`hasta < desde`): no se consulta (la ruta daría 422); «Hasta» muestra `Anterior a «Desde»`, la lista queda en `Sin resultados` y las descargas deshabilitadas.
- **Examen elegido**: se guarda la fila (puede no estar en la página visible) y viaja como `examen=<id>` en la 73 y en las descargas; las tres cifras son `meta.cifras`. Cambiar cualquier filtro o la búsqueda lo quita; cambiar de página no.
- **Descargas**: `formato` + los filtros vigentes (+ `examen`). Cada botón se deshabilita solo mientras dura **su** descarga; el resto de la pantalla sigue en uso. Se deshabilitan todos con `cifras.examenes = 0` (la ruta daría 409 `SIN_DATOS`). El aviso es `<nombre del servidor> descargado`, o el `message` del servidor si falla.

**`ReportesDocente`.** Dos lecturas de la ruta 77: una con `por_pagina=100` (el máximo) para el selector, las cifras del examen elegido, `meta.examenes`, `meta.rendidos` y las fichas de asignatura; otra paginada (8) con `asignatura` para «Exámenes del período». **No usa la ruta 42** (5.5 la listaba): así la pantalla no depende del permiso `examenes`. Un docente con más de 100 exámenes en los períodos vigentes solo vería los 100 primeros en el selector (la lista de abajo sí los pagina todos).

- **Período**: la lista «Período» («Vigentes» + cada uno de `GET /api/periodos/vigentes`) solo aparece con más de un período vigente; con uno solo va en el subtítulo.
- Se agrega el dato «Habilitados» a los tres de la maqueta (lo pide el criterio 1 de la HU-23).
- **Incidencias**: tarjeta «Incidencias» del examen rendido con `ListaIncidencias` (ruta 67, `estado=todas`), de solo lectura, y solo si la cuenta tiene `seguimiento_vivo`. Reúne expulsiones, excepciones e incidencias reportadas; **no** los intentos del sistema ni tiene descarga (ver pendientes).
- Descargas 78 y 79 con `examen` y `formato`; deshabilitadas mientras el examen no esté rendido.

**Pendiente para la integración (falta en el backend)**

- **HU-23, criterio 4 — reporte de incidencias del docente.** No existe `GET /api/reportes/incidencias/exportar?examen=&formato=` (`reportes_examenes` + docente del examen) con intentos no autorizados (incidencias `SISTEMA`), excepciones, expulsiones e incidencias reportadas. Al agregarla basta sumar una entrada a `reportes` en `ReportesDocente.jsx` (clave `incidencias`). La ruta 67 solo da las de `origen = PERSONA` y exige `seguimiento_vivo`.
- **HU-24, criterio 5 — resumen por estudiante.** No hay ruta (ni de lectura ni de descarga) que reúna exámenes, intentos, expulsiones e incidencias de un estudiante en el período; la pantalla no lo ofrece. Hace falta, por ejemplo, `GET /api/reportes/estudiantes/exportar?formato=` con los filtros de la 73 (y/o `estudiante=`).
- **HU-24, criterio 4**: la tabla de `intentos` no trae la columna «examen al que intentó ingresar» (solo «Examen que le correspondía»).
- **Períodos del docente**: no hay ruta con los períodos (también cerrados) en que el docente tuvo exámenes; solo puede filtrar entre los vigentes. La ruta 77 tampoco devuelve `meta.periodo`.
- Lo ya anotado por P1 y B8 se ve en estas pantallas: un examen de mesa sale como `MESA` (`EloquentReporteGateway::TIPOS`) y `rendido` depende de la zona horaria de la aplicación.
- No se revisó en un navegador (sin Vite ni compilación): el ajuste a 390 px se cuidó con las clases de la maqueta, sin comprobación visual.

**Verificación**: `npx vitest run resources/js/paginas/reportes` 2 archivos, 24 pruebas (12 + 12); eslint y prettier sin observaciones sobre los cinco archivos. Contra el servidor real (puerto 8057): rutas 73 y 77 con todos los filtros que envían las páginas, las 6 descargas del administrador y las 4 del docente (200, PDF y XLSX válidos, nombres `horarios_ingreso_umss_<hoy>.pdf`, `…_examen1_…`, `…_fcyt_…`, `asistencia_<fecha>.pdf`), 409 `SIN_DATOS` y `SIN_RENDIR`, 403 de alcance y de permiso cruzado, y la ruta 67 con `leticia.blanco` y `carga.alta`.

### WA · Preparación del administrador (Período, Oferta, Aulas y mapa, Docentes, Bitácora)

Archivos, todos en `resources/js/paginas/admin/`: `Periodo`, `Aulas`, `Docentes`, `Bitacora` y `Oferta`, cada uno con su `.test.jsx`. Sin subcomponentes en archivos aparte. No se tocó nada del armazón ni del backend.

- **Período (HU-10) y Aulas y mapa (HU-11)**: copiados del equipo sin cambios, con sus pruebas. El contrato de las rutas 19, 21 a 24, 28 y 29 es el mismo en los dos repositorios (se compararon controladores y pasarelas: solo difiere `users`/`usuarios`). Valen las notas «W1» y «W2» del plan del equipo: «Ajustar» solo en períodos «Sin fechas»; la clave de la facultad para importar sale de `usarFacultades()`; las aulas sin edificio figuran en la lista y no se pueden elegir; «Reintentar» vuelve a montar la vista de aulas.
- **Docentes (HU-12)**: copiado del equipo (ya traía el usuario propuesto y editable, y el correo opcional). Se repuso el período en el subtítulo, como en la maqueta: «Período 2/2026 · 847 docentes · 30 en más de una facultad»; sale de `principal` de `GET /periodos/vigentes` y, si no hay período vigente o la ruta falla, se omite. Los filtros booleanos viajan como `1`. «Caduca en 72 horas» sigue siendo texto fijo (no se calcula de `caduca_en`).
- **Bitácora (HU-07)**: la página del equipo no sirve aquí (allá la ruta devuelve la lista entera, con `usuario` como objeto, `tabla_afectada` y `fecha_operacion`, y la página rotula las operaciones). Se escribió sobre la maqueta y la ruta 13 de este repositorio: `GET /bitacora?buscar&rol&operacion&desde&hasta&pagina&por_pagina=25` con `usarPaginaServidor`; cada cambio de filtro consulta (no hay botón «Filtrar»). La fila muestra `usuario · operacion_texto`, `entidad` (si viene; es nula en los asientos de sesión) y `rol · fecha` (sin rol en los del sistema). El desplegable de operaciones sale de `meta.operaciones` (clave y texto) y las fechas se acotan entre `meta.fecha_minima` y hoy; las opciones se conservan cuando una consulta se rechaza. Un 422 se muestra como «Filtros no válidos» y el mensaje del servidor va bajo su campo. **Filtro por rol**: con `usuarios_roles` las opciones salen de `GET /roles` (incluye los roles creados); sin ese permiso se ofrecen los tres de inicio y no se pide la ruta. No hay filtro por cuenta (`usuario_id`): se busca por nombre con `buscar`, como en la maqueta.
- **Oferta (HU-17)**: nueva, sobre la maqueta. Filtros: **Período** (no está en la maqueta; lo piden los criterios 1 y 7: «Vigentes» por defecto, sin `periodo`, y todos los de `GET /periodos?por_pagina=100`, los no vigentes con su estado), Facultad (abre en la primera del catálogo y envía la clave), Carrera («Todas» por defecto; `GET /oferta/carreras?facultad=`), Nivel (`meta.niveles`) y Buscar. Lista de `GET /oferta/asignaturas` de a 15; al abrir una asignatura, `GET /oferta/asignaturas/{id}/grupos` de a 10 con `periodo`, `facultad` y `buscar` (un componente `Grupos` dentro del mismo archivo, con su propia paginación, que solo se dibuja con más de 10). Cambiar de período, facultad o carrera suelta el nivel. Subtítulo: el período pedido o, sin él, los vigentes que le tocan a la carrera (el anual para una carrera de régimen `Anual`, los demás para una semestral; sin carrera, todos: «Períodos 2/2026 y 0/2026 · FCyT»). Los niveles llegan por orden alfabético («Cuarto año», «Primer año»…, «Semestre 1», «Semestre 10», «Semestre 2») y la página los ordena por el plan (`ordenarNiveles`, exportada y probada).

**Pendiente para la integración**

- `ConsultarBitacora::TEXTOS` no tiene tres claves que `DemoSeeder` sí asienta: `auxiliar.crear`, `codigo_qr.emitir` y `habilitacion.registrar`. `GET /api/bitacora` las devuelve con la clave como `operacion_texto` (en la fila y en `meta.operaciones`) y así se ven en la pantalla. O se agregan al mapa o el sembrador usa las claves que el mapa ya tiene (`auxiliar.registrar`, `qr.emitir`, `habilitacion.habilitar`).
- `GET /api/roles` exige `usuarios_roles`: un rol creado que solo tenga `bitacora` no puede filtrar por roles creados (ve los tres de inicio). Bastaría que `meta` de la ruta 13 trajera `roles: [nombre…]`.
- `GET /api/oferta/asignaturas`: `meta.niveles` sale por orden alfabético; convendría el orden del plan desde el servidor. `GET /api/oferta/carreras` no admite `periodo`: con un período cerrado el desplegable sigue mostrando las carreras de la facultad en todos los planes.
- `GET /api/periodos/resumen` sigue sin la `clave` de cada facultad (ya anotado por el equipo): la página la resuelve por sigla.
- `usarEdificios` no sabe recargar y `GET /api/edificios` va con `max-age=3600`: tras reimportar ubicaciones el mapa puede tardar en reflejarlo (nota «W2» del equipo).
- No se revisó en un navegador (sin Vite ni compilación): el ajuste a 390 px se cuidó con las clases de la maqueta, sin comprobación visual.

**Verificación**: `npx vitest run` sobre los cinco archivos de prueba, 88 pruebas (Período 17, Aulas 20, Docentes 20, Bitácora 14, Oferta 17); eslint y prettier sin observaciones sobre los diez archivos; `resources/js/navegacion` sigue en verde. Contra el servidor real (puerto 8051, `administracion.academica`, solo lecturas; el único asiento que dejó es el `sesion.iniciar` de la entrada): rutas 13 (con `rol`, `operacion`, `buscar` y el 422 de fechas invertidas), 19, 20, 23, 25, 26 (con `periodo`, `facultad`, `carrera`, `nivel` y `buscar`), 27 (con `periodo` y el 404), 30 y 31, y `GET /roles`; las formas coinciden con lo que consumen las páginas. Datos vistos: 3 períodos (2 vigentes), 847 docentes (632 sin cuenta), 469 asignaturas y 1.100 grupos en FCyT, 28 asientos de bitácora desde el 30 de septiembre.

### WF · Examen en curso y Lista del aula

**Archivos** (páginas nuevas; no existen en el repositorio del equipo): `paginas/docente/{EnCurso.jsx,EnCursoAulas.jsx,EnCurso.test.jsx}` y `paginas/monitoreo/{Asistencia.jsx,Asistencia.test.jsx}`. No se tocó el armazón ni el backend.

**`docente/EnCurso` (HU-22)** — lee 70, 71 (sondeo 10 s), 67 (sondeo 10 s) y 28; escribe 68 y 69.

- El selector usa `SelectorExamen` + `usarExamen` con la lista de la ruta 70 adaptada a `{id, fecha, tipo, asignatura}`. La 70 no trae `tipo` aparte: se saca del segundo tramo de `texto` (`«9 oct · Primer parcial · …»`).
- El tablero (`Tablero`, con `key` por examen) pinta las cuatro cifras de `cifras`, la tarjeta de incidencias (fichas «Sin atender» / «Todas», filtradas en el cliente sobre la lista entera de la 67; solo aparece si hay alguna), filtros y orden, el mapa y las aulas. La hora que acompaña a «Ingreso abierto» es la de `hora_servidor` de la última lectura (`16:07:16 · Ingreso abierto`); con el examen sin abrir, `11 oct, 08:15 · 16:07:16`.
- **Filtros y orden a la vista con dos aulas o más** (la maqueta solo los muestra con más de seis): fichas «Todas», «Con incidencias», «Con pendientes», «Sin ingresos», «A mi cargo» y orden «Menor avance» (por defecto), «Más pendientes», «Aula». La rejilla compacta sigue reservada a más de seis aulas (`EnCursoAulas`, prop `denso`).
- Por aula: `ingresados de asignados · Faltan N` (N = `asignados − ingresados`, nunca negativo: quien entra por excepción cuenta en la puerta de esa aula), encargado con «Docente» o «Auxiliar a cargo», apoyo, insignias `N incidencias`, «Sin ingresos» (solo con el ingreso abierto), «Compartida · <otros exámenes>» y «Código de acceso». Enlaces «Lista» → `/en-curso/aula?examen=<id>&aula=<aula_id>` y, con `abierto` y permiso `punto_control`, «Controlar» → `/control?aula=<aula_id>`.
- Mapa: `MapaCampus` con `resaltados` = los `edificio_id` de las aulas, `rotulosEdificio` = «Aula 691B, 691A» por edificio y `facultad` = la del primer edificio resaltado.
- «Atender» llama a la 69, avisa «Incidencia atendida» (o el `message` del servidor, p. ej. 409 `YA_ATENDIDA`) y recarga incidencias y tablero. No hay forma de deshacer. «Incidencia» (ruta 68) se ofrece el día del examen (`hora_servidor` y `fecha` coinciden), no solo con `abierto`.
- Un docente puesto a cargo de un aula en un examen ajeno recibe ese examen en la 70 y 403 en la 71: en lugar del tablero ve «Aulas a mi cargo» con el enlace a la lista de cada una, y no se pide la 67.

**`monitoreo/Asistencia` (HU-21)** — lee 70, 72 (sondeo 15 s) y 67 con `?aula=` (sondeo 15 s); escribe 68 y, solo el docente, 69.

- Una sola página para `/en-curso/aula` y `/mi-aula`; distingue por `pathname.startsWith('/en-curso')`. El puesto sale de `?examen=&aula=` (ids) contra la ruta 70; si falta o no es de la cuenta: el mismo examen, luego la misma aula, luego un examen abierto, luego el primero. Sin ninguno: título «Lista del aula» y «Sin aula asignada».
- Fichas «Aula» cuando hay más de una alcanzable (no están en la maqueta): el docente, las del examen; el auxiliar, todas las que atiende (`691A · 11 oct 08:15 · Asignatura` si son de varios exámenes). Escriben `?examen=&aula=`.
- Lista paginada en el servidor (`usarPaginaServidor`, 25 por página, filtro `lista` = `faltan` por defecto | `ingresaron`, búsqueda con espera de 300 ms). **Condición por fila**: «Ausente» en `faltan`; en `ingresaron`, «Presente» con su hora o «Expulsado» (`expulsado: true`). Las cifras salen de `cifras`, no de la página.
- Es de consulta: no hay ninguna acción sobre ingresos ni estudiantes. Lo único que escribe es lo que ya traía la maqueta y la tabla de 5.5: reportar una incidencia del aula (el día del examen) y, el docente, atenderla. El auxiliar ve «Sin atender» sin botón.
- «Controlar» (con `abierto` y `punto_control`) → `/control?aula=` (docente) o `/ingreso?aula=` (auxiliar). Un 403 de la 72 (`Solo puedes seguir tu aula el día del examen.`) se muestra tal cual en el lugar de la lista, sin «Reintentar», y no se pide la 67.

**Pendiente para la integración (backend, no se tocó)**

- **Ruta 67**: no devuelve quién atendió ni cuándo (faltan, p. ej., `atendida_por` y `atendida_en`). El servidor lo guarda, pero la pantalla solo puede mostrar «Atendida»; el criterio «queda quién y cuándo» de HU-22 no se ve en la lista.
- **Ruta 71**: no trae la hora del último ingreso del examen ni por aula (p. ej. `ultimo_ingreso`); la pantalla muestra la hora de la última lectura (`hora_servidor`).
- **Ruta 72**: `buscar` cubre nombre y código, no el documento que pide HU-21; el campo dice «Nombre o código».
- **Ruta 70**: no trae `tipo` (se extrae de `texto`).
- No se revisó en un navegador (sin Vite ni compilación): el ajuste a 390 px se cuidó con las clases de la maqueta (`min-w-0`, `truncate`, fichas con desplazamiento propio), sin comprobación visual.

**Verificación**: `npx vitest run resources/js/paginas/monitoreo resources/js/paginas/docente/EnCurso.test.jsx` 2 archivos, 33 pruebas (EnCurso 19, Asistencia 14); eslint y prettier sin observaciones sobre los cinco archivos. Contra el servidor real (puerto 8056, solo lecturas; no se marcó ninguna incidencia): con `leticia.blanco`, 70 (2 exámenes; el de hoy abierto con 5 aulas), 71 (102 ingresaron, 149 faltan, 2 incidencias sin atender; 691A compartida con «Calculo I · Primer parcial», 624 con código), 67 (5 incidencias, 2 sin atender) y 72 del aula 691A (22 / 28 / 50; búsqueda y página correctas; un expulsado en la 691B); con `carla.salazar`, las mismas 70–72; con `diego.mamani`, 70 con solo la 691E, 72 y 67 `?aula=35` en 200, 67 sin aula 422, 71 y 72 de otra aula 403.

### WC · Exámenes, Registrar examen y Habilitación

Tres páginas portadas del repositorio del equipo (`paginas/examenes/{Examenes,RegistrarExamen,NormasDelExamen}`, `paginas/habilitacion/Habilitacion`, con sus pruebas) y completadas con lo que allá se recortó. Dos subcomponentes nuevos en `paginas/examenes/`: `QuienAtiende.jsx` y `CodigoDeAula.jsx`. No se tocó nada fuera de esas dos carpetas.

**Exámenes** (`/examenes`, ruta 42 y 47)

- Cinco pasos por examen: Grupos, Aulas, **Quién atiende** (`Pendiente` sin aulas, `n auxiliares` o `Solo docente`), Habilitación y **Códigos QR** con su estado real (`Pendiente`, `120 de 251` a medias, `251 emitidos` con `avance.qr = ok`; sale de `qr_emitidos`). El resumen dice «n de 5 pasos»; el estado «Listo» va en verde.
- Acción siguiente según `accion.clave`: `elegir_aulas` → `/examenes/nuevo?examen=<id>&paso=2` (solo `propio`); `habilitar` → `/habilitacion?examen=<id>` («Habilitar» o «Terminar habilitación»); `emitir_qr` → «Emitir códigos» y `ver_qr` → «Ver códigos QR», las dos a `/codigos-qr?examen=<id>`.
- **«En curso»**: el examen con `es_hoy`, sin `rendido` y con aulas muestra el botón a `/en-curso?examen=<id>` en lugar de la acción siguiente, que baja a los enlaces del pie (como en la maqueta). La maqueta lo pone en el destacado aunque sea «el próximo»; aquí solo en el de hoy.
- Enlaces del pie con el examen ya con aulas: «Habilitación» y «Códigos QR» (el que no sea ya la acción siguiente). Cada enlace exige su permiso con `puede()`: `habilitacion`, `codigos_qr`, `seguimiento_vivo`.
- Se conserva del equipo lo que la maqueta no tiene: «Editar» y «Eliminar» (con confirmación y el 409 a la vista) para quien registró el examen.

**Registrar examen** (`/examenes/nuevo`, rutas 43 a 53 y plantillas de normas)

- Cinco pasos: Examen (con las normas por plantilla del equipo, sin cambios), Grupos, Aulas, **Quién atiende** y Listo. `?paso=` admite 0 a 3. «Registrar examen» / «Guardar examen» pasó del paso de aulas al de quién atiende; en Aulas queda «Continuar», que no exige aulas (sin aulas el examen queda «Faltan aulas»).
- Cuerpo de las rutas 45 y 46: `aulas: [{aula_id, encargado_id, apoyo:[id]}]`, en el orden elegido. Por defecto cada aula queda a cargo de quien registra (`usuario.id` de la sesión), sin apoyo. Lo demás del cuerpo es lo del equipo (`normas_marcadas`, y `normas_conservadas` solo al modificar).
- Paso «Quién atiende» (`QuienAtiende.jsx`): por aula, «A cargo» (quien registra, los docentes que ya atienden el examen y los auxiliares) y «Apoyo» (auxiliares que no estén a cargo de esa aula; se quitan uno a uno); «Repartir» con más de dos aulas (la primera queda con el docente y cada una de las siguientes con un auxiliar). La lista de auxiliares es la ruta 50 sin `buscar`, más los sumados en la visita y los que ya atienden el examen; el buscador «Buscar auxiliar» consulta la ruta 50 con `buscar` (desde 3 letras, con 300 ms de espera) y ofrece sumar a los que no son de la cuenta. «Auxiliar nuevo» envía la ruta 51 con `{nombre, codigo_universitario}`: el campo se rotula «Correo institucional» con el sufijo `@est.umss.edu` (como la maqueta) y lo que se escribe es el código universitario (5 a 10 dígitos, se valida antes de enviar); el 409 `Auxiliar ya registrado` suma a la lista la cuenta que devuelve `data`.
- Rechazos: `aulas.N.encargado_id` y `aulas.N.apoyo` se quedan en el paso 4, bajo el aula N; los demás `aulas.*` vuelven al paso de aulas; el 409 `EXAMEN_CON_INGRESOS` se muestra en el paso 4, que es donde se guarda.
- **Código de acceso** (`CodigoDeAula.jsx`, rutas 52 y 53): botón de llave por aula; el diálogo muestra el código, `encargado · fecha, hora`, «Copiar» (portapapeles) y «Regenerar». Como el código es de un aula **ya guardada** en el examen, el botón está deshabilitado en un examen sin registrar y en un aula recién agregada; tras registrar, el paso «Listo» lista cada aula con quién la atiende y su botón de código. La llave va en color cuando el aula ya tiene código (`usa_codigo`, o recién abierto).
- El resumen (paso «Listo» y la vista de solo lectura del docente de un grupo incluido) suma la lista «Quién atiende»: `aula · encargado · apoyo`, con el código de cada aula.
- Catálogos que usa además de los del módulo: `GET /api/docente/grupos` (asignaturas que dicta), `/api/periodos/vigentes` (límites de la fecha), `/api/aulas` y `/api/edificios` (lista y mapa).

**Habilitación** (`/habilitacion`, rutas 54 a 56): la del equipo, con la autoría de la inhabilitación (`registrada_por · registrada_el`) bajo el motivo. Se repone el enlace «Generar códigos QR» → `/codigos-qr?examen=<id>` en el encabezado (con permiso `codigos_qr`); pasa a botón primario cuando no queda nadie sin revisar y hay al menos un habilitado, con las `cifras` de la última respuesta de la ruta 54.

**Verificación**: `npx vitest run resources/js/paginas/examenes resources/js/paginas/habilitacion` 4 archivos, 100 pruebas (Exámenes 21, Registrar examen 40, Normas 12, Habilitación 27); eslint y prettier sin observaciones. Contra el servidor real (puerto 8053, `leticia.blanco` y `carga.alta`): lecturas de las rutas 42, 43, 44, 48, 49, 50, 52, 54 y plantillas; y, con datos propios ya borrados, alta de una plantilla, registro de un examen de mesa con `aulas: [{aula_id, encargado_id, apoyo}]`, código de aula (generar y regenerar; 404 en un aula ajena al examen), modificación (422 `aulas.0.encargado_id`, y cambio de encargado) y eliminación de ambos. Quedaron los asientos de la bitácora de esas escrituras.

**Pendiente para la integración**

- `GET /api/examenes/opciones/grupos` da el nombre del docente de cada grupo pero no el **id de su cuenta**: «A cargo» no puede ofrecer a los docentes de los grupos sumados (la maqueta sí), solo a quien registra y a los que ya figuran como encargados en `aulas_detalle`. Bastaría `docente_usuario_id` en cada grupo de la ruta 48.
- No hay ruta para **quitar** un auxiliar de la lista de la cuenta (la maqueta deja quitar los agregados en la visita): aquí no se quitan.
- Los 422 de la ruta 51 le hablan al usuario («Escribe el nombre del auxiliar.»); la página valida antes y muestra «Obligatorio», «Mínimo 3 caracteres» y «Entre 5 y 10 dígitos», pero el texto del servidor se ve si llega (por ejemplo, el correo ya usado por otra cuenta).
- La ruta 52 genera el código al consultarlo (un `GET` que escribe y asienta `codigo_aula.generar`): abrir el diálogo ya lo crea.
- Las normas se muestran en el resumen con el texto copiado al marcar; las claves `norma.plantilla_*` y `codigo_aula.*` siguen sin texto en la bitácora (anotado en P1 y B4).
- Sin Vite no se revisó en un navegador a 390 px ni a 1.360 px; las clases de ancho son las de la maqueta (rejilla de pasos `grid-cols-2 lg:grid-cols-5`, filas de aula con `min-w-0` y `truncate`).

### WE · Códigos QR y Punto de control

Páginas nuevas (no existen en el repositorio del equipo); la maqueta es el diseño. Sin cambios en el backend ni en el armazón.

**Archivos**

- `paginas/docente/CodigosQr.jsx` (+ prueba, 14) y `paginas/docente/CredencialExamen.jsx` (la tarjeta «Credencial», el mapa «Ubicación» y la insignia `EstadoEnvio`).
- `paginas/ingreso/PuntoControl.jsx` (+ prueba, 20), `paginas/ingreso/VeredictoPuerta.jsx` (veredicto, situación excepcional y mapa del aula correcta) e `paginas/ingreso/IngresosDelAula.jsx` (ingresos y diálogo «Expulsión»).

**Códigos QR (`/codigos-qr?examen=<id>`, rutas 42, 57–61)**

- Cuatro cifras sobre la lista (`meta` de la 57): Habilitados, Emitidos, Enviados, Sin enviar. Cada fila lleva su estado: `Sin emitir`, `Sin enviar` o `Enviado`; la credencial a la vista suma la fecha del envío (`enviado_en`). La maqueta no tenía las cifras ni el estado por fila: se agregan por el criterio «a quién se envió y a quién no».
- «Emitir códigos» aparece mientras `emitidos < habilitados` (también tras habilitar a alguien después de emitir); «Enviar por correo» e «Imprimir credenciales», en cuanto hay algún emitido. Con filtro (grupo, aula, texto), el envío y la hoja van solo a lo filtrado: los mismos parámetros viajan en el cuerpo de la 60 y en la *query* de la 61.
- **Hoja**: `window.open('/api/examenes/{id}/codigos-qr/hoja?…', '_blank', 'noopener')`; la abre el navegador con la cookie de la sesión.
- **Envío por tandas** (advertencia de B6): la 60 se repite solo mientras `pendientes` baja; si `pendientes` es 0 o no baja respecto de la vuelta anterior se detiene (tope 30 vueltas) y, si quedó algo, avisa «N códigos sin enviar». Mientras tanto el diálogo dice «Enviando · quedan N».
- **Reenvío de una credencial**: botón «Enviar» / «Reenviar» en la tarjeta. No hay ruta por estudiante: usa la 60 con `{"buscar":"<código universitario>"}`.
- Textos de los 409: `SIN_HABILITADOS` → «Sin estudiantes habilitados»; `SIN_EMITIR` → «Sin códigos emitidos». El 403 de un examen ajeno se muestra en el lugar de la lista.

**Punto de control (`/control`, `/ingreso` y `/acceso-aula`, rutas 62–66 y 68)**

- El aula sale de `?aula=<id>` si está en la 62; si no, la primera `abierta`; si ninguna, la primera. Sin aulas: «Sin aulas hoy». «Cambiar» solo aparece con más de un aula (con código de aula nunca) y escribe `?aula=`; el puesto se monta de nuevo.
- Sondeo de la 63 cada 10 s; «Sin conexión» va en la cabecera del aula (esta pantalla no tiene `Encabezado`). «Ver los N» pide `ingresos=todos` y la búsqueda (300 ms) va en `buscar`.
- Visor: el recuadro enciende `LectorQr`; una lectura llama a la 64 con `token` y apaga la cámara; «Siguiente» la vuelve a encender si el veredicto venía de QR. Si `LectorQr` avisa error: «Cámara no disponible» y el ingreso manual abierto. Tras un error de la 64 leída por QR la cámara queda apagada (si no, releería el mismo código sin parar).
- Veredictos: los seis de la 64. `otra_aula` muestra aula, edificio, piso y el mapa (`resaltados` con el `edificio_id` del aula correcta y `contorno` + «Aquí» en el de la puerta); con `aula_correcta: null` solo el `dato` («Sin aula asignada»). `no_corresponde` muestra `examen_correspondiente` como «texto · Aula X».
- Situación excepcional: se ofrece en `no_habilitado`, `otra_aula` («Admitir como excepción») y `no_corresponde`. «Cambio de aula» solo figura en la lista si el veredicto trae estudiante y examen. Se envía `estudiante_id` del veredicto y `examen_id` solo si es uno de los exámenes a la vista del aula. El 409 de un cambio de aula que no puede admitir se muestra con su `message`.
- Expulsión: botón en cada ingreso no expulsado; el alcance lo decide el servidor (403 o 409 `YA_EXPULSADO` → «Ya estaba expulsado»).
- Incidencia desde la puerta: con código de aula va al `examen_id` del acceso; con cuenta, al primer examen del aula que no responda 403 (el aula puede compartir exámenes y la 63 no dice en cuál actúa la persona).
- «Lista» enlaza a `/en-curso/aula` (desde `/control`) o a `/mi-aula`, con `?examen=<primer examen del aula>&aula=<id>`, solo con cuenta y permiso `seguimiento_vivo`.
- Las normas se muestran con sus saltos de línea; un examen sin normas dice «Sin normas».

**Pendiente para la integración (backend o archivos ajenos)**

- **Avisos por correo (HU-20, criterio 6) sin ruta.** El único correo es el de la credencial (`correos/codigo_qr.blade.php`: examen, fecha, hora, duración, aula y QR); no lleva las normas ni admite recomendaciones del docente. Falta, por ejemplo, `POST /api/examenes/{examen}/avisos` con `{recomendaciones, grupo, aula}` o sumar `normas` y un texto libre al correo de la 60. La pantalla no ofrece un campo que el servidor ignoraría.
- **Ruta 57 sin filtro por estado de envío** (`?envio=sin_enviar|enviado`): la lista no puede mostrar solo a quienes no lo recibieron; hoy se ve fila por fila. Tampoco expone los envíos `FALLIDO` de `envios_correo` (ni su error): un correo que falla se ve igual que uno que no se envió.
- **Ruta 60, tope de 100 con cola sincrónica**: la última tanda completa los 100 con quienes ya lo recibieron (les llega dos veces), y «reenviar a todos» cuando no queda nadie pendiente envía solo a los primeros 100 y responde `pendientes: 0`. No hay envío por `estudiante_id`: el reenvío individual depende de que `buscar` por código coincida con uno solo.
- **Ruta 59**: no devuelve el correo institucional del estudiante; el diálogo dice «Institucional» y la credencial no lo muestra.
- **Ruta 63**: `examenes[].normas` es solo el texto libre; no incluye las normas marcadas (`examen_norma`, P1), que en la puerta no se ven. Tampoco dice en qué examen del aula actúa la persona (de ahí el reintento de la incidencia).
- **`GET /api/edificios` exige `auth`**: con código de aula daría 401 y el cliente cerraría el acceso, así que en ese caso el veredicto «Otra aula» no pide el mapa (edificio y piso sí se leen). Haría falta admitir ahí al `puesto`.
- **`paginas/auth/AccesoAula.jsx`** (armazón) sigue saliendo con `POST /api/auth/logout` en lugar de `DELETE /api/auth/codigo-aula`, y al recargar no toma `nombre` y `hasta` de `acceso` (ruta 62): lo anotado en B6 sigue pendiente; la puerta muestra «Código de aula» como operador cuando el nombre falta.
- `EloquentPuertaGateway::TIPOS` sin `MESA` (anotado en P1): en la puerta un examen de mesa se lee «MESA».
- No se revisó en un navegador (sin Vite ni compilación): ni la cámara real ni los 390 px. Las clases de ancho son las de la maqueta (`max-w-md`, `min-w-0`, `truncate`).

**Verificación**: `npx vitest run resources/js/paginas/ingreso resources/js/paginas/docente/CodigosQr.test.jsx` 2 archivos, 34 pruebas; eslint y prettier sin observaciones. Contra el servidor (puerto 8055, `leticia.blanco`, `carla.salazar`, `diego.mamani`) se comprobó la forma real de las rutas 57–66.

### P5 · Huecos del seguimiento en vivo (rutas 67, 70, 71 y 72 y sus dos páginas)

Cierra los cuatro pendientes de backend que dejó WF. **Todo es aditivo**: ningún campo existente cambia de nombre, de forma ni de valor.

**Campos nuevos**

- **Ruta 67** (`GET /api/examenes/{examen}/incidencias`), en cada fila: `atendida_por` (nombre de la cuenta que la atendió, `users.name`) y `atendida_en` (instante ISO 8601 con zona, `2026-10-09T16:02:12-04:00`). Los dos son `null` mientras `atendida` es `false`. Los mismos dos campos salen, nulos, en el `data` del 201 de la ruta 68 (comparten la fila). Si la cuenta que atendió ya no existe, `atendida_por` es `null` con `atendida_en` informado.
- **Ruta 70** (`GET /api/en-curso`), en cada examen: `tipo` (valor: `PRIMER_PARCIAL` | `SEGUNDO_PARCIAL` | `FINAL` | `SEGUNDA_INSTANCIA` | `MESA`) y `tipo_texto` (etiqueta: «Primer parcial»…), el mismo par de la ruta 28. `texto` sigue igual.
- **Ruta 71** (`GET /api/examenes/{examen}/en-curso`): `ultimo_ingreso` en la raíz (el último ingreso del examen, por cualquier aula) y `aulas[].ultimo_ingreso` (el último por la puerta de esa aula, el mismo criterio de `ingresados`). Instante ISO 8601 con zona; `null` si no hay ingresos. Va en la raíz y no dentro de `cifras`. Ojo: en esta ruta `examen.tipo` sigue siendo la etiqueta, como antes.
- **Ruta 72** (`GET /api/examenes/{examen}/aulas/{aula}/asistencia`): `buscar` cubre además `estudiantes.documento_identidad` (parcial, sin distinguir mayúsculas). La respuesta no cambia: el documento no se devuelve.

**Piezas**: `Monitoreo/Infrastructure/Persistence/EloquentSeguimientoGateway` (una consulta más por lectura de la 71: el máximo por aula; el del examen va con las cifras), `Monitoreo/Application/DTOs/{ExamenEnCursoData,CifrasEnCursoData,AulaEnCursoData}` (propiedades nuevas al final, con valor por defecto), `Monitoreo/Http/Controllers/EnCursoController` e `Ingreso/Infrastructure/Persistence/EloquentIncidenciaGateway` (un `leftJoin` a `users` y dos claves en `filaDe`). Sin rutas, contratos ni migraciones nuevas.

**Páginas**

- `componentes/Incidencias.jsx` (armazón, cambio mínimo y compatible): `ListaIncidencias` muestra bajo «Atendida» la línea `<nombre> · <día mes>, <HH:MM>` («Blanco Coca Leticia · 9 oct, 16:02») cuando la fila trae `atendida_por` o `atendida_en`; sin esos campos se ve como antes. Sin props nuevas; su prueba no se tocó y sigue en verde. Lo heredan también `ingreso/PuntoControl` y `reportes/ReportesDocente`.
- `docente/EnCurso`: la insignia del encabezado dice `Ingreso abierto · Último registro 16:16:36` (o `· Sin registros`), con la hora de `ultimo_ingreso` y ya no la de la lectura; con el examen sin abrir, `11 oct, 08:15` y, si hubo ingresos, `· Último registro …`. Cada aula muestra «Último registro HH:MM:SS» bajo quien la atiende (en la rejilla compacta, «Último HH:MM» junto a «Faltan N»); un aula sin ingresos no muestra nada. El selector toma `tipo` y `tipo_texto` de la ruta 70 (ya no parte `texto`).
- `monitoreo/Asistencia`: el campo de búsqueda dice «Nombre, código o documento». El subtítulo conserva la hora de la lectura (la ruta 72 no trae último ingreso; no se pidió).

**Verificación**: `tests/Feature/Modules/Monitoreo` + `tests/Feature/Modules/Ingreso` 165 pruebas, 1.005 afirmaciones (Monitoreo 37: `TableroTest` 17, `EnCursoListadoTest` 6, `AsistenciaDeAulaTest` 14; `IncidenciasTest` 20). PHPStan, Pint, deptrac y `quality:modules` sin observaciones. Vitest: `EnCurso.test.jsx` 21, `Asistencia.test.jsx` 15, `Incidencias.test.jsx` 8 (sin cambios); eslint y prettier limpios. Contra el servidor (puerto 8059, solo lecturas, `leticia.blanco` y `diego.mamani`): los cuatro campos con datos reales, incluida la búsqueda por un documento del padrón.

**Pendiente**: no se revisó en un navegador (sin Vite ni compilación); la línea de autoría y la insignia más larga se cuidaron con `truncate`/`min-w-0`, sin comprobación visual a 390 px.

### P4 · Reportes que faltaban (incidencias del examen, resumen por estudiante, examen intentado)

Cierra los tres huecos que anotó WG (HU-23 criterio 4; HU-24 criterios 4 y 5). Todo dentro de `app/Modules/Reportes/**`, `routes/reportes.php`, sus pruebas y `resources/js/paginas/reportes/**`. Son lecturas: no asientan bitácora (no hay nada nuevo que rotular en Administración).

**Rutas nuevas** (dos rutas literales más, `reportes.<clave>.exportar`; el total pasa a 82)

| Método y ruta | Permiso | Controlador |
|---|---|---|
| `GET /api/reportes/incidencias/exportar` | `reportes_examenes` + docente del examen | `ReporteDocenteController::exportar` (`reportes.incidencias.exportar`) |
| `GET /api/reportes/estudiantes/exportar` | `reportes_universidad` | `ReporteUniversidadController::exportar` (`reportes.estudiantes.exportar`) |

- **Incidencias del examen.** `?examen=<id>&formato=pdf|xlsx` (sin `formato`, PDF) → `incidencias_<fecha del examen>.pdf|xlsx`. Mismos rechazos que las 78–79: 422 sin `examen` o con formato desconocido, 404 `Examen no encontrado.`, 403 `{"message":"Este examen no es tuyo.","alcance":true}`, 409 `{"codigo":"SIN_RENDIR"}` antes de la fecha. Un examen sin nada asentado da el archivo vacío (`0 registros.`), no un error. El encabezado lleva el examen y la fecha y hora de emisión.
  - Columnas: Hora (`HH:MM:SS`) · Aula · Código · Estudiante · Tipo · Detalle · Registrado por. Orden por hora.
  - Filas: todas las incidencias con `examen_id` del examen (las de `PERSONA`: reportadas, expulsiones y situaciones excepcionales; y las de `SISTEMA`) **más** los rechazos de la puerta con `examen_id` nulo ocurridos en una de sus aulas dentro de su ventana (la misma regla que el reporte de intentos; en un aula compartida ese rechazo figura en los dos exámenes).
  - «Tipo»: la etiqueta de `incidencias.tipo`; el rechazo de la puerta agrega su motivo (`Intento no autorizado · No habilitado`). Sin estudiante identificado, «Estudiante» lleva `estudiante_texto`. «Registrado por»: nombre de docente o de la cuenta de `reportada_por`; con código de aula, `<nombre> (código de aula)`; un rechazo sin operador, `Sistema`.
  - Totales: registros, intentos de ingreso no autorizados (las de `SISTEMA`), situaciones excepcionales (tipo `CAMBIO_AULA`), expulsiones e incidencias reportadas (el resto). Las otras dos excepciones de la puerta (problema de identificación, no habilitado) se guardan como `OTRA` e `INTENTO_NO_AUTORIZADO` de `PERSONA`, sin marca que las distinga: cuentan como reportadas.
- **Resumen por estudiante.** `?formato=pdf|xlsx` + los filtros de la 73 (`periodo`, `facultad`, `buscar`, `desde`, `hasta`, o `examen`) → `resumen_estudiantes_<examenN|sigla|umss>_<hoy>.pdf|xlsx`. 422 de validación y 409 `{"codigo":"SIN_DATOS"}` como las 74–76. Los filtros eligen **exámenes** (`buscar` sigue mirando asignatura y docente, no al estudiante).
  - Una fila por estudiante, por apellidos y nombres: Código · Estudiante · Carrera · Materias · Exámenes · Ingresos · Intentos · Expulsiones · Incidencias.
  - Figura quien está inscrito en un grupo de un examen del alcance, tiene condición registrada (habilitado o no) o ingresó, y además quien fue identificado en algo asentado (por ejemplo, un ajeno rechazado en la puerta: sale con 0 exámenes y 1 intento).
  - «Exámenes» cuenta los del alcance que le tocaban o a los que ingresó; «Materias», sus asignaturas; «Intentos», los rechazos de la puerta (`SISTEMA`, con la regla de aula y ventana); «Incidencias», lo reportado por una persona sin las expulsiones (incluye los cambios de aula). Lo asentado sin estudiante identificado no entra en ninguna fila.

**Cambios en rutas existentes** (campos agregados; nada se quitó)

- **73** `meta.cifras.estudiantes`: cuántas filas tendría el resumen por estudiante en ese alcance (la cifra de su tarjeta).
- **77** cada examen trae `incidencias`: las filas de su reporte de incidencias (`null` sin rendir).
- **76** la tabla de intentos tiene una columna más: Hora · Código · Estudiante · Aula · **Examen al que intentó ingresar** · Examen que le correspondía · Motivo. El examen intentado es el de `incidencias.examen_id`; si la puerta no lo pudo atribuir (así asienta `VerificarEnPuerta` los `NO_INSCRITO` y `CODIGO_INVALIDO`), los exámenes que usaban esa aula a esa hora, separados por `; `; si no hay ninguno, `Ninguno a esa hora`.

**El PDF ya no pasa por HTML.** `ExportadorReporteArchivo::pdf` dibuja la tabla fila por fila sobre el lienzo de Dompdf (`Dompdf\Adapter\CPDF`): anchos de columna según el contenido, texto que baja de renglón, títulos repetidos en cada hoja y `Página N de M`. Con la tabla HTML, Dompdf agotaba los 128 MB a partir de unas 350 filas (medido: 500 filas 186 MB; 1.071 filas 592 MB y 10 s; 2.000 filas 1,8 GB y 34 s) y el resumen de toda la universidad respondía 500. Ahora 2.000 filas son 0,2 s y 37 MB. Vale para los siete reportes (mismo contenido, mismo orden; cambia el dibujo). Por eso las descargas extensas siguen el mecanismo de las demás: respuesta directa, y en la página solo queda deshabilitado el botón que la pidió.

**Estructura.** `Application/DTOs/{FilaIncidencia,FilaResumenEstudiante}`; `ReporteGateway::{contarEstudiantes,resumenPorEstudiante,incidenciasDeExamen}`; `FilaIntento::examenIntentado`; `FilaMiExamen::incidencias`. Las etiquetas de `TipoIncidencia` se repiten en `ArmarTabla::INCIDENCIAS` (no son públicas entre módulos). De otros módulos solo se lee con `DB::table` (`incidencias`, `accesos_aula`, `inscripciones`, `examen_grupo`, `habilitaciones`, `ingresos`).

**Páginas.** `ReportesDocente.jsx`: tercera tarjeta «Incidencias del examen» (`N registros`, PDF y hoja de cálculo, deshabilitada sin rendir); no depende de `seguimiento_vivo`. La tarjeta «Incidencias» de solo lectura sigue igual (ruta 67: solo lo reportado por personas, por eso su número puede ser menor). `Reportes.jsx`: cuarta tarjeta «Resumen por estudiante» con `meta.cifras.estudiantes`; la fila de tarjetas pasa a cuatro columnas desde `xl`.

**Verificación.** `tests/Feature/Modules/Reportes`: 55 pruebas, 533 afirmaciones (6 nuevas; base `control_ingreso_testing_pa1`). PHPStan, Pint, deptrac (0 violaciones, 0 sin cubrir), `quality:modules` y `quality:structure` sin observaciones. Vitest: 27 pruebas (`Reportes.test.jsx` 13, `ReportesDocente.test.jsx` 14); eslint y prettier limpios. Contra el servidor (puerto 8058, `leticia.blanco` y `administracion.academica`, solo lecturas y descargas): incidencias del examen de hoy en PDF y XLSX (11 registros: 5 intentos, 2 cambios de aula, 1 expulsión, 3 reportadas), 409 `SIN_RENDIR`, 422, 404, 403 de alcance y de permiso cruzado; resumen por estudiante de toda la universidad (1.071 estudiantes, PDF de 49 hojas en 0,2 s), por examen y con todos los filtros, 409 `SIN_DATOS`; intentos con la columna nueva (incluido un rechazo sin examen en un aula compartida, que nombra los dos exámenes); rutas 73 y 77 con los campos nuevos.

**Pendiente**

- El resumen no tiene filtro por estudiante (`estudiante=` o búsqueda por nombre o código): para un caso puntual se filtra en la hoja de cálculo.
- No se revisó en un navegador (sin Vite ni compilación): las dos tarjetas nuevas reutilizan las clases de las existentes, sin comprobación visual a 390 px.
- Sigue lo anotado por B8: «hoy», `rendido` y las horas dependen de la zona horaria de la aplicación.

### P7 · Huecos de la atención de aulas (HU-18: Exámenes y «Registrar examen»)

Cierra los cuatro pendientes que dejó WC. Archivos: `app/Modules/Examenes/**`, `routes/examenes.php`, `tests/Feature/Modules/Examenes/**`, `resources/js/paginas/examenes/{QuienAtiende,CodigoDeAula,RegistrarExamen}.jsx` (+ prueba) y una línea en `ConsultarBitacora::TEXTOS` (`auxiliar.quitar` → «Quitó al auxiliar»). Sin migraciones.

**1. Docente de un grupo sumado, a cargo de un aula**

- Ruta 48 (`GET /api/examenes/opciones/grupos`): cada grupo de `propios` y `otros` suma `"docente_usuario_id": 76` — el id de la cuenta (`users.id`) del docente del grupo; `null` si el grupo no tiene docente, el docente no tiene cuenta o la cuenta está bloqueada (`is_active = false`). El mismo campo sale en `grupos_detalle` de las rutas 44, 45 y 46. `GrupoDeExamenData` suma `docenteUsuarioId` al final, con valor por defecto.
- El alta y la edición ya aceptaban como `encargado_id` cualquier cuenta de docente o de auxiliar (no hizo falta tocar `ComprobarDatosDeExamen`); queda cubierto por una prueba con el docente de un grupo sumado, al registrar y al modificar.
- Página: «A cargo» ofrece a quien registra, a los docentes con cuenta de los grupos elegidos, a los docentes que ya atienden el examen y a los auxiliares. Un docente no se ofrece como apoyo. Al quitar un grupo (o cambiar de asignatura) las aulas que tenía a cargo su docente vuelven a quien registra.

**2. Quitar un auxiliar de la lista**

- Ruta nueva `DELETE /api/auxiliares/{auxiliar}` (`auxiliares.destroy`, `auth` + `permiso:examenes`); `{auxiliar}` es el id de la **cuenta**, el mismo `id` de las rutas 50 y 51.
  - 204 sin cuerpo: `auxiliares.registrado_por` pasa a `null`. No se borra la cuenta, ni la fila de `auxiliares`, ni lo que atendió. Asienta `auxiliar.quitar` (tabla `auxiliares`, `registro_id` = id de la fila).
  - 409 `{"message":"Atiende un examen pendiente.","codigo":"AUXILIAR_ASIGNADO"}`: la cuenta está a cargo o de apoyo en un aula de un examen que registró quien llama y que no terminó (`fecha + hora_inicio + duración > ahora`). Se comprueba antes que lo demás.
  - 404 `{"message":"Auxiliar no encontrado."}`: la cuenta no existe, no es un auxiliar o no la registró quien llama (también al repetir la baja).
- **Cambio en la ruta 50 sin `buscar`** (la forma no cambia; cambia qué filas salen): «mío» era «lo registré **o** lo puse alguna vez en un aula de un examen mío»; ahora la segunda parte cuenta solo los exámenes **que no terminaron**. Sin eso, quitar a un auxiliar que atendió un examen ya rendido no lo sacaba de la lista. Consecuencia: el auxiliar de otro docente que se usó en un examen ya rendido deja de salir entre los propios y hay que volver a buscarlo (ruta 50 con `buscar`, donde sale con `mio: false`). El `mio` de la búsqueda y del 409 de la ruta 51 sigue la misma regla.
- Página: cada auxiliar de la cuenta y cada sumado en la visita lleva una equis («Quitar a X de la lista») que abre el diálogo «Quitar auxiliar» (nombre, «Cancelar» / «Quitar»). El de la cuenta envía el `DELETE`; el sumado en la visita solo sale de la pantalla. Al quitarlo deja las aulas donde estaba (a cargo → quien registra). El 409 se muestra como aviso («Atiende un examen pendiente»): primero hay que sacarlo del aula y guardar el examen. Quien solo figura porque ya atiende el examen guardado no lleva equis.

**3. Mensajes del alta de auxiliar (ruta 51)**: `nombre` → `Obligatorio`, `No válido`, `Entre 3 y 120 caracteres`; `codigo_universitario` → `Obligatorio`, `Entre 5 y 10 dígitos`, `Ya existe` (el correo `<código>@est.umss.edu` ya es de otra cuenta). El 409 `Auxiliar ya registrado.` no cambia.

**4. El código de aula ya no se genera al consultarlo**

- Ruta 52 (`GET …/aulas/{aula}/codigo`): no escribe ni asienta nada. Sin código responde 200 `{"data":{"codigo":null,"generado_en":null,"encargado":"…","examen":"2026-11-28 10:00"}}`; con código, lo de antes.
- Ruta 53 (`POST`, mismo camino): genera el primero o reemplaza el vigente. `message` es `Código generado.` la primera vez y `Código regenerado.` después; siempre 200 con la misma `data`. La bitácora sigue distinguiendo `codigo_aula.generar` y `codigo_aula.regenerar`.
- Nadie más consumía la ruta 52 en `resources/js` (el seguimiento y la puerta no la usan; `auth/AccesoAula` usa `POST /api/auth/codigo-aula`, que es de Ingreso). `CodigoAulaGateway::generar(…, false)` queda sin llamadores.
- Diálogo «Código de acceso»: sin código muestra «Sin código» y el botón «Generar»; con código, «Regenerar» y «Copiar». La llave del aula toma color solo cuando hay código.

**Verificación**: `tests/Feature/Modules/Examenes` 99 pruebas (92 + 7: `AuxiliaresTest` 16, `CodigoDeAulaTest` 9, `OpcionesDeGruposTest` 5, `RegistroExamenTest` 24) y `tests/Feature/Modules/Administracion` 233, en verde; phpstan, pint y deptrac sin observaciones; `npx vitest run resources/js/paginas/examenes` 80 pruebas (Registrar examen 47); eslint y prettier limpios. Contra el servidor (puerto 8061, `erika.rodriguez` y `leticia.blanco`): `docente_usuario_id` en la ruta 48 y en `grupos_detalle`; examen de mesa de prueba con un aula a cargo de la docente del grupo sumado; código nulo → generado → regenerado; 422 breves; alta de un auxiliar de prueba, 409 al quitarlo estando de apoyo, 204 tras sacarlo del aula y 404 al repetir. El examen y la cuenta de prueba se borraron; quedaron sus asientos en la bitácora.

**Pendiente**

- `ConsultarBitacora::TEXTOS` sigue sin `codigo_aula.generar` (se muestra tal cual; `codigo_aula.regenerar` sí tiene texto).
- La baja no deja rastro en `auxiliares` de quién lo había registrado (queda en la bitácora: `auxiliar.registrar` y `auxiliar.quitar`). Una cuenta de rol Auxiliar creada por el administrador (sin fila en `auxiliares`) no es de nadie: no se puede quitar, y solo aparece en la lista mientras atiende un examen pendiente.
- No se revisó en un navegador (sin Vite): la equis de cada auxiliar mide 40 px dentro de la misma píldora, con `truncate` en el nombre.

### P6 · Huecos de Códigos QR y Punto de control (HU-19 y HU-20)

Cierra los pendientes de backend que dejó WE y los refleja en sus páginas. **Todo es aditivo**: ninguna ruta existente pierde ni cambia un campo. Sin migración.

**Rutas nuevas** (`routes/ingreso.php`)

- `POST /api/examenes/{examen}/codigos-qr/avisos` (`codigos-qr.avisar`; `auth` + `permiso:codigos_qr` + docente del examen). Cuerpo, todo opcional: `{"recomendaciones":"…","grupo":37,"aula":32,"buscar":"…","envio":"…","tanda":"…"}`. Avisa a los **habilitados** del filtro, tengan o no código emitido (vacío = todos). El correo (`correos/aviso_examen.blade.php`, asunto `Aviso de examen · <tipo> · <asignatura>`) lleva examen, estudiante, fecha, hora, duración, aula con edificio y piso (o «Sin aula»), las normas marcadas (lista, en su orden), las normas en texto libre y las recomendaciones; no lleva el QR ni marca `codigos_qr.enviado_en`. 202 `{"message":"44 avisos enviados.","encolados":44,"pendientes":0,"tanda":"2026-10-09T16:31:43-04:00"}` (`1 aviso enviado.`). 409 `{"codigo":"SIN_HABILITADOS"}` si el filtro no deja a nadie; 422 `errors.recomendaciones` «Máximo 2000 caracteres», `errors.tanda` «La tanda no es válida.», y los de los filtros; 403 y 404 como las demás rutas de códigos. Cada correo deja su fila en `envios_correo` con **`tipo: "AVISO"`** (`PENDIENTE` → `ENVIADO` | `FALLIDO` con el error) y la petición asienta `aviso.enviar` sobre `examenes` (`ConsultarBitacora::TEXTOS`: «Envió avisos del examen»).
- `POST /api/examenes/{examen}/codigos-qr/{estudiante}/envio` (`codigos-qr.reenviar`; mismo permiso y alcance), sin cuerpo: envía o reenvía el código de **un** estudiante, lo haya recibido o no. 202 `{"message":"1 código enviado.","encolados":1,"pendientes":0,"correo":"202105877@est.umss.edu"}`; 404 `El estudiante no está habilitado en este examen.`; 409 `SIN_EMITIR` si no tiene código. Registra `envios_correo` y `qr.enviar` como la ruta 60.
- `GET /api/puerta/aulas/{aula}/edificios` (`puerta.edificios`; middleware `puesto` + atiende el aula, como la 63). Responde **la misma forma que `GET /api/edificios`**: `{"data":[{id,clave,facultad,nombre,poligono,centro,aulas,pisos}],"meta":{"caja":{"lon":[min,max],"lat":[min,max]}|null}}`, con `Cache-Control: private, max-age=3600`, pero solo con los edificios de las facultades donde están esa aula y las demás aulas de sus exámenes del día (por clave de facultad). La alcanza quien entró con código de aula (solo la suya: otra da 403 `alcance`); sin sesión ni acceso, 401. `/api/edificios` no se tocó. Usa `Academico\Application\Contracts\ConsultaAulasGateway::edificios()`.

**Campos y parámetros nuevos en rutas existentes**

- **Ruta 57**: cada fila suma `correo` (`<código>@est.umss.edu`), `envio` (`sin_emitir` | `sin_enviar` | `enviado` | `fallido`) y `error` (texto del último fallo; `null` salvo en `fallido`). `meta` suma `fallidos`. Parámetro nuevo `?envio=` con esos cuatro valores (422 `errors.envio` «El estado de envío no es válido.»); los conteos de `meta` no dependen del filtro. Definición: `enviado` = `codigos_qr.enviado_en` con valor; `fallido` = con código, sin `enviado_en` y con el **último** `envios_correo` de tipo `CODIGO_QR` en `FALLIDO`; `sin_enviar` = el resto de los emitidos (incluye el correo que sigue en la cola). «Sin enviar» de la pantalla = `emitidos − enviados − fallidos`.
- **Ruta 59**: suma `correo`.
- **Ruta 60**: admite `envio` (los mismos filtros de la lista; `{"envio":"fallido"}` reintenta solo los fallidos) y `tanda`; responde además `tanda`. Con cola sincrónica (tope de 100 por petición): **mientras quede alguien del filtro sin recibirlo, la tanda es solo de esos** (ya no se completa con quienes lo recibieron) y `pendientes` son los que quedan sin recibirlo; cuando ya lo recibieron todos, es un reenvío a todos que avanza de 100 en 100: el cliente devuelve en cada vuelta la `tanda` de la primera respuesta, el servidor salta a quien ya tiene un envío registrado desde ese instante y `pendientes` cuenta los que faltan de esa tanda. Una tanda ya terminada responde 202 con `encolados: 0`. Con cola real no cambia nada: van todos de una vez y `pendientes` es 0. Sigue valiendo la advertencia de B6 para el primer caso (si los correos fallan, `pendientes` no baja).
- **Ruta 61**: admite `envio` (comparte `FiltroCodigosRequest`).
- **Ruta 63**: cada examen suma `normas_marcadas`: lista de **textos** en el orden guardado (`examen_norma.orden`), `[]` si no hay. `normas` (texto libre) sigue igual.
- `EloquentPuertaGateway::TIPOS` ya tenía `MESA` → «Examen de mesa» (hay una prueba).
- Los avisos usan la misma `tanda` desde la primera vuelta: con cola sincrónica van de 100 en 100 sin repetir, y como el fallo también deja fila, `pendientes` siempre baja.

**Piezas**: `Application/Actions/{EnviarAvisos,ReenviarCodigoQr}`, `Application/Queries/ConsultarMapaDePuerta`, `Application/DTOs/AvisoData`, `Http/Requests/EnviarAvisoRequest`, `Infrastructure/Mail/AvisoExamenMail`. `CredencialData` suma `correo`, `ExamenData` suma `normasMarcadas` y `FiltroCodigosData` suma `envio` (al final, con valor por defecto). `CodigoQrGateway`: `resumen()` trae `fallidos`; `emitidas()` suma `$soloSinEnviar` y `$desde`; nuevos `porReenviar`, `paraAvisar` y `porAvisar`. `CorreoQrGateway::avisar()`. `PuertaGateway::facultadesDelMapa()`. El dominio del correo vive ahora en `LeeFilas::correoInstitucional()`.

**Páginas**

- `docente/CodigosQr`: quinta cifra «Fallidos»; filtro «Envío» (Sin emitir, Sin enviar, Enviados, Fallidos) que viaja como `envio` a la lista, al envío y a la hoja; insignia «Fallido» por fila. La credencial muestra el correo institucional, el error del envío fallido y «Reenviar», que llama a la ruta por estudiante. Botón «Enviar aviso» → diálogo «Aviso por correo» (examen, fecha, destinatarios del filtro, campo «Recomendaciones» con contador hasta 2.000); al terminar avisa «N avisos enviados» y deja la línea `Aviso · <fecha y hora> · N enviados`. Los dos envíos comparten `enTandas()`, que devuelve la `tanda`.
- `ingreso/PuntoControl`: «Normas» lista primero las marcadas (`role="list"`, «Normas marcadas») y después el texto libre; «Sin normas» solo si faltan las dos.
- `ingreso/VeredictoPuerta`: el mapa de «Otra aula» lee `GET /puerta/aulas/{aula}/edificios` en lugar de `/edificios`, también con código de aula (`conMapa` queda como opción, por defecto encendida).
- `auth/AccesoAula` (armazón, cambio mínimo): «Salir» llama a `DELETE /api/auth/codigo-aula`; al recargar toma `acceso` de la ruta 62 (nombre declarado y `hasta`) y la cabecera suma `· Hasta HH:MM`. El contexto no cambió.

**Para el integrador**

- `Administracion\Domain\Enums\TipoCorreo` no tiene `AVISO`: si `EnvioCorreo` convierte `tipo` con esa enumeración, leer una fila de aviso con el modelo fallaría (hoy nadie lo hace; Ingreso escribe y lee por nombre de tabla). Basta añadir `case Aviso = 'AVISO'`.
- Los avisos van a los habilitados; un inscrito sin habilitar o sin revisar no lo recibe.
- Con una cola real el correo encolado se ve «Sin enviar» hasta que el trabajador lo despacha (no hay estado «en cola»).
- La ruta 63 sigue sin decir en qué examen del aula actúa la persona (la incidencia desde la puerta sigue probando examen por examen).
- No se revisó en un navegador (sin Vite ni compilación): el diálogo del aviso y la quinta cifra (`grid-cols-2 sm:grid-cols-5`) no tienen comprobación visual a 390 px.

**Verificación**: `tests/Feature/Modules/Ingreso` 149 pruebas, 937 afirmaciones (nuevas: `AvisosDeExamenTest` 8, `EstadoDeEnviosTest` 8, `MapaYNormasDePuertaTest` 5). PHPStan, Pint, deptrac (0 violaciones, 0 sin cubrir) y `quality:modules` (0 violaciones) sin observaciones. Vitest: `CodigosQr.test.jsx` 18, `PuntoControl.test.jsx` 20, `AccesoAula.test.jsx` 10; eslint y prettier limpios. Contra el servidor (puerto 8060): `leticia.blanco` rutas 57 (con `envio` en sus cuatro valores y 422), 59 y un aviso al examen 3 (44 correos, visto en el registro de correo); `carla.salazar` y `diego.mamani` rutas 63 y el mapa de la puerta (200 en su aula con 21 edificios de FCyT, 403 en la ajena, 404, 401 sin sesión). En la base de desarrollo quedaron 44 filas `AVISO` en `envios_correo` (examen 3) y el asiento `aviso.enviar`.
