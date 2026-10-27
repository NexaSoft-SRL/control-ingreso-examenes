import PropTypes from 'prop-types';
import { KeyRound, ScanLine, UserCheck, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import Insignia from '../../componentes/Insignia';
import { plural } from '../../utiles/texto';

const ENLACE =
    'inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600';

// El avance del aula: ingresados sobre asignados, sin pasar del 100 %
// (quien entra por excepción cuenta en la puerta del aula por la que entró).
export const avanceDe = (aula) =>
    aula.asignados ? Math.min(Math.round((aula.ingresados / aula.asignados) * 100), 100) : 0;

// '2026-10-09T16:05:12-04:00' → '16:05'.
export const horaDe = (instante) => String(instante ?? '').slice(11, 16);

// Solo cuenta las que faltan atender: lo dice el rótulo.
const textoIncidencias = (n) => `${plural(n, 'incidencia')} sin atender`;

// Por qué los ingresados no cuadran con los asignados: quienes entraron
// por esta puerta siendo de otra aula y los de esta que entraron por otra.
const crucesDe = (aula) =>
    [
        aula.de_otra > 0 && `+${aula.de_otra} de otra aula`,
        aula.en_otra > 0 && `${aula.en_otra} ${aula.en_otra === 1 ? 'entró' : 'entraron'} en otra`,
    ]
        .filter(Boolean)
        .join(' · ');

function encargadoDe(aula) {
    if (!aula.a_cargo) return { nombre: 'Sin responsable', rol: null };
    return {
        nombre: aula.a_cargo.nombre,
        rol: aula.a_cargo.es_docente ? 'Docente' : 'Auxiliar a cargo',
    };
}

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

const formaPersona = PropTypes.shape({
    id: PropTypes.number,
    nombre: PropTypes.string,
    es_docente: PropTypes.bool,
});

const formaAula = PropTypes.shape({
    aula_id: PropTypes.number.isRequired,
    nombre: PropTypes.string.isRequired,
    a_cargo: formaPersona,
    apoyo: PropTypes.arrayOf(formaPersona),
    ingresados: PropTypes.number,
    asignados: PropTypes.number,
    faltan: PropTypes.number,
    ultimo_registro: PropTypes.string,
    de_otra: PropTypes.number,
    en_otra: PropTypes.number,
    incidencias_sin_atender: PropTypes.number,
    compartida: PropTypes.arrayOf(PropTypes.string),
    codigo_acceso: PropTypes.bool,
    a_mi_cargo: PropTypes.bool,
    puede_operar: PropTypes.bool,
});

Barra.propTypes = { aula: formaAula.isRequired, className: PropTypes.string };

function Enlaces({ aula, lista, control, compacto = false }) {
    return (
        <>
            {lista && (
                <Link to={lista} className={ENLACE} aria-label={`Lista del aula ${aula.nombre}`}>
                    Lista
                </Link>
            )}
            {control && (
                <Link
                    to={control}
                    className={ENLACE}
                    aria-label={`Controlar el aula ${aula.nombre}`}
                >
                    <ScanLine className="h-4 w-4" />
                    <span className={compacto ? 'max-[22rem]:hidden' : ''}>Controlar</span>
                </Link>
            )}
        </>
    );
}

Enlaces.propTypes = {
    aula: formaAula.isRequired,
    lista: PropTypes.string,
    control: PropTypes.string,
    compacto: PropTypes.bool,
};

// Las aulas del examen en curso (GET /api/en-curso/{examen}). Con pocas, una
// tarjeta por aula; `denso` las pasa a una rejilla compacta. «Lista» abre el
// seguimiento del aula (HU-21) y «Controlar», su puerta (HU-19), solo en
// las aulas cuya puerta la cuenta opera (`puede_operar`, del servidor).
export default function EnCursoAulas({ aulas, examenId, denso, abierto, conLista, conControl }) {
    const lista = (a) => (conLista ? `/seguimiento?examen=${examenId}&aula=${a.aula_id}` : null);
    const control = (a) =>
        conControl && a.puede_operar ? `/punto-control?aula=${a.aula_id}` : null;

    if (denso) {
        return (
            <ul className="grid min-w-0 grid-cols-2 gap-2 self-start sm:gap-3 xl:grid-cols-3">
                {aulas.map((a) => {
                    const porAtender = a.incidencias_sin_atender ?? 0;
                    const apoyo = a.apoyo ?? [];
                    const encargado = encargadoDe(a);
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
                                <h3 className="min-w-0 truncate text-sm font-semibold text-slate-900">
                                    Aula {a.nombre}
                                </h3>
                                <span className="shrink-0 text-sm font-medium tabular-nums text-slate-700">
                                    {a.ingresados} de {a.asignados}
                                </span>
                            </div>
                            <Barra aula={a} className="mt-2 h-1.5" />
                            <p className="mt-2 truncate text-xs font-medium text-slate-800">
                                {encargado.nombre}
                            </p>
                            <p className="truncate text-xs text-slate-600">
                                {porAtender > 0 && (
                                    <span className="font-semibold text-danger-700">
                                        {textoIncidencias(porAtender)} ·{' '}
                                    </span>
                                )}
                                {[
                                    `Faltan ${a.faltan}`,
                                    crucesDe(a),
                                    a.ultimo_registro && `Último ${horaDe(a.ultimo_registro)}`,
                                    encargado.rol,
                                    apoyo.length > 0 && `+${apoyo.length} apoyo`,
                                    a.compartida?.length > 0 && 'Compartida',
                                    a.codigo_acceso && 'Código',
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            <div className="-mx-2 -mb-2 mt-auto flex items-center justify-between pt-1">
                                <Enlaces aula={a} lista={lista(a)} control={control(a)} compacto />
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
                const encargado = encargadoDe(a);
                const compartida = (a.compartida ?? []).join(', ');
                return (
                    <li
                        key={a.aula_id}
                        className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 sm:p-5"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="min-w-0">
                                <h3 className="text-base font-semibold text-slate-900">
                                    Aula {a.nombre}
                                </h3>
                                <p className="flex items-center gap-1.5 text-sm text-slate-700">
                                    <UserCheck className="h-4 w-4 shrink-0 text-slate-500" />
                                    <span className="min-w-0 truncate">{encargado.nombre}</span>
                                    {encargado.rol && (
                                        <span className="shrink-0 text-xs text-slate-600">
                                            · {encargado.rol}
                                        </span>
                                    )}
                                </p>
                                {apoyo.length > 0 && (
                                    <p className="flex items-center gap-1.5 text-sm text-slate-700">
                                        <Users className="h-4 w-4 shrink-0 text-slate-500" />
                                        <span className="min-w-0 truncate">
                                            {apoyo.map((p) => p.nombre).join(', ')}
                                        </span>
                                        <span className="shrink-0 text-xs text-slate-600">
                                            · Apoyo
                                        </span>
                                    </p>
                                )}
                                {a.ultimo_registro && (
                                    <p className="text-xs tabular-nums text-slate-600">
                                        Último registro {horaDe(a.ultimo_registro)}
                                    </p>
                                )}
                            </div>
                            <div className="flex min-w-0 max-w-full flex-wrap gap-2">
                                {porAtender > 0 && (
                                    <Insignia tono="peligro">
                                        {textoIncidencias(porAtender)}
                                    </Insignia>
                                )}
                                {compartida && (
                                    <span
                                        className="min-w-0 max-w-full truncate rounded-full bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700"
                                        title={compartida}
                                    >
                                        Compartida · {compartida}
                                    </span>
                                )}
                                {a.codigo_acceso && (
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
                                    `Faltan ${a.faltan}`,
                                    crucesDe(a),
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                            <Enlaces aula={a} lista={lista(a)} control={control(a)} />
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
    conLista: PropTypes.bool,
    conControl: PropTypes.bool,
};
