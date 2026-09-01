# Arquitectura del sistema

## 1. Objeto

Sistema informático para la gestión, validación y control del ingreso de estudiantes
durante la realización de exámenes masivos, desarrollado para la empresa TIS conforme al
pliego de especificaciones PETIS-452026-2026.

La función central del sistema consiste en registrar el acceso de cada estudiante al
ambiente de evaluación, previa verificación de su identidad, de su habilitación para el
examen y del ambiente que le corresponde. Los módulos restantes proveen los datos que
alimentan ese registro o explotan la información resultante.

## 2. Estructura en capas

| Capa | Responsabilidad | Tecnología |
|---|---|---|
| Presentación | Interfaz responsiva para escritorio y dispositivos móviles; lectura de códigos QR mediante la cámara del dispositivo | React con Tailwind CSS |
| Lógica de negocio | Validación del ingreso, gestión de habilitaciones, generación de reportes, control de acceso y registro de auditoría | PHP 8.2 con Laravel, sobre Apache |
| Persistencia | Almacenamiento transaccional con integridad referencial | PostgreSQL 15 |

La capa de presentación no constituye una aplicación independiente: sus recursos se
compilan y se sirven como archivos estáticos desde el directorio público de Laravel. El
sistema no emplea renderizado en servidor, dado que el entorno de destino no admite la
ejecución permanente de procesos Node sin contenedores.

## 3. Versiones

Las versiones se determinan por las disponibles en el servidor de destino
(`tis.cs.umss.edu.bo`), conforme al punto 3.3 del pliego.

| Componente | Servidor | Adoptada |
|---|---|---|
| Lenguaje | PHP 7.4.22 y 8.2 | PHP 8.2 |
| Base de datos | PostgreSQL 15.10; MariaDB 10.11.6 | PostgreSQL 15 |
| Servidor web | Apache 2.4.62 | Apache 2.4 |
| Marco de trabajo | Laravel 8--11 | Laravel 11 (11.56.1) |
| Entorno JavaScript | Node 20.19.0 | Node 20.19.0, únicamente para compilación |

El archivo `composer.json` fija la plataforma en PHP 8.2.0 y el archivo `.nvmrc`
establece la versión de Node, de modo que la resolución de dependencias sea equivalente
en todos los equipos de desarrollo y en la integración continua.

### 3.1. Excepción de seguridad en Laravel 11

El marco no se instala en el servidor: viaja dentro de `vendor/` en la transferencia por
FTP. Se adopta Laravel 11 por corresponder al rango que el pliego declara sostenido, aun
cuando su ventana de soporte de seguridad concluyó en marzo de 2026 y la última versión
publicada, 11.56.1, es también la última que existirá.

Tres avisos de seguridad afectan a la totalidad de la rama 11 y no tienen corrección
dentro de ella. Composer bloquea la instalación mientras no se declaren de forma
explícita, por lo que constan en `composer.json` bajo `config.policy.advisories.ignore-id`:

| Aviso | Asunto | Corregido en |
|---|---|---|
| `PKSA-mdq4-51ck-6kdq` (CVE-2026-48019) | Inyección CRLF en la regla de validación `email` | Laravel 12.60.0 |
| `PKSA-3r5d-mb8f-1qw9` | Inyección CRLF en la regla de validación `email` | Laravel 12.60.0 |
| `PKSA-m5cs-t1y6-qpcs` | Confusión de rutas en URLs firmadas temporales | Laravel 12.61.1 |

Ambos asuntos alcanzan a piezas que el sistema emplea. Mientras la excepción siga
vigente, la validación de direcciones de correo rechaza los caracteres de control antes
de aplicar la regla del marco, y el control de acceso no se apoya en URLs firmadas
temporales como única credencial.

La restricción proviene del punto 3.3 del pliego, no de una limitación técnica del
servidor, que solo aporta PHP 8.2 y Apache. Corresponde plantear a la administración del
servidor y a la consultora la adopción de Laravel 12, que corrige los tres avisos y
mantiene el mismo requisito de PHP; de aceptarse, se retira esta excepción.

Queda pendiente de confirmación con la administración del servidor la disponibilidad de
PostgreSQL en el alojamiento compartido, cuyo panel administra bases de datos mediante
phpMyAdmin. En caso negativo, la configuración se ajusta a MariaDB mediante el archivo
de entorno; las migraciones de Laravel son independientes del motor.

## 4. Decisiones de diseño

### 4.1. Control de unicidad del ingreso

El requerimiento 8 del pliego exige impedir que un estudiante registre su ingreso más de
una vez para el mismo examen. La verificación previa a la inserción no ofrece garantía
suficiente ante lecturas simultáneas del mismo documento. En consecuencia, la restricción
se implementa como índice único sobre el par examen–estudiante en la base de datos, de
modo que el segundo intento sea rechazado por el motor.

### 4.2. Inmutabilidad del registro

El pliego establece que los registros de ingreso no pueden ser modificados por usuarios
sin los permisos correspondientes. La tabla de ingresos admite exclusivamente operaciones
de inserción. Toda corrección se materializa mediante un registro de anulación que
conserva el original; ambos quedan asentados en la bitácora de auditoría.

## 5. Organización del código

La estructura sigue las convenciones de Laravel, con agrupación por módulo funcional
dentro de cada capa.

```
app/
├── Models/                    entidades del dominio
├── Http/
│   ├── Controllers/
│   │   ├── Estudiantes/       M1
│   │   ├── Examenes/          M2
│   │   ├── Habilitacion/      M3
│   │   ├── Ingreso/           M4
│   │   ├── Monitoreo/         M5
│   │   ├── Reportes/          M6
│   │   └── Admin/             M7
│   ├── Requests/              validación de entrada
│   └── Middleware/
├── Services/                  lógica de negocio no atribuible a un controlador
└── Policies/                  autorización por entidad

resources/js/
├── paginas/                   una carpeta por módulo
├── componentes/               elementos compartidos
└── app.jsx                    punto de entrada

routes/
├── web.php                    vista raíz y ruta de reserva del cliente
└── api.php                    servicios consumidos por la capa de presentación
```

## 6. Módulos y trazabilidad

| Mód. | Denominación | Requerimientos del pliego |
|---|---|---|
| M1 | Gestión de estudiantes | 1 |
| M2 | Gestión de exámenes y ambientes | 2, 10 |
| M3 | Habilitación | 3, 6 |
| M4 | Control de ingreso | 4, 5, 6, 7, 8, 9, 14, 15 |
| M5 | Monitoreo en tiempo real | 11 |
| M6 | Reportes | 12, 13 |
| M7 | Administración y seguridad | 16 |

## 7. Condicionantes del entorno de destino

El alojamiento asignado carece de consola remota. De ello se derivan tres condiciones de
obligado cumplimiento:

1. Las dependencias se resuelven en el equipo de desarrollo y se incorporan al paquete de
   distribución; no se ejecuta `composer install` en el servidor.
2. El esquema de base de datos se aplica mediante la importación de un guion SQL desde el
   panel de administración; las migraciones se ejecutan únicamente en desarrollo.
3. La publicación se realiza por FTP conforme al procedimiento descrito en
   `despliegue.md`.
