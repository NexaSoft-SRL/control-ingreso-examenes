# Modelo de datos

## 1. Estado del documento

Este documento contiene el diseño de datos del sistema de gestión, validación y
control del ingreso de estudiantes a exámenes masivos.

El diseño se encuentra en elaboración durante el Sprint 0. Ninguna estructura
indicada como candidata se considera implementada mientras no haya sido
validada, traducida a restricciones PostgreSQL y aprobada conforme al proceso
del proyecto.

Las migraciones de dominio se crearán después de cerrar este diseño.

## 2. Principios

El modelo debe:

- preservar integridad referencial;
- impedir inconsistencias críticas también bajo concurrencia;
- mantener trazabilidad de las operaciones relevantes;
- evitar duplicación de hechos;
- permitir incorporar estudiantes, exámenes y ambientes sin cambios estructurales;
- soportar identificación rápida por QR, código universitario y documento;
- conservar los registros históricos necesarios para auditoría y reportes.

## 3. Entidades conceptuales

### Administración

- Usuario
- Rol
- Permiso
- Bitácora

### Estudiantes

- Estudiante
- ParticipacionExamen

### Exámenes

- Docente
- Asignatura
- Examen
- Ambiente
- ExamenAmbiente
- NormaExamen
- NormaEstudianteExamen

### Habilitación

- Habilitación
- MotivoInhabilitación
- AsignaciónAmbiente
- CódigoQR

### Ingreso

- Ingreso
- AnulaciónIngreso
- Incidencia

## 4. Relaciones conceptuales

Un docente puede ser responsable de asignaturas.

Una asignatura puede tener múltiples exámenes.

Un examen puede utilizar múltiples ambientes y un ambiente puede ser utilizado
por múltiples exámenes en distintos momentos. Esta relación se representa
mediante ExamenAmbiente.

Un examen puede disponer de múltiples NormaExamen.

Una NormaEstudianteExamen representa una regla particular cuyo alcance está
limitado a una combinación estudiante-examen.

Un estudiante puede participar en múltiples exámenes. ParticipacionExamen
representa de forma neutral la pertenencia de un Estudiante a un Examen y debe
ser única para cada combinación estudiante-examen.

La condición del estudiante respecto a esa participación se representa mediante
Habilitación. Una habilitación negativa se relaciona con un
MotivoInhabilitación parametrizable.

CódigoQR permite identificar una relación estudiante-examen durante el punto de
control, pero no constituye por sí mismo evidencia de habilitación ni permiso
de ingreso.

La asignación física del estudiante se modela separadamente de la asignación del
ambiente al examen. AsignaciónAmbiente relaciona estudiante, examen y ambiente.

Un ingreso corresponde al acceso efectivo de un estudiante a un examen. Se
relaciona con la Habilitación validada, el ambiente efectivo del ingreso y el
usuario que realizó el registro. Estudiante y examen se determinan
inequívocamente mediante la Habilitación.

Las incidencias registran resultados excepcionales del punto de control. Algunas
pueden existir sin un ingreso previo; una expulsión, en cambio, requiere un
ingreso previo. Esta diferencia debe quedar resuelta antes del modelo lógico.

## 5. Reglas funcionales confirmadas

Las siguientes reglas provienen del pliego, del backlog o de la propuesta
técnica aceptada como base del diseño:

- El estudiante debe poder identificarse mediante código universitario,
  documento de identidad o código QR.
- Antes del ingreso debe comprobarse la habilitación del estudiante para el
  examen correspondiente.
- Antes del ingreso debe verificarse el ambiente que corresponde al estudiante.
- La fecha y hora del ingreso se registran automáticamente.
- Un estudiante no puede registrar más de un ingreso para el mismo examen.
- La garantía contra doble ingreso debe mantenerse también bajo concurrencia.
- Los intentos de ingreso a exámenes que no corresponden deben quedar registrados.
- Las expulsiones deben conservar motivo y hora.
- Las situaciones excepcionales, incluidos problemas de identificación,
  estudiantes no habilitados y cambios de ambiente, deben quedar registradas.
