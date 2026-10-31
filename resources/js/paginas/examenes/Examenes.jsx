import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { ArrowRight, CalendarDays, Check, Plus, Radio, Timer, UserRound } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Chips from '../../componentes/Chips';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import Insignia from '../../componentes/Insignia';
import { usarSesion } from '../../sesion/SesionContexto';
import { colorDeAsignatura, diaMes, fechaLarga, mesDe, plural } from '../../utiles/texto';
import { FORMA_EXAMEN, PASOS, accionDe, pendiente, tipoDe } from './avanceDelExamen';
import rutaDelExamen from './rutaDelExamen';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ENLACE = `inline-flex min-h-10 shrink-0 items-center rounded-lg text-sm font-medium hover:underline ${FOCO}`;

// Una fila por examen: fecha, asignatura, estado, los cinco pasos a la vista
// y una sola acción, el paso siguiente (o «En curso» el día del examen). Toda
// la tarjeta abre la página del examen, donde están su habilitación, sus
// códigos QR, la edición y la eliminación.
function TarjetaExamen({ e, destacado = false }) {
    const { puede } = usarSesion();
    const accion = accionDe(e, puede('habilitacion'), puede('codigos_qr'));
    // El examen de hoy que no terminó se sigue en vivo.
    const enCurso = e.es_hoy && !e.rendido && e.aulas.length > 0;
    const nombre = e.asignatura.nombre;
    const titulo = `${tipoDe(e)} · ${nombre}`;
    const hechos = PASOS.filter((p) => e.avance[p.clave] === 'ok').length;
    const [dia, mes] = diaMes(e.fecha).split(' ');
    const aulas =
        e.aulas.length === 0
            ? 'Sin aulas'
            : e.aulas.length > 3
              ? plural(e.aulas.length, 'aula')
              : e.aulas.join(', ');

    return (
        <article
            className={`group relative flex min-w-0 flex-col overflow-hidden rounded-xl border bg-white transition-shadow hover:shadow-md md:flex-row ${destacado ? 'border-primary-500 ring-2 ring-primary-500/30' : 'border-slate-200 hover:border-slate-300'}`}
        >
            <div
                className="flex shrink-0 items-center gap-2 border-b border-l-[6px] border-b-slate-200 bg-slate-50 px-4 py-2 text-slate-900 md:w-24 md:flex-col md:justify-center md:gap-0 md:border-b-0 md:border-r md:border-r-slate-200 md:py-3"
                style={{ borderLeftColor: colorDeAsignatura(nombre) }}
            >
                <span className="text-xl font-bold leading-none md:text-2xl">{dia}</span>
                <span className="text-sm font-medium uppercase text-slate-700">{mes}</span>
                <span className="ml-auto text-sm font-medium text-slate-700 md:ml-0">{e.hora}</span>
            </div>
            <div className="min-w-0 flex-1 px-4 py-3 sm:px-5">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <div className="min-w-0 flex-1 basis-64">
                        {/* El estado va con el título: es del examen, no de la acción. */}
                        <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                            <span className="flex min-w-0 items-center gap-2">
                                <IconoAsignatura
                                    nombre={nombre}
                                    color={colorDeAsignatura(nombre)}
                                />
                                <h3 className="min-w-0 break-words text-base font-semibold text-slate-900">
                                    {/* El enlace del título cubre toda la tarjeta. */}
                                    <Link
                                        to={rutaDelExamen(e.id)}
                                        className={`rounded after:absolute after:inset-0 group-hover:text-primary-700 ${FOCO}`}
                                    >
                                        {titulo}
                                    </Link>
                                </h3>
                            </span>
                            <span className="flex shrink-0">
                                <Insignia tono={pendiente(e) ? 'advertencia' : 'exito'} punto>
                                    {e.estado}
                                </Insignia>
                            </span>
                        </div>
                        <p className="text-sm text-slate-600">
                            <span className="sm:truncate">
                                {plural(e.grupos.length, 'grupo')} ·{' '}
                                {plural(e.inscritos, 'inscrito')} · {aulas}
                                {e.junto_con > 0 &&
                                    ` · junto con ${plural(e.junto_con, 'examen', 'exámenes')}`}
                            </span>{' '}
                            <span className="whitespace-nowrap">
                                · {hechos} de {PASOS.length} pasos
                            </span>
                        </p>
                    </div>
                    {/* Lo que se pulsa dentro queda por encima del enlace de la tarjeta. */}
                    <div className="relative z-10 ml-auto flex items-center gap-x-3">
                        {enCurso ? (
                            <Link
                                to={`/en-curso?examen=${e.id}`}
                                className={`inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 ${FOCO}`}
                            >
                                <Radio className="h-4 w-4" /> En curso
                            </Link>
                        ) : (
                            accion && (
                                <Link
                                    to={accion.a}
                                    className={`${ENLACE} gap-1.5 text-primary-700`}
                                >
                                    {accion.texto} <ArrowRight className="h-4 w-4" />
                                </Link>
                            )
                        )}
                    </div>
                </div>

                <ol className="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-5" aria-label="Pasos">
                    {PASOS.map(({ clave, Icono, texto, detalle }) => {
                        const hecho = e.avance[clave] === 'ok';
                        const actual = e.avance[clave] === 'ahora';
                        return (
                            <li
                                key={clave}
                                className={`min-w-0 rounded-lg border p-2.5 ${actual ? 'border-warning-200 bg-warning-50' : 'border-slate-200 bg-white'}`}
                            >
                                <span
                                    className={`flex items-center gap-1.5 text-xs font-semibold ${hecho || actual ? 'text-slate-800' : 'text-slate-600'}`}
                                >
                                    {hecho ? (
                                        <Check
                                            className="h-3.5 w-3.5 shrink-0 text-success-600"
                                            strokeWidth={3}
                                        />
                                    ) : (
                                        <Icono
                                            className={`h-3.5 w-3.5 shrink-0 ${actual ? 'text-warning-700' : 'text-slate-500'}`}
                                        />
                                    )}
                                    <span className="truncate">{texto}</span>
                                </span>
                                <span className="mt-0.5 block truncate text-xs text-slate-600">
                                    {detalle(e)}
                                </span>
                            </li>
                        );
                    })}
                </ol>
                <div className="mt-2 flex min-h-10 flex-wrap items-center">
                    <span className="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarDays className="h-4 w-4 text-slate-400" aria-hidden="true" />
                            {fechaLarga(e.fecha)}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <Timer className="h-4 w-4 text-slate-400" aria-hidden="true" />
                            {e.duracion} min
                        </span>
                        {e.registrado_por && (
                            <span className="inline-flex min-w-0 items-center gap-1.5">
                                <UserRound
                                    className="h-4 w-4 shrink-0 text-slate-400"
                                    aria-hidden="true"
                                />
                                <span className="truncate">{e.registrado_por}</span>
                            </span>
                        )}
                    </span>
                </div>
            </div>
        </article>
    );
}

