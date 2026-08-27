# Convenciones de desarrollo

Normas de trabajo sobre el repositorio. Desarrollan lo dispuesto en los artículos 19, 20
y 21 del Reglamento Interno de la grupo-empresa (Anexo A de la propuesta).

## 1. Ramas

La rama `main` contiene la versión estable. Su modificación se realiza exclusivamente
mediante solicitud de incorporación (*pull request*) aprobada; la protección de rama
configurada en el repositorio impide la escritura directa.

Cada historia de usuario se desarrolla en una rama independiente, identificada con su
número en el tablero del proyecto:

```
hu/12-registrar-ingreso-por-qr
fix/34-doble-registro-en-ambiente-erroneo
docs/manual-instalacion
```

Prefijos admitidos: `hu/` funcionalidad, `fix/` corrección, `docs/` documentación,
`chore/` mantenimiento.

## 2. Mensajes de confirmación

Se aplica la especificación Conventional Commits, con el asunto redactado en español, en
modo imperativo y con una extensión máxima de 72 caracteres:

```
<tipo>(<ámbito opcional>): <asunto>
```

| Tipo | Uso |
|---|---|
| `feat` | Funcionalidad nueva |
| `fix` | Corrección de un defecto |
| `docs` | Documentación |
| `refactor` | Reorganización del código sin cambio de comportamiento |
| `test` | Pruebas |
| `ci` | Integración continua |
| `chore` | Configuración, dependencias y mantenimiento |

Ejemplos:

```
feat(ingreso): registrar la hora de ingreso automáticamente
fix(habilitacion): impedir el segundo registro del mismo estudiante
```

Cuando la justificación del cambio no resulte evidente, se añade un cuerpo separado del
asunto por una línea en blanco. No se emplean líneas de coautoría.

Cada confirmación corresponde a un cambio con sentido propio. No se agrupan
modificaciones sin relación entre sí ni se genera una confirmación por cada archivo
modificado.

## 3. Solicitudes de incorporación

Se emiten contra `main` mediante la plantilla del repositorio, que requiere la
descripción del cambio, la historia de usuario correspondiente y el procedimiento de
verificación.

Condiciones para la incorporación:

1. Resultado favorable de la integración continua: estilo de código y pruebas.
2. Aprobación de al menos otro socio, conforme al artículo 19 del reglamento interno.
3. Rama actualizada respecto de `main`.

La revisión se concentra en la corrección de la lógica y en el cumplimiento de los
criterios de aceptación; la verificación del formato corresponde a las herramientas
automáticas.

## 4. Estilo de código

| Ámbito | Herramienta | Ejecución |
|---|---|---|
| PHP | Laravel Pint | `composer exec -- pint` |
| JavaScript y React | ESLint y Prettier | `npm run lint` |

Ambas herramientas se ejecutan automáticamente en cada solicitud de incorporación.

## 5. Nomenclatura

Las entidades del dominio, las tablas y los modelos se nombran en español, por
corresponder al lenguaje empleado en el pliego y en la comunicación con el cliente. Los
elementos propios del marco de trabajo conservan su denominación original.

Las tablas se nombran en plural y los modelos en singular.

## 6. Nombres de archivo

Los sistemas de archivos de macOS no distinguen mayúsculas de minúsculas; los de Linux,
sí. La integración continua y el servidor de destino operan sobre Linux. En consecuencia:

- No se admiten archivos cuyos nombres difieran únicamente en el uso de mayúsculas, dado
  que en macOS constituyen un mismo archivo.
- Las rutas de importación deben coincidir exactamente con el nombre del archivo.
- Los nombres impuestos por una herramienta se conservan en su forma original
  (`README.md`, `app/`, `routes/`, `composer.json`). La documentación se redacta en
  español; el nombre del archivo obedece a la convención de la plataforma.

## 7. Información excluida del repositorio

- El archivo `.env`, que contiene las credenciales del entorno. Se versiona únicamente
  `.env.example`.
- Datos personales reales de estudiantes. Los conjuntos de prueba emplean datos
  ficticios.
- Los directorios `vendor/` y `node_modules/`.

Estas exclusiones están declaradas en `.gitignore`. El repositorio es de acceso público.

## 8. Pruebas

Cada historia de usuario se incorpora acompañada de sus pruebas. Como mínimo:

- Pruebas unitarias de las reglas de validación: habilitación, correspondencia de
  ambiente y unicidad del ingreso.
- Pruebas de integración del flujo completo del punto de control.

Las pruebas de interfaz mediante navegador se ejecutan de forma manual con anterioridad a
cada entrega, y no forman parte de la integración continua.
