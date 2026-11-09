import PropTypes from 'prop-types';
import { Check, ChevronDown } from 'lucide-react';
import { colorDeAsignatura, diaMes } from '../utiles/texto';
import IconoAsignatura from './IconoAsignatura';
import usarDesplegable from './usarDesplegable';

// Con más exámenes que estos, la lista se agrupa por asignatura.
const SIN_GRUPOS = 6;

// Los exámenes llegan de GET /api/examenes: la asignatura es un objeto.
function nombreDe(examen) {
    return examen.asignatura?.nombre ?? examen.asignatura ?? '';
}

const tipoDe = (examen) => examen.tipo_texto ?? examen.tipo;
const cuandoDe = (examen) => [diaMes(examen.fecha), examen.hora].filter(Boolean).join(' · ');

// Con qué examen se está trabajando. Va arriba de las pantallas que
// dependen de un examen, porque el docente puede tener varios, de
// distintas asignaturas. `examenes` es la lista (o una parte: por ejemplo,
// los de hoy). Cada examen se ve con el ícono de su asignatura, su tipo y su
// fecha; con muchos, la lista se agrupa por asignatura.
export default function SelectorExamen({ valor, onCambiar, examenes: recibidos }) {
    // Por fecha, como en el resto de las pantallas.
    const examenes = [...recibidos].sort((a, b) =>
        `${a.fecha ?? ''} ${a.hora ?? ''}`.localeCompare(`${b.fecha ?? ''} ${b.hora ?? ''}`)
    );
    const agrupada = examenes.length > SIN_GRUPOS;
    const asignaturas = [...new Set(examenes.map(nombreDe))];
    // El orden en que se dibujan (y se recorren con el teclado).
    const opciones = agrupada
        ? asignaturas.flatMap((a) => examenes.filter((e) => nombreDe(e) === a))
        : examenes;
    const examen = examenes.find((e) => e.id === valor) ?? examenes[0];
    const d = usarDesplegable({
        opciones,
        elegida: examen,
        alElegir: (e) => onCambiar(e.id),
        texto: nombreDe,
    });
    if (!examen) return null;

    const opcion = (e) => {
        const i = opciones.indexOf(e);
        const marcada = e.id === examen.id;
        return (
            <li
                key={e.id}
                id={d.idDe(i)}
                role="option"
                aria-selected={marcada}
                onMouseDown={(ev) => ev.preventDefault()}
                onMouseEnter={() => d.setActiva(i)}
                onClick={() => d.elegir(e)}
                className={`flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 ${
                    i === d.activa ? 'bg-slate-100' : ''
                } ${marcada ? 'bg-primary-50' : ''}`}
            >
                <span aria-hidden="true">
                    <IconoAsignatura
                        nombre={nombreDe(e)}
                        color={colorDeAsignatura(nombreDe(e))}
                        tamano={agrupada ? 'sm' : 'md'}
                    />
                </span>
                <span className="min-w-0 flex-1">
                    <span
                        className={`block truncate text-sm ${
                            marcada
                                ? 'font-semibold text-primary-800'
                                : 'font-medium text-slate-900'
                        }`}
                    >
                        {agrupada ? tipoDe(e) : nombreDe(e)}
                    </span>
                    <span className="block text-xs text-slate-600">
                        {agrupada ? cuandoDe(e) : `${tipoDe(e)} · ${cuandoDe(e)}`}
                    </span>
                </span>
                {marcada && (
                    <Check className="h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                )}
            </li>
        );
    };

    return (
        <div ref={d.caja} className="relative">
            <button
                type="button"
                aria-haspopup="listbox"
                aria-expanded={d.abierta}
                aria-label={`Examen: ${diaMes(examen.fecha)} · ${tipoDe(examen)} · ${nombreDe(examen)}`}
                aria-controls={d.abierta ? `${d.id}-lista` : undefined}
                aria-activedescendant={d.abierta ? d.idDe(d.activa) : undefined}
                onClick={d.alternar}
                onKeyDown={d.teclas}
                className={`flex w-full items-center gap-3 rounded-xl border bg-white px-4 py-3 text-left focus:outline-none focus-visible:border-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/40 ${
                    d.abierta ? 'border-primary-500 ring-2 ring-primary-500/40' : 'border-slate-200'
                }`}
            >
                <IconoAsignatura
                    nombre={nombreDe(examen)}
                    color={colorDeAsignatura(nombreDe(examen))}
                    tamano="md"
                />
                <span className="min-w-0 flex-1">
                    <span className="block text-xs font-medium uppercase tracking-wide text-slate-600">
                        Examen
                    </span>
                    <span className="block truncate text-sm font-semibold text-slate-900">
                        {nombreDe(examen)}
                    </span>
                    <span className="block text-xs text-slate-600">
                        {tipoDe(examen)} · {cuandoDe(examen)}
                    </span>
                </span>
                {examenes.length > 1 && (
                    <ChevronDown
                        className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${d.abierta ? 'rotate-180' : ''}`}
                        aria-hidden="true"
                    />
                )}
            </button>

            {d.abierta && (
                <ul
                    ref={d.lista}
                    id={`${d.id}-lista`}
                    role="listbox"
                    aria-label="Exámenes"
                    className="absolute z-30 mt-1.5 max-h-96 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg"
                >
                    {agrupada
                        ? asignaturas.map((a) => (
                              <li key={a} role="presentation">
                                  <ul role="group" aria-label={a}>
                                      <li
                                          role="presentation"
                                          className="flex items-center gap-2 px-2.5 pb-1 pt-2 text-xs font-semibold text-slate-600"
                                      >
                                          <span aria-hidden="true">
                                              <IconoAsignatura
                                                  nombre={a}
                                                  color={colorDeAsignatura(a)}
                                                  tamano="xs"
                                              />
                                          </span>
                                          {a}
                                      </li>
                                      {examenes.filter((e) => nombreDe(e) === a).map(opcion)}
                                  </ul>
                              </li>
                          ))
                        : examenes.map(opcion)}
                </ul>
            )}
        </div>
    );
}

SelectorExamen.propTypes = {
    valor: PropTypes.number,
    onCambiar: PropTypes.func.isRequired,
    examenes: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            fecha: PropTypes.string,
            hora: PropTypes.string,
            tipo: PropTypes.string,
            tipo_texto: PropTypes.string,
            asignatura: PropTypes.oneOfType([PropTypes.string, PropTypes.object]),
        })
    ).isRequired,
};
