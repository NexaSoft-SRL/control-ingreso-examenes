import PropTypes from 'prop-types';
import { KeyRound, ScanLine, UserCheck, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import Insignia from '../../componentes/Insignia';
import { plural } from '../../utiles/texto';

const ENLACE =
    'inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600';

// Quien entra por excepción en un aula que no era la suya cuenta en la
// puerta de esa aula: los ingresados pueden pasar de los asignados.
// Asignados a esta aula que no entraron por ninguna puerta (el servidor
// lo da en `faltan`): la misma cifra que la lista del aula.
export const faltanEn = (aula) => aula.faltan ?? Math.max(aula.asignados - aula.ingresados, 0);
export const avanceDe = (aula) =>
    aula.asignados ? Math.min(Math.round((aula.ingresados / aula.asignados) * 100), 100) : 0;

// '2026-10-09T16:05:12-04:00' → '16:05'; vacío si el aula no tiene ingresos.
const horaDe = (instante) => String(instante ?? '').slice(11, 16);

// Por qué los ingresados no cuadran con los asignados: quienes entraron
// por esta puerta siendo de otra aula y los de esta que entraron por otra.
const crucesDe = (aula) =>
    [
        aula.de_otra > 0 && `+${aula.de_otra} de otra aula`,
        aula.en_otra > 0 && `${aula.en_otra} ${aula.en_otra === 1 ? 'entró' : 'entraron'} en otra`,
    ]
        .filter(Boolean)
        .join(' · ');

// Solo cuenta las que faltan atender: lo dice el rótulo.
const textoIncidencias = (n) => `${plural(n, 'incidencia')} sin atender`;

function Barra({ aula, className = '' }) {
    return (
        <div
            className={`overflow-hidden rounded-full bg-slate-200 ${className}`}
            role="progressbar"
            aria-label={`Ingresos del aula ${aula.nombre}`}
            aria-valuenow={aula.ingresados}
            aria-valuemin={0}
            aria-valuemax={Math.max(aula.asignados, aula.ingresados)}
        >
            <div
                className="h-full rounded-full bg-primary-600"
                style={{ width: `${avanceDe(aula)}%` }}
            />
        </div>
    );
}

const formaAula = PropTypes.shape({
    aula_id: PropTypes.number.isRequired,
    nombre: PropTypes.string.isRequired,
    encargado: PropTypes.shape({ nombre: PropTypes.string, es_docente: PropTypes.bool }),
    apoyo: PropTypes.arrayOf(PropTypes.string),
    ingresados: PropTypes.number,
    asignados: PropTypes.number,
    incidencias_sin_atender: PropTypes.number,
    compartida: PropTypes.string,
    usa_codigo: PropTypes.bool,
    a_mi_cargo: PropTypes.bool,
    ultimo_ingreso: PropTypes.string,
});

Barra.propTypes = { aula: formaAula.isRequired, className: PropTypes.string };

// Las aulas del examen (GET /api/examenes/{examen}/en-curso). Con pocas, una
// tarjeta por aula; `denso` las pasa a una rejilla compacta.
export default function EnCursoAulas({ aulas, examenId, denso, abierto, conControl }) {
    const lista = (a) => `/en-curso/aula?examen=${examenId}&aula=${a.aula_id}`;
    const control = (a) => `/control?aula=${a.aula_id}`;

    if (denso) {
        return (
            <ul className="grid min-w-0 grid-cols-2 gap-2 self-start sm:gap-3 xl:grid-cols-3">
                {aulas.map((a) => {
                    const porAtender = a.incidencias_sin_atender ?? 0;
                    const apoyo = a.apoyo ?? [];
                    return (
                        <li
                            key={a.aula_id}
                            className={`flex min-w-0 flex-col rounded-xl border bg-white p-3 ${
                                porAtender
                                    ? 'border-danger-600'
                                    : abierto && a.ingresados === 0
                                      ? 'border-warning-600'
                                      : 'border-slate-200'
                            }`}
                        >
                            <div className="flex items-baseline justify-between gap-2">
                                <h2 className="min-w-0 truncate text-sm font-semibold text-slate-900">
                                    Aula {a.nombre}
                                </h2>
                                <span className="shrink-0 text-sm font-medium tabular-nums text-slate-700">
                                    {a.ingresados} de {a.asignados}
                                </span>
                            </div>
                            <Barra aula={a} className="mt-2 h-1.5" />
                            <p className="mt-2 truncate text-xs font-medium text-slate-800">
                                {a.encargado?.nombre}
                            </p>
                            <p className="truncate text-xs text-slate-600">
                                {porAtender > 0 && (
                                    <span className="font-semibold text-danger-700">
                                        {textoIncidencias(porAtender)} ·{' '}
                                    </span>
                                )}
                                {[
                                    `Faltan ${faltanEn(a)}`,
                                    crucesDe(a),
                                    a.ultimo_ingreso && `Último ${horaDe(a.ultimo_ingreso)}`,
                                    a.encargado?.es_docente ? 'Docente' : 'Auxiliar',
                                    apoyo.length > 0 && `+${apoyo.length} apoyo`,
                                    a.compartida && 'Compartida',
                                    a.usa_codigo && 'Código',
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            <div className="-mx-2 -mb-2 mt-auto flex items-center justify-between pt-1">
                                <Link
                                    to={lista(a)}
                                    className={ENLACE}
                                    aria-label={`Lista del aula ${a.nombre}`}
                                >
                                    Lista
                                </Link>
                                {conControl && (
                                    <Link
                                        to={control(a)}
                                        className={ENLACE}
                                        aria-label={`Controlar el aula ${a.nombre}`}
                                    >
                                        <ScanLine className="h-4 w-4" />{' '}
                                        <span className="max-[22rem]:hidden">Controlar</span>
                                    </Link>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        );
    }

    return (
        <ul className="min-w-0 space-y-3">
            {aulas.map((a) => {
                const porAtender = a.incidencias_sin_atender ?? 0;
                const apoyo = a.apoyo ?? [];
                return (
                    <li
                        key={a.aula_id}
                        className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 sm:p-5"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="min-w-0">
                                <h2 className="text-base font-semibold text-slate-900">
                                    Aula {a.nombre}
                                </h2>
                                <p className="flex items-center gap-1.5 text-sm text-slate-700">
                                    <UserCheck className="h-4 w-4 shrink-0 text-slate-500" />
                                    <span className="min-w-0 truncate">{a.encargado?.nombre}</span>
                                    <span className="shrink-0 text-xs text-slate-600">
                                        · {a.encargado?.es_docente ? 'Docente' : 'Auxiliar a cargo'}
                                    </span>
                                </p>
                                {apoyo.length > 0 && (
                                    <p className="flex items-center gap-1.5 text-sm text-slate-700">
                                        <Users className="h-4 w-4 shrink-0 text-slate-500" />
                                        <span className="min-w-0 truncate">{apoyo.join(', ')}</span>
                                        <span className="shrink-0 text-xs text-slate-600">
                                            · Apoyo
                                        </span>
                                    </p>
                                )}
                                {a.ultimo_ingreso && (
                                    <p className="text-xs tabular-nums text-slate-600">
                                        Último registro {horaDe(a.ultimo_ingreso)}
                                    </p>
                                )}
                            </div>
                            <div className="flex min-w-0 max-w-full flex-wrap gap-2">
                                {porAtender > 0 && (
                                    <Insignia tono="peligro">
                                        {textoIncidencias(porAtender)}
                                    </Insignia>
                                )}
                                {abierto && a.ingresados === 0 && (
                                    <Insignia tono="advertencia">Sin ingresos</Insignia>
                                )}
                                {a.compartida && (
                                    <span
                                        className="min-w-0 max-w-full truncate rounded-full bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700"
                                        title={a.compartida}
                                    >
                                        Compartida · {a.compartida}
                                    </span>
                                )}
                                {a.usa_codigo && (
                                    <Insignia tono="neutro">
                                        <KeyRound className="h-3.5 w-3.5" /> Código de acceso
                                    </Insignia>
                                )}
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <Barra aula={a} className="h-2 min-w-24 flex-1" />
                            <span className="shrink-0 text-sm font-medium tabular-nums text-slate-700">
                                {[
                                    `${a.ingresados} de ${a.asignados}`,
                                    `Faltan ${faltanEn(a)}`,
                                    crucesDe(a),
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                            <Link
                                to={lista(a)}
                                className={ENLACE}
                                aria-label={`Lista del aula ${a.nombre}`}
                            >
                                Lista
                            </Link>
                            {conControl && (
                                <Link
                                    to={control(a)}
                                    className={`-mr-2 ${ENLACE}`}
                                    aria-label={`Controlar el aula ${a.nombre}`}
                                >
                                    <ScanLine className="h-4 w-4" /> Controlar
                                </Link>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

EnCursoAulas.propTypes = {
    aulas: PropTypes.arrayOf(formaAula).isRequired,
    examenId: PropTypes.number.isRequired,
    denso: PropTypes.bool,
    abierto: PropTypes.bool,
    conControl: PropTypes.bool,
};
