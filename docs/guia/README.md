# Documentación de las historias

Aquí está la documentación de las 30 historias de usuario del catálogo vigente: el detalle y
su reparto por *sprint*, en `docs/guia/avance.md`. Cada historia queda terminada de punta a
punta, con sus pruebas, y documentada en su ficha (`docs/guia/fichas/sprintN/huNN.pdf`). El
catálogo anterior, de 35 historias, y sus notas de desarrollo quedan en `docs/guia/v1/`.

La puesta en marcha, los usuarios y los datos de prueba están en el `README.md` de la raíz.

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
