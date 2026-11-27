import sha256 from '../utiles/sha256';
import { nuevoUid } from './almacen';

// Las mismas reglas de la puerta (VerificarEnPuerta), con lo que el puesto
// sabe de la instantánea y de lo que ya decidió sin conexión. No hay
// excepciones, expulsiones, carnet ni fotos: piden conexión.
const RELECTURA_MS = 120 * 1000;
const REPETIDO_MS = 10 * 60 * 1000;

const hhmm = (ms) =>
    new Date(ms).toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit', hour12: false });

const abierto = (examen, ms) => ms >= Date.parse(examen.abre) && ms <= Date.parse(examen.cierra);

// El ingreso de un estudiante en un examen: el de la instantánea o el que
// este puesto ya admitió sin conexión.
function ingresoDe(instantanea, cola, estudiante, examenId, aula) {
    const admitido = cola.find(
        (e) =>
            e.resultado === 'ADMITIDO' &&
            e.estudiante_id === estudiante.id &&
            e.examen_id === examenId
    );
    if (admitido) {
        return {
            aula_id: aula.id,
            aula: aula.nombre,
            ms: Date.parse(admitido.hora),
            expulsado: false,
        };
    }
    const previo = estudiante.examenes.find((x) => x.examen_id === examenId)?.ingreso;
    if (!previo) return null;
    return {
        aula_id: previo.aula_id,
        aula: previo.aula,
        ms: null,
        hora: previo.hora,
        expulsado: Boolean(previo.expulsado),
    };
}

function evento({ ahoraMs, medio, resultado, examenId, estudianteId, token, detalle }) {
    return {
        uid: nuevoUid(),
        hora: new Date(ahoraMs).toISOString(),
        medio,
        resultado,
        examen_id: examenId ?? null,
        estudiante_id: estudianteId ?? null,
        ...(token ? { token } : {}),
        ...(detalle ? { detalle } : {}),
    };
}

