import PropTypes from 'prop-types';
import { CalendarClock } from 'lucide-react';
import { Link } from 'react-router-dom';
import usarConsulta from '../api/usarConsulta';
import { usarSesion } from '../sesion/SesionContexto';
import { fechaLarga } from '../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// El próximo examen del docente que todavía no se rindió, de GET /examenes.
function proximoDe(examenes, hoy) {
    return (
        [...(examenes ?? [])]
            .filter((e) => !e.rendido && (!hoy || e.fecha >= hoy))
            .sort((a, b) => `${a.fecha}${a.hora}`.localeCompare(`${b.fecha}${b.hora}`))[0] ?? null
    );
}

// Pantalla sin nada que atender (el punto de control o el examen en curso
// fuera del día del examen): qué pasa, por qué y, para quien registra
// exámenes, cuál es el próximo y el enlace a la lista.
export default function SinActividad({ Icono, titulo, detalle, conReportes = false }) {
    const { puede } = usarSesion();
    const conExamenes = puede('examenes');
    const verReportes = conReportes && puede('reportes_examenes');
    const { datos, meta } = usarConsulta('/examenes', { activa: conExamenes });
    const proximo = conExamenes ? proximoDe(datos, meta?.hoy) : null;

    return (
        <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
            <span className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <Icono className="h-6 w-6" aria-hidden="true" />
            </span>
            <div className="space-y-1">
                <p className="text-base font-semibold text-slate-900">{titulo}</p>
                <p className="text-sm text-slate-600">{detalle}</p>
            </div>
            {proximo && (
                <p className="flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-900">
                    <CalendarClock
                        className="h-4 w-4 shrink-0 text-primary-700"
                        aria-hidden="true"
                    />
                    <span>
                        Próximo: {proximo.tipo_texto ?? proximo.tipo} · {proximo.asignatura?.nombre}{' '}
                        · {fechaLarga(proximo.fecha)}, {proximo.hora}
                    </span>
                </p>
            )}
            {(conExamenes || verReportes) && (
                <div className="flex flex-wrap justify-center gap-x-4">
                    {conExamenes && (
                        <Link
                            to="/examenes"
                            className={`inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                        >
                            Ver exámenes
                        </Link>
                    )}
                    {verReportes && (
                        <Link
                            to="/mis-reportes"
                            className={`inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                        >
                            Reportes de exámenes tomados
                        </Link>
                    )}
                </div>
            )}
        </div>
    );
}

SinActividad.propTypes = {
    Icono: PropTypes.elementType.isRequired,
    titulo: PropTypes.string.isRequired,
    detalle: PropTypes.string.isRequired,
    conReportes: PropTypes.bool,
};
