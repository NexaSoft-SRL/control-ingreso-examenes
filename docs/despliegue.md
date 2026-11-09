# Procedimiento de despliegue

Procedimiento establecido por la administración del Laboratorio del Departamento de
Informática y Sistemas para la publicación de proyectos Laravel con cliente React. Se
corresponde con la sección 1.17 de la Parte B de la propuesta.

## 1. Características del entorno

El entorno de destino es un alojamiento compartido que dispone de panel de
administración, acceso por FTP y administrador web de base de datos. No dispone de
consola remota, por lo que no se ejecutan procesos de instalación ni de compilación en el
servidor.

## 2. Requisitos previos

- Credenciales del proyecto: dominio, acceso FTP y datos de la base de datos, entregados
  por la administración del servidor.
- Proyecto compilado sin errores en el entorno de desarrollo.

## 3. Procedimiento

1. **Verificación de la cuenta.** Comprobar que el dominio asignado responde.

2. **Configuración del cliente.** Sustituir la dirección de los servicios, que en
   desarrollo apunta al equipo local, por el dominio del proyecto.

3. **Compilación.** Ejecutar `npm run build`, que genera los archivos estáticos.

4. **Integración.** Copiar los archivos compilados, con excepción del `index`, al
   directorio público del proyecto; el `index` se copia al directorio de vistas.

5. **Configuración de rutas.** Publicar la vista en la ruta raíz e incorporar una ruta de
   reserva que devuelva la misma vista para cualquier dirección. Su omisión ocasiona
   error 404 al recargar una vista interna.

6. **Configuración del entorno.** Consignar en el archivo `.env` el nombre de la base de
   datos, el usuario y la contraseña asignados, con `APP_ENV=production` y
   `APP_DEBUG=false`: con la depuración activa, un error muestra su traza al usuario.
   Configurar también el correo (`MAIL_MAILER` y sus datos): sin él los estudiantes no
   reciben el código de acceso. Este archivo se transfiere al servidor y no se
   incorpora al repositorio.

7. **Transferencia.** Subir el proyecto comprimido y descomprimirlo en el servidor: la
   aplicación en el directorio raíz de la cuenta y el contenido de su directorio público
   en `public_html`, previa eliminación de los archivos predeterminados.

8. **Permisos.** Otorgar permisos de escritura a los directorios `storage/` y
   `bootstrap/cache/`.

   **Límite de subida.** Las fotos y las listas se suben hasta 8 MB. El archivo
   `public/.user.ini` lo fija en servidores con PHP-FPM o CGI; si el servidor usa otro
   modo, ajustar `upload_max_filesize = 8M` y `post_max_size = 10M` desde el panel.

9. **Base de datos.** Importar el guion de creación y carga inicial desde el
   administrador web del panel. Las migraciones se ejecutan en el entorno de desarrollo y
   se exporta el resultado.

10. **Verificación.** Comprobar el ingreso, la navegación y la recarga de vistas en el
    dominio asignado.

## 4. Incidencias frecuentes

| Manifestación | Causa habitual |
|---|---|
| Error 500 sin detalle | Ausencia de permisos de escritura en `storage/` o `bootstrap/cache/` |
| Página sin contenido | El `index` no se ubicó en el directorio de vistas, o la ruta raíz no lo sirve |
| Error 404 al recargar una vista interna | Ausencia de la ruta de reserva del paso 5 |
| Error de conexión con la base de datos | Credenciales incorrectas o motor no coincidente |
| «Archivo demasiado grande» al subir una foto o una lista | Límite de subida de PHP por debajo de 8 MB (paso 8) |
| El estudiante no recibe el código de acceso | Correo sin configurar en `.env` (paso 6) |

## 5. Respaldo y restauración (HU-25)

El sistema respalda sus propios datos: no hace falta consola remota ni `pg_dump` en el
servidor, que el alojamiento no ofrece.

### 5.1 Generación

- **A demanda.** En *Respaldo* → «Respaldar ahora». El archivo queda en
  `storage/app/respaldos/` con el nombre `respaldo-AAAAMMDD-HHMMSS.sql`.
- **Programada.** La tarea `respaldo:generar` corre todas las noches a las 03:00. Para
  que el planificador funcione en el servidor hay que dar de alta una sola tarea `cron`
  en el panel:

  ```
  * * * * * cd /home/<cuenta>/punku && php artisan schedule:run >> /dev/null 2>&1
  ```

El archivo contiene los **datos**, no el esquema: el esquema lo crean las migraciones
(paso 9 de este procedimiento). Empieza con un `TRUNCATE` de todas las tablas, sigue con
los `INSERT` en orden de dependencias y repone las secuencias, todo dentro de una
transacción: o entra completo o no entra nada.

### 5.2 Restauración desde el sistema

1. Entrar como Administrador a *Respaldo*.
2. Pulsar «Restaurar» en el respaldo elegido y confirmar en el aviso rojo. La operación
   reemplaza **todos** los datos actuales.
3. El sistema informa cuántas tablas repuso.

La bitácora queda como estaba en el respaldo, más el asiento `respaldo.restaurar` con el
autor y la fecha, que se escribe una vez repuestos los datos. Si la cuenta que ordenó la
restauración no existe en ese respaldo ---se creó después---, el asiento queda sin autor
y lo dice.

### 5.3 Restauración desde el panel, si el sistema no arranca

1. Descargar el respaldo con «Descargar», o tomarlo por FTP de
   `storage/app/respaldos/`.
2. Abrir el administrador web de base de datos del panel.
3. Importar el archivo `.sql` sobre la base de datos del proyecto, con el esquema ya
   creado.

### 5.4 Prueba de la restauración

La restauración está probada de forma automática en
`tests/Feature/Modules/Administracion/RespaldoTest.php`: se genera un respaldo, se
altera la base ---se borra un registro y se agrega otro---, se restaura y se comprueba
que el sistema vuelve al estado del respaldo y que sigue aceptando registros nuevos, lo
que verifica también que las secuencias se repusieron.

Antes de cada entrega conviene repetir la prueba a mano sobre una copia del entorno de
producción, siguiendo 5.2, y dejar constancia en el acta correspondiente.

## 6. Despliegue mediante contenedores

La comunicación de la administración del servidor de agosto de 2026 limita a tres grupos
por docente la posibilidad de desplegar proyectos dockerizados, por restricciones de
recursos. Esta limitación no afecta al presente proyecto, que se ejecuta sobre PHP y
Apache conforme al procedimiento descrito.