// `peticion` es `{ token }` o `{ identificador }`. Devuelve:
//   { veredicto, evento }  el veredicto a mostrar y lo que hay que encolar
//   { sinRegistro: true }  nadie con ese código
//   { sinExamen: true }    ningún examen abierto a esta hora
export default function decidir({ instantanea, cola, ahoraMs, peticion }) {
    const { aula } = instantanea;
    const abiertos = instantanea.examenes.filter((x) => abierto(x, ahoraMs));
    if (abiertos.length === 0) return { sinExamen: true };

    const ids = new Set(abiertos.map((x) => x.id));
    const porId = new Map(instantanea.estudiantes.map((e) => [e.id, e]));
    let estudiante = null;
    let candidatos;
    let medio;
    let token = null;

    if (peticion.token) {
        medio = 'QR';
        token = peticion.token;
        const clave = instantanea.claves[sha256(token)];
        estudiante = clave ? (porId.get(clave.estudiante_id) ?? null) : null;

        if (!clave || !estudiante) {
            return {
                veredicto: {
                    veredicto: 'no_corresponde',
                    dato: 'Código QR no válido',
                    pie: 'Intento registrado',
                    examen_correspondiente: null,
                },
                evento: evento({
                    ahoraMs,
                    medio,
                    resultado: 'CODIGO_INVALIDO',
                    examenId: abiertos[0].id,
                    token,
                }),
            };
        }
        // Con QR el examen es el del código, no otro del estudiante.
        candidatos = ids.has(clave.examen_id) ? [clave.examen_id] : [];
    } else {
        const codigo = String(peticion.identificador ?? '').trim();
        estudiante = instantanea.estudiantes.find((e) => e.codigo === codigo) ?? null;

        if (estudiante) {
            medio = 'CODIGO';
        } else {
            const normalizado = codigo.replace(/[\s-]+/g, '').toUpperCase();
            const docId = normalizado
                ? (instantanea.documentos?.[sha256(normalizado)] ?? null)
                : null;
            estudiante = docId ? (porId.get(docId) ?? null) : null;
            if (!estudiante) return { sinRegistro: true };
            medio = 'DOCUMENTO';
        }

        candidatos = estudiante.examenes.map((x) => x.examen_id).filter((id) => ids.has(id));
    }

    const datosEstudiante = {
        id: estudiante.id,
        codigo: estudiante.codigo,
        nombre: estudiante.nombre,
        foto: null,
    };

    if (candidatos.length === 0) {
        return {
            veredicto: {
                veredicto: 'no_corresponde',
                estudiante: datosEstudiante,
                dato: `No inscrito en los exámenes del aula ${aula.nombre}`,
                pie: 'Intento registrado',
                examen_correspondiente: null,
            },
            evento: evento({
                ahoraMs,
                medio,
                resultado: 'NO_CORRESPONDE',
                examenId: abiertos[0].id,
                estudianteId: estudiante.id,
                token,
            }),
        };
    }

    // El examen que puede admitirlo: sin ingreso y habilitado primero.
    const examenId =
        candidatos.find((id) => {
            const x = estudiante.examenes.find((e) => e.examen_id === id);
            return !ingresoDe(instantanea, cola, estudiante, id, aula) && x?.condicion?.habilitado;
        }) ??
        candidatos.find((id) => ingresoDe(instantanea, cola, estudiante, id, aula)) ??
        candidatos[0];
    const examen = instantanea.examenes.find((x) => x.id === examenId);
    const datosExamen = { id: examen.id, asignatura: examen.asignatura, tipo: examen.tipo };
    const condicion = estudiante.examenes.find((x) => x.examen_id === examenId)?.condicion ?? null;
    const previo = ingresoDe(instantanea, cola, estudiante, examenId, aula);

    if (previo) {
        if (previo.expulsado) {
            return {
                veredicto: {
                    veredicto: 'expulsado',
                    estudiante: datosEstudiante,
                    examen: datosExamen,
                    dato: 'Expulsado',
                },
                evento: null,
            };
        }
        const hora = previo.ms === null ? previo.hora.slice(0, 5) : hhmm(previo.ms);
        const donde = previo.aula_id === aula.id ? 'en esta aula' : `en el aula ${previo.aula}`;
        const relectura =
            previo.aula_id === aula.id && previo.ms !== null && ahoraMs - previo.ms <= RELECTURA_MS;
        return {
            veredicto: {
                veredicto: 'ya_ingreso',
                estudiante: datosEstudiante,
                examen: datosExamen,
                dato: `Ingresó a las ${hora} ${donde}`,
                pie: relectura ? null : 'Incidencia registrada',
            },
            evento: relectura
                ? null
                : evento({
                      ahoraMs,
                      medio,
                      resultado: 'YA_INGRESO',
                      examenId,
                      estudianteId: estudiante.id,
                  }),
        };
    }

    if (!condicion || !condicion.habilitado) {
        const dato = condicion ? (condicion.motivo ?? 'No habilitado') : 'Sin revisar';
        const reciente = cola.some(
            (e) =>
                e.resultado === 'NO_HABILITADO' &&
                e.estudiante_id === estudiante.id &&
                ahoraMs - Date.parse(e.hora) < REPETIDO_MS
        );
        return {
            veredicto: {
                veredicto: 'no_habilitado',
                estudiante: datosEstudiante,
                examen: datosExamen,
                dato,
                pie: null,
            },
            evento: reciente
                ? null
                : evento({
                      ahoraMs,
                      medio,
                      resultado: 'NO_HABILITADO',
                      examenId,
                      estudianteId: estudiante.id,
                      detalle: dato,
                  }),
        };
    }

    const sugerida =
        condicion.aula_id && condicion.aula_id !== aula.id
            ? { id: condicion.aula_id, nombre: condicion.aula }
            : null;

    return {
        veredicto: {
            veredicto: 'admitido',
            estudiante: datosEstudiante,
            examen: datosExamen,
            dato: `Registrado a las ${hhmm(ahoraMs)}${sugerida ? ` · su aula sugerida era la ${sugerida.nombre}` : ''}`,
            pie: null,
            ingreso: { hora: hhmm(ahoraMs) },
            aula_sugerida: sugerida,
        },
        evento: evento({
            ahoraMs,
            medio,
            resultado: 'ADMITIDO',
            examenId,
            estudianteId: estudiante.id,
        }),
    };
}