TarjetaExamen.propTypes = {
    e: FORMA_EXAMEN.isRequired,
    destacado: PropTypes.bool,
};

// Los exámenes del docente en el período, de todas sus asignaturas. El de
// hoy (o el próximo) va primero; el color de la asignatura queda solo en
// el bloque de la fecha. Se filtra por asignatura.
export default function Examenes() {
    const { datos, meta, cargando, error, recargar } = usarConsulta('/examenes');
    const [asignatura, setAsignatura] = useState(null);
    const [aviso, avisar] = useAviso();
    // El aviso que deja la página del examen al eliminarlo.
    const { state } = useLocation();
    const avisoAlVolver = state?.aviso;
    useEffect(() => {
        if (avisoAlVolver) avisar(avisoAlVolver);
    }, [avisoAlVolver, avisar]);

    const examenes = [...(datos ?? [])].sort((a, b) =>
        `${a.fecha}${a.hora}`.localeCompare(`${b.fecha}${b.hora}`)
    );
    const hoy = meta?.hoy ?? null;
    const asignaturas = [
        ...new Map(examenes.map((e) => [e.asignatura.id, e.asignatura])).values(),
    ].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));

    const visible = (e) => asignatura === null || e.asignatura.id === asignatura;
    // El de hoy que no terminó o, si no hay, el próximo.
    const primero = examenes.find((e) => !e.rendido && (!hoy || e.fecha >= hoy)) ?? null;
    const destacado = primero && visible(primero) ? primero : null;
    const lista = examenes.filter((e) => visible(e) && e !== destacado);
    const meses = [...new Set(lista.map((e) => e.fecha.slice(0, 7)))];

    const opciones = [
        { valor: null, etiqueta: 'Todas', conteo: examenes.length },
        ...asignaturas.map((a) => ({
            valor: a.id,
            etiqueta: a.nombre,
            color: colorDeAsignatura(a.nombre),
            conteo: examenes.filter((e) => e.asignatura.id === a.id).length,
        })),
    ];

    const subtitulo = datos
        ? [
              meta?.periodo ? `Período ${meta.periodo}` : null,
              plural(examenes.length, 'examen', 'exámenes'),
              plural(asignaturas.length, 'asignatura'),
          ]
              .filter(Boolean)
              .join(' · ')
        : undefined;

    return (
        <div className="space-y-6">
            <Encabezado titulo="Exámenes" subtitulo={subtitulo}>
                <Link
                    to="/examenes/nuevo"
                    className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 ${FOCO}`}
                >
                    <Plus className="h-4 w-4" strokeWidth={2.5} /> Registrar examen
                </Link>
            </Encabezado>

            <EstadoCarga cargando={cargando} error={error} onReintentar={recargar}>
                <Chips
                    opciones={opciones}
                    valor={asignatura}
                    onCambiar={setAsignatura}
                    etiqueta="Asignatura"
                    maximo={7}
                />

                {destacado && (
                    <section className="space-y-2">
                        <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                            {destacado.fecha === hoy ? 'Hoy' : 'Próximo'}
                        </h2>
                        <TarjetaExamen e={destacado} destacado />
                    </section>
                )}

                {meses.map((mes) => {
                    const delMes = lista.filter((e) => e.fecha.startsWith(mes));
                    return (
                        <section key={mes} className="space-y-2">
                            <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {mesDe(`${mes}-01`)} · {delMes.length}
                            </h2>
                            {delMes.map((e) => (
                                <TarjetaExamen key={e.id} e={e} />
                            ))}
                        </section>
                    );
                })}

                {!destacado && lista.length === 0 && (
                    <p className="py-10 text-center text-sm text-slate-600">Sin exámenes</p>
                )}
            </EstadoCarga>

            {aviso}
        </div>
    );
}
