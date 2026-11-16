import decidir from './decidir';
import {
    agregarEvento,
    cola,
    dispositivo,
    esDeHoy,
    estaCerrada,
    guardarInstantanea,
    horaCorregida,
    huboUso,
    instantaneaDe,
    marcarCerrada,
    marcarUso,
    quitarEventos,
    reiniciarCierre,
} from './almacen';

const LOTE = 500;

// Una falla de red o del servidor intermedio (sin respuesta, o 502, 503 o
// 504): el servidor no pudo decidir y el puesto sigue con lo que sabe. Un
// rechazo del servidor (422, 409…) sí es una respuesta y no cae aquí.
export function esFalloDeRed(fallo) {
    const estado = fallo?.response?.status;
    return !fallo?.response || estado === 502 || estado === 503 || estado === 504;
}

// Con conexión: descarga la instantánea del aula y la guarda. Una nueva
// empieza sin cierre; si había eventos sin enviar de la anterior, quedan en
// la cola y se envían con la instantánea con que se decidieron.
export async function prepararSinConexion(api, aulaId) {
    const { data } = await api.get(`/puerta/aulas/${aulaId}/instantanea`, {
        params: { dispositivo: dispositivo() },
    });
    const anterior = instantaneaDe(aulaId);
    if (anterior && cola(aulaId).length > 0) {
        // No se pisa lo que aun no llego al servidor.
        return { guardada: false, motivo: 'PENDIENTES', pendientes: cola(aulaId).length };
    }
    // La anterior termino sin eventos: se cierra, para que el servidor no la
    // tome por un puesto que no envio lo suyo.
    if (anterior) {
        try {
            await sincronizar(api, aulaId, { cerrar: true });
        } catch {
            // Si no se pudo cerrar, queda abierta; no impide preparar la nueva.
        }
    }
    const guardada = guardarInstantanea(aulaId, data);
    if (guardada) reiniciarCierre(aulaId);
    return {
        guardada,
        motivo: guardada ? null : 'ALMACENAMIENTO',
        estudiantes: data.estudiantes.length,
        examenes: data.examenes.length,
    };
}

// Lo que el puesto muestra de su modo sin conexión.
export function estadoSinConexion(aulaId, ahoraMs = Date.now()) {
    const guardada = instantaneaDe(aulaId);
    return {
        preparado: guardada !== null,
        vigente: esDeHoy(guardada, ahoraMs),
        pendientes: cola(aulaId).length,
        huboUso: huboUso(aulaId),
        estudiantes: guardada ? guardada.instantanea.estudiantes.length : 0,
    };
}

// Decide con la instantánea cuando el servidor no responde. Devuelve el
// veredicto con `sin_conexion: true`, o por qué no se puede:
//   SIN_INSTANTANEA  el puesto no preparó el modo
//   OTRO_DIA         la instantánea es de otro día
//   SIN_EXAMEN       no hay un examen abierto a esta hora
//   SIN_REGISTRO     nadie con ese código
//   NO_GUARDA        el navegador no pudo guardar lo decidido
export function decidirSinConexion(aulaId, peticion, ahoraMs = Date.now()) {
    const guardada = instantaneaDe(aulaId);
    if (!guardada) return { error: 'SIN_INSTANTANEA' };
    if (!esDeHoy(guardada, ahoraMs)) return { error: 'OTRO_DIA' };

    const resultado = decidir({
        instantanea: guardada.instantanea,
        cola: cola(aulaId),
        ahoraMs: horaCorregida(guardada, ahoraMs),
        peticion,
    });
    if (resultado.sinExamen) return { error: 'SIN_EXAMEN' };
    if (resultado.sinRegistro) return { error: 'SIN_REGISTRO' };

    if (resultado.evento && !agregarEvento(aulaId, resultado.evento)) return { error: 'NO_GUARDA' };
    marcarUso(aulaId);

    return { veredicto: { ...resultado.veredicto, sin_conexion: true } };
}

// Al volver la conexión: envía lo decidido, por lotes. Con `cerrar`, aunque
// no haya nada que enviar avisa que este puesto terminó (cierra la sesión),
// para que el docente sepa que no quedó nada en su celular; solo se pide al
// salir de la puerta o cuando el examen termina, no al descargar. Devuelve
// el resumen o null si no había nada que hacer.
export async function sincronizar(api, aulaId, { cerrar = false } = {}) {
    const guardada = instantaneaDe(aulaId);
    if (!guardada) return null;

    const instantaneaEn = guardada.instantanea.instantanea_en;
    const pendientes = cola(aulaId);

    if (pendientes.length === 0 && (!cerrar || estaCerrada(aulaId, instantaneaEn))) return null;

    const total = {
        recibidos: 0,
        aplicados: 0,
        repetidos: 0,
        ingresos: 0,
        incidencias: 0,
        irregularidades: 0,
    };
    const lotes = pendientes.length === 0 ? [[]] : [];
    for (let i = 0; i < pendientes.length; i += LOTE) lotes.push(pendientes.slice(i, i + LOTE));

    for (const lote of lotes) {
        const { data } = await api.post(`/puerta/aulas/${aulaId}/sincronizacion`, {
            dispositivo: dispositivo(),
            instantanea_en: instantaneaEn,
            eventos: lote,
        });
        quitarEventos(
            aulaId,
            lote.map((e) => e.uid)
        );
        Object.keys(total).forEach((clave) => {
            total[clave] += data[clave] ?? 0;
        });
    }

    marcarCerrada(aulaId, instantaneaEn);
    return total;
}
