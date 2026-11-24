import PropTypes from 'prop-types';
import { CalendarClock } from 'lucide-react';
import { fechaLarga } from '../../utiles/texto';

// La puerta abre una hora antes del inicio (lo decide el servidor).
const ANTICIPO = 60;

const minutos = (hora) => {
    const [h, m] = String(hora).split(':').map(Number);
    return h * 60 + m;
};
const reloj = (total) =>
    `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
const hoyLocal = (ahora) =>
    `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;

// Lo que le toca a quien atiende aulas, de GET /en-curso (hoy y el próximo
// día con examen): el próximo turno con su aula y a qué hora abre, o que el
// de hoy ya terminó, o que no tiene aulas asignadas.
export function turnoDe(examenes, ahora = new Date()) {
    const hoy = hoyLocal(ahora);
    const ya = ahora.getHours() * 60 + ahora.getMinutes();
    const cerrados = (examenes ?? []).filter((e) => !e.abierto);
    const proximo = cerrados.find(
        (e) => e.fecha > hoy || (e.fecha === hoy && ya < minutos(e.hora) - ANTICIPO)
    );
    if (proximo) return { tipo: 'proximo', examen: proximo };
    const terminado = cerrados.find((e) => e.fecha === hoy);
    if (terminado) return { tipo: 'terminado', examen: terminado };
    return { tipo: 'ninguno' };
}

const aulasDe = (examen) => (examen.aulas ?? []).map((a) => a.nombre).join(', ');

export default function ProximoTurno({ examenes, ahora }) {
    const turno = turnoDe(examenes, ahora);

    if (turno.tipo === 'ninguno') {
        return <p className="text-sm text-slate-700">Sin aulas asignadas · avise al docente</p>;
    }

    const { examen } = turno;
    const nombre = `${examen.tipo_texto || examen.tipo} · ${examen.asignatura}`;
    const aulas = aulasDe(examen);

    if (turno.tipo === 'terminado') {
        return (
            <p className="text-sm text-slate-700">
                Terminó: {nombre}
                {aulas && ` · aula ${aulas}`}
            </p>
        );
    }

    return (
        <p className="flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-2 text-left text-sm text-primary-900">
            <CalendarClock className="h-4 w-4 shrink-0 text-primary-700" aria-hidden="true" />
            <span>
                Próximo: {nombre}
                {aulas && ` · aula ${aulas}`} · {fechaLarga(examen.fecha)}, {examen.hora} · abre{' '}
                {reloj(Math.max(0, minutos(examen.hora) - ANTICIPO))}
            </span>
        </p>
    );
}

ProximoTurno.propTypes = {
    examenes: PropTypes.arrayOf(
        PropTypes.shape({
            tipo: PropTypes.string,
            tipo_texto: PropTypes.string,
            asignatura: PropTypes.string,
            fecha: PropTypes.string.isRequired,
            hora: PropTypes.string.isRequired,
            abierto: PropTypes.bool,
            aulas: PropTypes.arrayOf(PropTypes.shape({ nombre: PropTypes.string })),
        })
    ),
    ahora: PropTypes.instanceOf(Date),
};
