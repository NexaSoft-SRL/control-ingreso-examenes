# Proyecto guía

Copia independiente del repositorio del sistema, donde están implementadas las 30
historias de usuario del catálogo vigente: el detalle y su reparto por *sprint*, en
`docs/guia/avance.md`. Sirve de referencia para el equipo: cada historia queda terminada de
punta a punta, con sus pruebas, y documentada en su ficha
(`docs/guia/fichas/sprintN/huNN.pdf`). El catálogo anterior, de 35 historias, y sus notas
de desarrollo quedan en `docs/guia/v1/`.

No sustituye al repositorio del equipo. Nada de aquí se copia y pega a ciegas: es una
referencia de cómo resolver cada historia respetando la arquitectura y los criterios de
aceptación del *backlog*.

## Diferencias con el proyecto original

| | Proyecto del equipo | Proyecto guía |
|---|---|---|
| Base de datos | `control_ingreso`, puerto 5432 | `control_ingreso_guia`, puerto 5434 |
| Contenedor | `control_ingreso_db` | `control_ingreso_guia_db` |
| Volumen | `datos_postgres` | `datos_postgres_guia` |
| Servidor local | `http://localhost:8000` | `http://localhost:8001` |

Las dos bases pueden correr a la vez. El motor es el mismo, PostgreSQL 15.10, la versión
del servidor de destino.

## Cómo levantarlo

```bash
docker compose up -d                 # base de datos propia, puerto 5434
composer install && npm install      # solo la primera vez
php artisan migrate
php artisan serve --port=8001        # y, aparte, npm run dev
```

La base de pruebas `control_ingreso_testing` se crea una sola vez dentro del contenedor:

```bash
docker exec control_ingreso_guia_db \
  psql -U nexasoft -d control_ingreso_guia -c "CREATE DATABASE control_ingreso_testing;"
```

## Datos de demostración

```bash
composer datos:demo                            # rehace la base y la siembra
composer datos:volumen                         # lo anterior más un examen de 400
php artisan db:seed --class=DemoGuiaSeeder     # agrega lo que falte, sin borrar
```

Deja una cuenta por rol, el padrón con ocho estudiantes (uno dado de baja), tres
docentes, cuatro ambientes, tres asignaturas y cuatro exámenes. El primero está en curso:
con los habilitados repartidos en dos aulas, cuatro ingresos ---uno con observación---,
una expulsión, dos situaciones excepcionales y dos intentos de ingreso que no
correspondían. Con eso el punto de control, el tablero, la lista de presentes y los cinco
reportes muestran datos reales. Es lo que se ve en las capturas de las fichas.

`DatosMasivosSeeder` agrega encima un examen masivo de hoy ---400 estudiantes, tres
ambientes, 293 ingresos escalonados--- y un examen vecino a la misma hora, que es lo que
hace falta para mirar el tablero, la lista de presentes y los cinco reportes con la
cantidad de filas que van a tener el día del examen. El detalle está en el README de la
raíz.

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Administrador | `admin@umss.edu.bo` | `Admin12345` |
| Docente | `docente@umss.edu.bo` | `Docente12345` |
| Personal de control | `control@umss.edu.bo` | `Control12345` |
| Responsable académico | `responsable@umss.edu.bo` | `Responsable12345` |

Cada rol entra solo a las secciones que tiene habilitadas. Para probar la carga masiva
hay dos archivos en `docs/ejemplos/`: `padron_ejemplo.csv`, con los mismos ocho
estudiantes del seeder, y `padron_con_errores.csv`, con tres filas que el sistema tiene
que rechazar indicando fila y motivo.

## Controles antes de cada confirmación

```bash
composer quality:backend    # arquitectura, estilo, análisis estático y pruebas
composer test:postgresql    # pruebas contra PostgreSQL 15.10
npm run quality             # lint, formato, pruebas y compilación del frontend
```

Una historia no se da por terminada si alguno falla.

## Cómo se documenta cada historia

Las notas de desarrollo del catálogo anterior (`docs/guia/v1/huNN.md`) siguen esta
estructura, que se mantiene para las historias que se documenten así:

1. **Qué pide la historia**: narrativa y criterios de aceptación del *backlog*, más lo
   observado por la consultora en las revisiones.
2. **Decisiones**: qué se resolvió y por qué, incluido lo que se dejó fuera.
3. **Backend**: archivos creados por capa, y el contrato de cada *endpoint*.
4. **Frontend**: pantallas, rutas y cómo consumen la API.
5. **Pruebas**: qué cubre cada una.
6. **Commits**: la lista de confirmaciones de la historia, en orden.

Cada historia tiene además su ficha en LaTeX, agrupadas por sprint en
`docs/guia/fichas/sprintN/huNN.tex`, con su PDF al lado. Las tres cosas que comparten
---`cuerpo.tex`, `comun.tex` y `mockups/`--- quedan en la raíz de `fichas/`, y por eso el
guion se ejecuta desde ahí:

```bash
cd docs/guia/fichas
sh compilar.sh              # las 30
sh compilar.sh sprint4      # las de un sprint
sh compilar.sh hu24 hu27    # algunas sueltas
```

El avance general se sigue en `docs/guia/avance.md`.

## Confirmaciones

Se mantiene la convención de `docs/convenciones.md`: Conventional Commits en español.
Cada historia avanza en confirmaciones pequeñas y legibles, en este orden habitual:
migraciones, dominio y aplicación, HTTP, pruebas, frontend y documentación. La idea es
que el equipo pueda leer la historia commit por commit.