- Las operaciones relevantes deben conservar usuario, fecha y hora.
- Las correcciones de un ingreso deben conservar el registro original mediante
  una anulación registrada.

## 5.1 Invariantes de diseño candidatos

Las siguientes reglas son propuestas del modelo y deben validarse antes de
convertirse en restricciones PostgreSQL:

- una sola relación de habilitación por estudiante y examen;
- una sola asignación de ambiente por estudiante y examen;
- el ambiente asignado a un estudiante debe formar parte de los ambientes
  habilitados para ese examen;
- la bitácora representa hechos de auditoría y no el estado actual de las
  entidades de negocio.

## 6. Decisiones de diseño

### D-01 — Docente y usuario — RESUELTA COMO PROPUESTA DE DISEÑO

Docente se mantiene como entidad académica independiente de Usuario.

Un Docente puede vincularse opcionalmente a un Usuario mediante una relación
uno a cero-o-uno. El vínculo debe ser único cuando exista.

Usuario representa autenticación, autorización y acceso al sistema. Docente
representa la identidad académica utilizada por asignaturas y exámenes.

Esta separación permite registrar docentes académicos antes de provisionarles
una cuenta y evita acoplar la información académica al mecanismo de acceso.
La decisión debe ser validada con TIS antes de congelar el modelo lógico.

### D-02 — Semántica de Habilitación — RESUELTA COMO PROPUESTA DE DISEÑO

Habilitación representa la condición evaluada de un estudiante respecto a un
examen.

Para una misma combinación estudiante-examen puede existir como máximo una
Habilitación.

La ausencia de una fila de Habilitación significa que no existe una condición
registrada para ese estudiante y examen; no equivale a una inhabilitación.

Una Habilitación conserva su condición y, cuando la condición sea negativa, el
motivo de inhabilitación correspondiente.

MotivoInhabilitación se mantiene como catálogo parametrizable y no como valores
codificados en la aplicación.

La combinación estudiante-examen deberá disponer de una restricción UNIQUE en
el modelo lógico.

Esta decisión debe ser validada con TIS antes de congelar el modelo lógico.

### D-03 — Unicidad e integridad de Ingreso — RESUELTA PARCIALMENTE

Ingreso referencia una Habilitación en lugar de duplicar estudiante_id y
examen_id.

Habilitación dispone de unicidad sobre estudiante-examen.

Si D-07 determina que una anulación no permite registrar posteriormente otro
Ingreso para la misma relación estudiante-examen, una restricción UNIQUE sobre
habilitacion_id en Ingreso constituye una solución candidata que garantiza el
bloqueo concurrente sin duplicar estudiante_id y examen_id.

Si D-07 determina que una anulación permite registrar posteriormente un nuevo
Ingreso correcto, esa restricción simple no será suficiente porque impediría
conservar varios ingresos históricos para una misma Habilitación. En ese caso
deberá diseñarse una garantía PostgreSQL que permita historial pero mantenga
como máximo un ingreso efectivo.

Ambas alternativas deben preservar la regla funcional de doble ingreso exigida
por el sistema. La estrategia física definitiva debe validarse con TIS antes de
congelar el modelo lógico.

La referencia a Habilitación, por sí sola, no garantiza que su condición sea
habilitada. El modelo lógico deberá impedir que un Ingreso se asocie a una
Habilitación cuya condición sea negativa. El mecanismo PostgreSQL concreto se
definirá al diseñar las restricciones físicas.

La semántica definitiva de la unicidad depende además de D-07, porque debe
definirse si una anulación permite o no registrar posteriormente otro ingreso
correcto para el mismo estudiante y examen.

### D-04 — Incidencias — RESUELTA PARCIALMENTE COMO PROPUESTA DE DISEÑO

