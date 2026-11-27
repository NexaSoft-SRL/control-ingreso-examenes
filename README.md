# Sistema de control de ingreso a exámenes

Sistema de **NexaSoft S.R.L.** para gestionar, validar y controlar el ingreso de estudiantes
durante exámenes masivos: habilitación por el docente, códigos QR, punto de control en la
puerta (también sin conexión), seguimiento en vivo, incidencias, reportes y respaldo.

Las **30 historias de usuario del catálogo vigente** están construidas, con su código,
pruebas, fichas y documentación. El avance historia por historia está en
[`docs/guia/avance.md`](docs/guia/avance.md).

## Plataforma

PHP 8.2 · Laravel 11 · React 19 · Tailwind CSS 4 · PostgreSQL 15.10 · Vite

Las versiones se corresponden con las del servidor de destino. Ver
[`docs/arquitectura.md`](docs/arquitectura.md).

## Puesta en marcha

```sh
composer install && npm install

php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate

docker compose up -d                 # PostgreSQL 15.10 (base y puerto en docker-compose.yml y .env.example)
php artisan migrate
composer datos:demo                  # datos de demostración

npm run dev                          # Laravel y Vite, con recarga en caliente
```

La aplicación queda en `http://localhost:8000`.
Para iniciar también el trabajador de la cola de correos, ejecuta `composer dev`.

La base de pruebas se crea una sola vez dentro del contenedor:

```sh
docker compose exec postgres createdb -U nexasoft control_ingreso_testing
```

## Usuarios de prueba

Los carga `composer datos:demo`. Se entra con **usuario** y contraseña; el correo es solo
un dato de contacto. Cada cuenta ve únicamente las pantallas que su rol tiene habilitadas.

| Rol | Usuario | Contraseña | Para ver |
|---|---|---|---|
| Administrador | `admin` | `Admin12345` | Período, oferta, aulas, docentes, padrón, reportes de la universidad, usuarios y roles, bitácora y respaldo |
| Docente | `leticia blanco` | `Docente12345` | Sus exámenes (uno en curso hoy), habilitación, códigos QR, seguimiento, punto de control, sus grupos y sus reportes |
| Docente | nombre y apellido del docente con más asignaturas | `Docente12345` | Un docente con 18 exámenes en 6 asignaturas |
| Docente | `carla salazar` | `Docente12345` | A cargo de un aula en el examen de hoy de otra docente |
| Docente | `erika rodriguez` | `Docente12345` | Registró un parcial conjunto con grupos de otros docentes |
| Docente | `corina flores` | `Fa7-Kmq4-Ru9` | Contraseña temporal: cae en el cambio obligatorio |
| Auxiliar | `diego mamani` | `Auxiliar12345` | Punto de control y lista de su aula en el examen de hoy |

Los roles de inicio son tres (Administrador, Docente y Auxiliar) y el administrador puede
crear otros. El examen «de hoy» se fecha al sembrar: para tener uno en curso hay que
volver a ejecutar `composer datos:demo` el día de la demostración.

## Datos de prueba

Tres sembradores. Los tres **rehacen la base** desde cero.

```sh
composer datos:demo          # datos de demostración
composer datos:volumen       # los mismos datos, con más carga
composer datos:consultora    # escenario chico, con pocos usuarios y casos con sentido
```

**`DemoSeeder`** importa la oferta real del período 2/2026 de las cuatro facultades
(3.169 grupos, 847 docentes y 281 aulas con su ubicación) y deja un padrón ficticio de
4.218 estudiantes con sus inscripciones y 23 exámenes en todos los estados: sin aulas,
sin habilitar, listos, y uno en curso el día en que se siembra, con ingresos, una
expulsión, cambios de aula, incidencias atendidas y sin atender, códigos QR emitidos y
enviados, y un aula compartida con otro examen.

**`VolumenSeeder`** agrega carga sobre esos datos, para ver las pantallas con la cantidad
de filas del día de un examen masivo. Su cabecera detalla qué añade.

