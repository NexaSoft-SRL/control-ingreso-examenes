# Sistema de control de ingreso a exámenes masivos

<!-- Descomentar al crear el repositorio, sustituyendo <repositorio> por su nombre:
![CI](https://github.com/NexaSoft-SRL/<repositorio>/actions/workflows/ci.yml/badge.svg)
-->

Producto software desarrollado por **NexaSoft S.R.L.** para la empresa TIS, en respuesta
a la convocatoria pública CPTIS-452026-2026 y al pliego de especificaciones
PETIS-452026-2026.

El sistema identifica al estudiante en el ambiente de evaluación, verifica su
habilitación para el examen, comprueba la correspondencia del ambiente asignado y
registra la hora de ingreso de forma inmutable.

## Plataforma

PHP 8.2 · Laravel · React · Tailwind CSS · PostgreSQL 15 · Apache 2.4

Las versiones se corresponden con las disponibles en el servidor de destino. Ver
`docs/arquitectura.md`.

## Requisitos previos

PHP 8.2, Composer, Node 20.19 y Docker; en su defecto, PostgreSQL 15 instalado
localmente.

El procedimiento es válido en macOS, Linux y Windows. En Windows se ejecuta desde
PowerShell y Docker Desktop requiere WSL 2.

## Instalación

```sh
composer install
npm install

php -r "copy('.env.example', '.env');"
php artisan key:generate

docker compose up -d          # PostgreSQL 15 en localhost:5432
php artisan migrate

composer run dev
```

El último comando inicia el servidor de aplicación, la cola de trabajos, el registro de
eventos y el compilador de recursos. La aplicación queda disponible en
`http://localhost:8000`.

El puerto 5173 corresponde al servidor de recursos y no se accede directamente: la
aplicación se sirve desde el puerto 8000.

## Estructura

```
app/Http/Controllers/<módulo>    Controladores por módulo funcional
app/Models/                      Modelos de dominio
app/Services/                    Reglas de negocio
database/migrations/             Definición del esquema
resources/js/paginas/<módulo>    Vistas de React, un directorio por módulo
resources/js/componentes/        Componentes reutilizables
routes/                          Definición de rutas web y de API
tests/                           Pruebas unitarias y de característica
docs/                            Documentación técnica
```

Los módulos son `estudiantes`, `examenes`, `habilitacion`, `ingreso`, `monitoreo`,
`reportes` y `admin`. La correspondencia con los requerimientos del pliego consta en
`docs/arquitectura.md`.

## Verificación previa a la incorporación de cambios

```sh
composer exec -- pint         # estilo de código PHP
npm run lint                  # estilo de código JavaScript
php artisan test              # pruebas
```

Las tres comprobaciones se ejecutan automáticamente en cada solicitud de incorporación.

## Contribución

El procedimiento de trabajo —denominación de ramas, formato de los mensajes de
confirmación y requisitos de revisión— consta en `docs/convenciones.md`. Toda
incorporación a la rama principal requiere la revisión de al menos otro socio.

## Documentación

| Documento | Contenido |
|---|---|
| `docs/arquitectura.md` | Estructura en capas, versiones, organización del código y decisiones de diseño |
| `docs/modelo-datos.md` | Entidades, restricciones e índices |
| `docs/convenciones.md` | Ramas, mensajes de confirmación, solicitudes de incorporación y estilo |
| `docs/despliegue.md` | Procedimiento de publicación en el servidor de TIS |

## Equipo

| Socio | |
|---|---|
| Shawn Brandon Bellido Zeballos | Representante legal |
| Cristian Encinas Cáceres | |
| Wilber Lancea Mamani | |
| Alberto Quispe Ramírez | |
| Jofre Ticona Plata | |
| Rodrigo Velásquez Ricaldez | |
| Neida Zeballos Tejada | |

Cinco iteraciones de dos semanas, del 14 de septiembre al 22 de noviembre de 2026.
Entrega final: 7 de diciembre de 2026.

## Condiciones de uso

Desarrollo académico realizado en el marco de la asignatura Taller de Ingeniería de
Software de la Universidad Mayor de San Simón, gestión 2/2026. Su uso y distribución se
sujetan a lo establecido en el contrato suscrito con la empresa TIS.
