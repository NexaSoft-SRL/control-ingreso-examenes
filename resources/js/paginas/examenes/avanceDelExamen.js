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
                    ? plural(e.auxiliares, 'persona')
                    : 'Solo docente',
    },
    {
        clave: 'habilitacion',
        Icono: ListChecks,
        texto: 'Habilitación',
        detalle: (e) => (e.habilitados ? `${e.habilitados} de ${e.inscritos}` : 'Pendiente'),
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

export const pendiente = (e) => e.estado !== 'Listo';
export const tipoDe = (e) => e.tipo_texto ?? e.tipo;

// A dónde lleva la acción siguiente. Las aulas y quién atiende solo los
// cambia quien registró el examen; las otras dos dependen del permiso de su
// pantalla.
export function accionDe(e, puedeHabilitar, puedeEmitir) {
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
    if (clave === 'habilitar' && puedeHabilitar) {
        const empezada = (e.habilitados ?? 0) + (e.no_habilitados ?? 0) > 0;
        return {
            texto: empezada ? 'Terminar habilitación' : 'Habilitar',
            a: rutaDelExamen(e.id, 'habilitacion'),
        };
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
    es_hoy: PropTypes.bool,
    rendido: PropTypes.bool,
    con_ingresos: PropTypes.bool,
    con_incidencias: PropTypes.bool,
    codigos_aula: PropTypes.number,
});