Incidencia representa un hecho excepcional ocurrido durante el proceso de
control de ingreso.

Los tipos funcionales inicialmente conocidos son:

- intento no autorizado;
- problema de identificación;
- cambio de ambiente;
- expulsión.

Toda Incidencia conserva como mínimo su tipo, el usuario que la registra, el
motivo y la fecha y hora del hecho.

El contexto asociado depende del tipo de incidencia. No todos los casos
disponen necesariamente de un estudiante identificado ni de un ingreso previo.

Una expulsión requiere obligatoriamente un Ingreso previo y debe conservar una
referencia a este.

Un problema de identificación puede registrarse aun cuando no haya sido posible
determinar el Estudiante.

Los intentos no autorizados y los cambios de ambiente deben conservar el
contexto suficiente para identificar el examen y la situación que produjo la
incidencia.

Para los cambios de ambiente debe distinguirse el ambiente anterior del nuevo
ambiente. La representación física concreta de este dato se definirá en el
modelo lógico.

La estructura definitiva deberá evitar columnas ambiguas o combinaciones de
campos inválidas. Las restricciones específicas por tipo se definirán antes de
crear las migraciones PostgreSQL.

Esta decisión debe ser validada con TIS antes de congelar el modelo lógico.

### D-05 — Código QR — RESUELTA PARCIALMENTE COMO PROPUESTA DE DISEÑO

CódigoQR representa un mecanismo de identificación de la relación entre un
estudiante y un examen. La existencia o validez del código no constituye por sí
misma autorización de ingreso.

Después de resolver un CódigoQR, el sistema debe verificar de forma independiente
la habilitación, el ambiente correspondiente y la existencia de un ingreso
previo.

El contenido utilizado como QR debe ser un identificador opaco y no debe exponer
directamente información sensible ni utilizarse como evidencia de que el
estudiante está habilitado.

El diseño debe permitir regenerar y revocar códigos. Cuando se regenere un
CódigoQR, los códigos anteriores deben quedar inactivos sin perder la
trazabilidad de su existencia.

Como propuesta de seguridad, se considera almacenar en la base de datos una
representación criptográfica no reversible del token utilizado para resolver el
código, evitando conservar el token secreto en texto plano.

Esta alternativa debe evaluarse junto con la necesidad funcional de reimprimir
o reenviar posteriormente exactamente el mismo código. Si TIS exige esa
capacidad, el diseño deberá resolverla sin degradar la protección del token.

Debe existir como máximo un CódigoQR activo para una misma relación
estudiante-examen.

La relación física definitiva de CódigoQR con Habilitación o directamente con
estudiante-examen se definirá en el modelo lógico después de validar D-02 con
TIS.

Quedan pendientes de validación funcional la expiración, reimpresión, reenvío y
regeneración del CódigoQR antes de congelar el modelo lógico.

### D-06 — Normas del examen — RESUELTA PARCIALMENTE COMO PROPUESTA DE DISEÑO

Las normas generales y las normas particulares de un estudiante se modelan como
conceptos separados.

NormaExamen representa una norma cuyo alcance corresponde al examen completo.

NormaEstudianteExamen representa una norma particular aplicable exclusivamente
a un estudiante dentro de un examen determinado.

Las normas se consideran datos del dominio y no valores codificados en la
aplicación.

Un examen puede disponer de múltiples NormaExamen.

Una misma relación estudiante-examen puede disponer de múltiples
NormaEstudianteExamen.

El modelo no presupone que una norma particular sustituya automáticamente una
norma general. Debe validarse con TIS si las normas particulares son
adicionales, excepciones o sustituciones de normas generales antes de definir
su semántica definitiva.

No se introduce por ahora un catálogo global de normas, porque los requisitos
disponibles no demuestran que las mismas normas deban reutilizarse como
entidades compartidas entre diferentes exámenes.

### D-07 — Anulación y nuevo ingreso