**`ConsultoraSeeder`** deja siete cuentas (administrador, tres docentes, la consultora y dos
auxiliares), 157 estudiantes (con y sin carnet y foto) y cuatro exámenes: uno rendido ayer
con incidencias, intentos rechazados y modo sin conexión usado, uno de mañana con los
códigos QR sin enviar, un parcial conjunto a medio habilitar y un segundo parcial sin aulas.

Para probar las cargas hay archivos de ejemplo en `docs/ejemplos/`.

## Comandos

### Controles de calidad

```sh
composer quality:backend     # composer, estructura, capas, módulos, phpstan, pint y pruebas
composer test:postgresql     # pruebas que exigen PostgreSQL real
npm run quality              # eslint, prettier, vitest y compilación
```

Una historia no se da por terminada si alguno falla. Hoy: **1.091 pruebas de backend** y
866 de frontend.

### Durante el desarrollo

```sh
npm run dev                       # Laravel y recursos, con recarga en caliente
composer dev                      # además, trabajador de la cola de correos
php artisan test --filter=NombreDeLaPrueba
vendor/bin/pint                   # formatea el PHP
npx prettier --write resources/js # formatea el JavaScript
```

### Operación

```sh
php artisan respaldo:generar      # respaldo de datos a demanda (HU-35)
php artisan schedule:list         # tareas programadas: el respaldo nocturno de las 02:00
php artisan migrate:fresh --seed
```

### Fichas de historia de usuario

```sh
cd docs/guia/fichas
sh compilar.sh                    # todas
sh compilar.sh sprint4            # las de un sprint
sh compilar.sh hu24 hu27          # algunas sueltas
```

## Estructura

```
app/Modules/<Módulo>/Domain           Modelos, enums, excepciones y reglas
app/Modules/<Módulo>/Application      Acciones, consultas, contratos y DTOs
app/Modules/<Módulo>/Infrastructure   Persistencia, archivos, correo, QR, exportación
app/Modules/<Módulo>/Http             Controladores, peticiones y middleware
resources/js/paginas/<módulo>         Pantallas de React, una carpeta por módulo
resources/js/componentes              Componentes compartidos
routes/                               Rutas por módulo, todas bajo /api
database/migrations/                  Esquema
tests/                                Pruebas de arquitectura, de característica y de integración
docs/guia/                            Una explicación por historia y sus fichas
```

Los módulos son `Administracion`, `Estudiantes`, `Examenes`, `Habilitacion`, `Ingreso`,
`Monitoreo` y `Reportes`. Entre módulos solo es pública `Application/Contracts` y los
`Application/DTOs` con los que esos contratos hablan; el resto es privado y lo verifica
Deptrac en cada control de calidad.

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/guia/README.md`](docs/guia/README.md) | Cómo se documenta cada historia |
| [`docs/guia/avance.md`](docs/guia/avance.md) | Las historias, su sprint, sus puntos y su ficha |
| [`docs/guia/v1/`](docs/guia/v1/) | Catálogo anterior (35 historias) y sus notas de desarrollo |
| [`docs/guia/fichas/sprintN/`](docs/guia/fichas) | Las fichas en LaTeX y PDF, con sus mockups |
| [`docs/arquitectura.md`](docs/arquitectura.md) | Capas, módulos, versiones y decisiones |
| [`docs/modo-sin-conexion.md`](docs/modo-sin-conexion.md) | Control de la puerta sin internet: flujo, casos y solución |
| [`docs/modelo-datos.md`](docs/modelo-datos.md) | Entidades, decisiones de diseño, restricciones e índices |
| [`docs/convenciones.md`](docs/convenciones.md) | Ramas, mensajes de confirmación y estilo |
| [`docs/despliegue.md`](docs/despliegue.md) | Publicación en el servidor de TIS, respaldo y restauración |

## Condiciones de uso

Desarrollo académico realizado en el marco de la asignatura Taller de Ingeniería de
Software de la Universidad Mayor de San Simón, gestión 2/2026, en respuesta a la
convocatoria CPTIS-452026-2026.
