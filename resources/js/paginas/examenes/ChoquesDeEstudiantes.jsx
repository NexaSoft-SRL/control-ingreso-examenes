import PropTypes from 'prop-types';
import { useState } from 'react';
import { ChevronDown, ChevronUp, TriangleAlert } from 'lucide-react';
import usarConsulta from '../../api/usarConsulta';
import { plural } from '../../utiles/texto';

const FORMATO_HORA = /^\d{2}:\d{2}$/;
const FORMATO_FECHA = /^\d{4}-\d{2}-\d{2}$/;

// Aviso (nunca un bloqueo): estudiantes de los grupos del examen que ya tienen
// otro examen a la misma hora (GET /examenes/opciones/choques). No dice nada
// mientras consulta, si no hay choques o si la consulta falla.
//
//   <ChoquesDeEstudiantes grupos={[1, 2]} fecha="2026-12-08" hora="08:15"
//       duracion={90} examenId={5} guia={<Link …>Habilitación</Link>} />
export default function ChoquesDeEstudiantes({
    grupos,
    fecha,
    hora,
    duracion,
    examenId = undefined,
    activa = true,
    guia = null,
}) {
    const [abierto, setAbierto] = useState(false);
    const completo =
        grupos.length > 0 &&
        FORMATO_FECHA.test(fecha ?? '') &&
        FORMATO_HORA.test(hora ?? '') &&
        Number.isInteger(duracion) &&
        duracion >= 15 &&
        duracion <= 480;

    const consulta = usarConsulta('/examenes/opciones/choques', {
        parametros: {
            grupos,
            fecha,
            hora_inicio: hora,
            duracion_minutos: duracion,
            examen_id: examenId,
        },
        activa: activa && completo,
    });

    // Con otros parámetros o en curso no se muestra lo que quedó de antes.
    if (!consulta.vigente || consulta.actualizando || consulta.error) return null;
    const total = consulta.datos?.total ?? 0;
    if (total === 0) return null;

    const estudiantes = consulta.datos.estudiantes ?? [];

    return (
        <div
            role="status"
            className="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2.5 text-sm text-warning-800"
        >
            <p className="flex items-start gap-2 font-medium">
                <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>
                    {plural(total, 'estudiante')} {total === 1 ? 'tiene' : 'tienen'} otro examen a
                    esa hora
                </span>
            </p>
            <button
                type="button"
                aria-expanded={abierto}
                onClick={() => setAbierto((a) => !a)}
                className="mt-1 inline-flex min-h-11 items-center gap-1 pl-6 font-medium text-warning-900 underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
            >
                {abierto ? 'Ocultar' : 'Ver quiénes'}
                {abierto ? (
                    <ChevronUp className="h-4 w-4" aria-hidden="true" />
                ) : (
                    <ChevronDown className="h-4 w-4" aria-hidden="true" />
                )}
            </button>
            {abierto && (
                <ul className="mt-1 max-h-72 divide-y divide-warning-200 overflow-y-auto rounded-md bg-white/70 px-3">
                    {estudiantes.map((e) => (
                        <li key={e.codigo} className="py-2">
                            <p className="font-medium text-slate-900">
                                {e.nombre} · {e.codigo}
                            </p>
                            {e.examenes.map((o) => (
                                <p
                                    key={`${o.examen}-${o.inicio}`}
                                    className="text-xs text-slate-700"
                                >
                                    {o.examen} · {o.inicio}–{o.fin}
                                </p>
                            ))}
                        </li>
                    ))}
                    {total > estudiantes.length && (
                        <li className="py-2 text-xs text-slate-700">
                            y {total - estudiantes.length} más
                        </li>
                    )}
                </ul>
            )}
            {guia && <p className="mt-1 pl-6">{guia}</p>}
        </div>
    );
}

ChoquesDeEstudiantes.propTypes = {
    grupos: PropTypes.arrayOf(PropTypes.number).isRequired,
    fecha: PropTypes.string,
    hora: PropTypes.string,
    duracion: PropTypes.number,
    examenId: PropTypes.number,
    activa: PropTypes.bool,
    guia: PropTypes.node,
};
