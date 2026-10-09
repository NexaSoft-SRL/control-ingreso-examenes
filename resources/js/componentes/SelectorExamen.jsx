import PropTypes from 'prop-types';
import { colorDeAsignatura, diaMes } from '../utiles/texto';

// Los exámenes llegan de GET /api/examenes: la asignatura es un objeto.
function nombreDe(examen) {
    return examen.asignatura?.nombre ?? examen.asignatura ?? '';
}

function textoDe(examen) {
    return `${diaMes(examen.fecha)} · ${examen.tipo_texto ?? examen.tipo} · ${nombreDe(examen)}`;
}

// Con qué examen se está trabajando. Va arriba de las pantallas que
// dependen de un examen, porque el docente puede tener varios, de
// distintas asignaturas. `examenes` es la lista (o una parte: por ejemplo,
// los de hoy). Con muchos exámenes, la lista se agrupa por asignatura.
export default function SelectorExamen({ valor, onCambiar, examenes }) {
    const examen = examenes.find((e) => e.id === valor) ?? examenes[0];
    if (!examen) return null;

    return (
        <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/40">
            <span
                className="h-10 w-1.5 shrink-0 rounded-full"
                style={{ backgroundColor: colorDeAsignatura(nombreDe(examen)) }}
            />
            <span className="min-w-0 flex-1">
                <span className="block text-xs font-medium uppercase tracking-wide text-slate-600">
                    Examen
                </span>
                <select
                    className="w-full truncate bg-transparent bg-[position:right_0_center] py-0.5 text-sm font-semibold text-slate-900 focus:outline-none"
                    value={examen.id}
                    onChange={(e) => onCambiar(Number(e.target.value))}
                >
                    {examenes.length <= 6
                        ? examenes.map((e) => (
                              <option key={e.id} value={e.id}>
                                  {textoDe(e)}
                              </option>
                          ))
                        : [...new Set(examenes.map(nombreDe))].map((asignatura) => (
                              <optgroup key={asignatura} label={asignatura}>
                                  {examenes
                                      .filter((e) => nombreDe(e) === asignatura)
                                      .map((e) => (
                                          <option key={e.id} value={e.id}>
                                              {textoDe(e)}
                                          </option>
                                      ))}
                              </optgroup>
                          ))}
                </select>
            </span>
        </label>
    );
}

SelectorExamen.propTypes = {
    valor: PropTypes.number,
    onCambiar: PropTypes.func.isRequired,
    examenes: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            fecha: PropTypes.string,
            tipo: PropTypes.string,
            tipo_texto: PropTypes.string,
            asignatura: PropTypes.oneOfType([PropTypes.string, PropTypes.object]),
        })
    ).isRequired,
};
