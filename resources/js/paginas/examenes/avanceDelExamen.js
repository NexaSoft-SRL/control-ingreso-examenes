import PropTypes from 'prop-types';
import { DoorOpen, ListChecks, QrCode, UserCheck, Users } from 'lucide-react';
import { plural } from '../../utiles/texto';
import rutaDelExamen from './rutaDelExamen';

// El avance de un examen, como lo muestran la lista y la página del examen.

// Los cinco pasos del avance, en el orden en que se preparan.
export const PASOS = [
    {
        clave: 'grupos',
        Icono: Users,
        texto: 'Grupos',
        detalle: (e) => `${e.grupos.length} · ${plural(e.inscritos, 'inscrito')}`,
    },
    {
        clave: 'aulas',
        Icono: DoorOpen,
        texto: 'Aulas',
        detalle: (e) => (e.aulas.length ? e.aulas.join(', ') : 'Sin elegir'),
    },
    {
        clave: 'auxiliares',
        Icono: UserCheck,
        texto: 'Quién atiende',
        detalle: (e) =>
            e.aulas.length === 0
                ? 'Pendiente'
                : e.avance?.auxiliares === 'ahora'
                  ? 'Más de 2 aulas por persona'
                  : e.auxiliares
                    ? plural(e.auxiliares, 'auxiliar', 'auxiliares')
                    : 'Solo docente',
    },
    {
        clave: 'habilitacion',
        Icono: ListChecks,
        texto: 'Habilitación',
        detalle: (e) =>
            e.estado === 'Falta repartir estudiantes'
                ? 'Sin aula asignada'
                : e.habilitados
                  ? `${e.habilitados} de ${e.inscritos}`
                  : 'Pendiente',
    },
    {
        clave: 'qr',
        Icono: QrCode,
        texto: 'Códigos QR',
        detalle: (e) =>
            e.qr_emitidos
                ? e.avance.qr === 'ok'
                    ? plural(e.qr_emitidos, 'emitido')
                    : `${e.qr_emitidos} de ${e.habilitados}`
                : 'Pendiente',
    },
];

// Un examen ya rendido (o de un día pasado) no tiene pasos pendientes: lo que
// queda es ver su reporte.
export const yaPaso = (e, hoy = null) => Boolean(e.rendido) || Boolean(hoy && e.fecha < hoy);
export const estadoDe = (e, hoy = null) => (yaPaso(e, hoy) ? 'Rendido' : e.estado);
export const pendiente = (e, hoy = null) => !yaPaso(e, hoy) && e.estado !== 'Listo';
export const tipoDe = (e) => e.tipo_texto ?? e.tipo;

// A dónde lleva la acción siguiente. Las aulas solo las elige quien registró
// el examen; las otras dos dependen del permiso de su pantalla.
export function accionDe(
    e,
    puedeHabilitar,
    puedeEmitir,
    { hoy = null, puedeReportes = false } = {}
) {
    if (yaPaso(e, hoy)) {
        return puedeReportes ? { texto: 'Ver reporte', a: `/mis-reportes?examen=${e.id}` } : null;
    }
    const clave = e.accion?.clave;
    if (clave === 'elegir_aulas' && e.propio) {
        return {
            texto: 'Elegir aulas',
            a: `/examenes/nuevo?examen=${e.id}&paso=${e.accion.paso ?? 2}`,
        };
    }
    if (clave === 'quien_atiende' && e.propio) {
        return {
            texto: 'Asignar quién atiende',
            a: `/examenes/nuevo?examen=${e.id}&paso=${e.accion.paso ?? 3}`,
        };
    }
    if (clave === 'repartir' && puedeHabilitar) {
        return { texto: 'Repartir estudiantes', a: rutaDelExamen(e.id, 'habilitacion') };
    }
    if (clave === 'habilitar' && puedeHabilitar) {
        // El mismo verbo del estado («Falta habilitar estudiantes»).
        return { texto: 'Habilitar estudiantes', a: rutaDelExamen(e.id, 'habilitacion') };
    }
    if ((clave === 'emitir_qr' || clave === 'ver_qr') && puedeEmitir) {
        return {
            texto: clave === 'ver_qr' ? 'Códigos QR' : 'Emitir códigos QR',
            a: rutaDelExamen(e.id, 'qr'),
        };
    }
    return null;
}

export const FORMA_EXAMEN = PropTypes.shape({
    id: PropTypes.number.isRequired,
    asignatura: PropTypes.shape({ id: PropTypes.number, nombre: PropTypes.string }).isRequired,
    tipo: PropTypes.string,
    tipo_texto: PropTypes.string,
    fecha: PropTypes.string.isRequired,
    hora: PropTypes.string,
    duracion: PropTypes.number,
    grupos: PropTypes.arrayOf(PropTypes.string).isRequired,
    inscritos: PropTypes.number,
    aulas: PropTypes.arrayOf(PropTypes.string).isRequired,
    auxiliares: PropTypes.number,
    habilitados: PropTypes.number,
    no_habilitados: PropTypes.number,
    qr_emitidos: PropTypes.number,
    avance: PropTypes.objectOf(PropTypes.string).isRequired,
    estado: PropTypes.string,
    accion: PropTypes.shape({ clave: PropTypes.string, paso: PropTypes.number }),
    propio: PropTypes.bool,
    registrado_por: PropTypes.string,
    junto_con: PropTypes.number,
    invitados_pendientes: PropTypes.number,
    es_hoy: PropTypes.bool,
    rendido: PropTypes.bool,
    con_ingresos: PropTypes.bool,
    con_incidencias: PropTypes.bool,
    codigos_aula: PropTypes.number,
});

// Los pasos del asistente de un examen: los del registro, por su posición
// (1 grupos, 2 aulas, 3 equipo, 4 listo), y los de después, por su nombre
// (habilitar, qr, bitacora, sin-conexion), que no dependen de los permisos.
const PASO_DE_AVANCE = { grupos: 1, aulas: 2, auxiliares: 3, habilitacion: 'habilitar', qr: 'qr' };

// El primer paso que falta (o «Listo», el 4, si no falta ninguno).
export function primerPasoPendiente(examen) {
    for (const [clave, paso] of Object.entries(PASO_DE_AVANCE)) {
        if (examen.avance?.[clave] !== 'ok') return paso;
    }
    return 4;
}

// A qué paso del asistente lleva abrir el examen en una pestaña de la página.
export function pasoDeLaPestana(examen, pestana) {
    if (pestana === 'habilitacion') return 'habilitar';
    if (pestana === 'qr') return 'qr';
    if (pestana === 'bitacora' || pestana === 'sin-conexion') return pestana;
    return primerPasoPendiente(examen);
}
