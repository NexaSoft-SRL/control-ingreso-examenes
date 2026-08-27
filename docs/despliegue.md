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
   datos, el usuario y la contraseña asignados. Este archivo se transfiere al servidor y
   no se incorpora al repositorio.

7. **Transferencia.** Subir el proyecto comprimido y descomprimirlo en el servidor: la
   aplicación en el directorio raíz de la cuenta y el contenido de su directorio público
   en `public_html`, previa eliminación de los archivos predeterminados.

8. **Permisos.** Otorgar permisos de escritura a los directorios `storage/` y
   `bootstrap/cache/`.

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

## 5. Despliegue mediante contenedores

La comunicación de la administración del servidor de agosto de 2026 limita a tres grupos
por docente la posibilidad de desplegar proyectos dockerizados, por restricciones de
recursos. Esta limitación no afecta al presente proyecto, que se ejecuta sobre PHP y
Apache conforme al procedimiento descrito.