Definir el efecto funcional de una anulación sobre la posibilidad de registrar
posteriormente otro ingreso para el mismo estudiante y examen.

Debe determinarse si:

1. la anulación únicamente conserva constancia de una corrección y el ingreso
   original continúa siendo el único ingreso posible; o
2. la anulación invalida el ingreso original y permite registrar posteriormente
   un nuevo ingreso correcto.

Esta decisión condiciona la estrategia definitiva de unicidad de Ingreso y debe
cerrarse antes de definir la restricción PostgreSQL correspondiente.

### D-08 — Responsabilidad docente y asignatura

Los requisitos identifican Docente y Asignatura como parte de la oferta
académica, pero todavía no establecen la cardinalidad exacta entre ambos.

Debe validarse si:

- una asignatura dispone de un único docente responsable;
- una asignatura puede disponer de varios docentes;
- la responsabilidad corresponde directamente al Examen y no a la Asignatura;
- o se requiere una entidad intermedia de oferta académica.

No se fijará una cardinalidad física hasta contar con esta definición.

### D-09 — Gestión académica

La documentación hace referencia a la oferta académica de una gestión, pero
todavía no determina si GestiónAcadémica debe representarse como una entidad
propia o como un atributo de otras entidades.

La decisión debe considerar la conservación histórica de asignaturas, docentes,
exámenes y reportes entre distintas gestiones académicas.

No se incorporará GestiónAcadémica al modelo lógico hasta validar esta
necesidad con TIS.

## 7. Restricciones candidatas

Las siguientes restricciones son parte del diseño y todavía no se consideran
implementadas:

- unicidad de código universitario;
- unicidad de documento de identidad, pendiente de validación de los datos TIS;
- unicidad de Habilitación por estudiante y examen;
- unicidad de AsignaciónAmbiente por estudiante y examen;
- existencia de como máximo un ingreso efectivo para una misma relación
  estudiante-examen; el mecanismo definitivo de unicidad queda condicionado por
  D-07;
- integridad entre ExamenAmbiente y AsignaciónAmbiente;
- obligatoriedad del motivo cuando una habilitación sea negativa;
- ausencia de motivo de inhabilitación cuando la condición sea positiva;
- prohibición de asociar un Ingreso a una Habilitación cuya condición sea
  negativa;
- obligatoriedad de un Ingreso previo para una incidencia de expulsión;
- posibilidad de registrar un problema de identificación sin Estudiante
  identificado;
- conservación del ambiente anterior y del nuevo ambiente cuando se registre
  un cambio de ambiente;
- un CódigoQR válido identifica estudiante y examen pero no sustituye la
  validación de habilitación;
- como máximo un CódigoQR puede encontrarse activo para una misma relación
  estudiante-examen;
- un CódigoQR revocado no puede utilizarse para identificar un ingreso;
- una NormaExamen mantiene alcance general sobre su examen;
- una NormaEstudianteExamen mantiene alcance exclusivo sobre la combinación
  estudiante-examen correspondiente;
- conservación del ingreso original cuando exista una corrección;
- protección de la bitácora contra edición ordinaria.

## 8. Índices candidatos

Los índices se definirán a partir de las consultas críticas después de cerrar
las claves del modelo.

Las búsquedas conocidas incluyen:

- estudiante por código universitario;
- estudiante por documento de identidad;
- habilitación por estudiante y examen;
- ambiente asignado por estudiante y examen;
- ingreso por estudiante y examen;
- ingresos por examen y ambiente;
- incidencias por examen, tipo y fecha;
- bitácora por usuario, operación y fecha.

No se crearán índices redundantes con restricciones UNIQUE o claves primarias.

## 9. Fuera de alcance

El sistema no administra contenido del examen, respuestas, corrección ni
calificaciones.

Su responsabilidad funcional concluye en el control y trazabilidad del ingreso,
las situaciones excepcionales relacionadas y la información necesaria para
monitoreo y reportes.
