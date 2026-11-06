import PropTypes from 'prop-types';
import { useState } from 'react';
import {
    ArrowRight,
    BarChart3,
    CalendarDays,
    Check,
    ClipboardList,
    DoorOpen,
    History,
    Pencil,
    QrCode,
    Radio,
    Timer,
    Trash2,
    UserCheck,
    UserRound,
} from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import usarConsulta from '../../api/usarConsulta';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import Insignia from '../../componentes/Insignia';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { colorDeAsignatura, fechaLarga, plural } from '../../utiles/texto';
import { ListaDelExamen } from '../habilitacion/Habilitacion';
import { CodigosDelExamen } from './CodigosQr';
import EliminarExamen from './EliminarExamen';
import { FORMA_EXAMEN, PASOS, accionDe, pendiente, tipoDe } from './avanceDelExamen';
import rutaDelExamen from './rutaDelExamen';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const BOTON = `inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium ${FOCO}`;
const VOLVER = { a: '/examenes', texto: 'Exámenes' };

// Las pestañas de la página. Habilitación y códigos QR piden su permiso y
// que el examen ya tenga aulas. La bitácora del examen (HU-29) se suma aquí.
const PESTANAS = [
    { clave: 'resumen', texto: 'Resumen', Icono: ClipboardList },
    { clave: 'habilitacion', texto: 'Habilitación', Icono: UserCheck, permiso: 'habilitacion' },
    { clave: 'qr', texto: 'Códigos QR', Icono: QrCode, permiso: 'codigos_qr' },
];

// A dónde lleva cada paso del resumen. Los tres primeros son del registro
// (solo para quien lo registró); los otros dos, pestañas de esta página.
function destinoDe(clave, e) {
    const paso = { grupos: 1, aulas: 2, auxiliares: 3 }[clave];
    if (paso !== undefined) return e.propio ? `/examenes/nuevo?examen=${e.id}&paso=${paso}` : null;
    return rutaDelExamen(e.id, clave);
}

function Resumen({ e, accion }) {
    return (
        <div className="space-y-6">
            <Tarjeta titulo="Avance" icono={ClipboardList}>
                <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Pasos">
                    {PASOS.map(({ clave, Icono, texto, detalle }) => {
                        const hecho = e.avance[clave] === 'ok';
                        const actual = e.avance[clave] === 'ahora';
                        const destino = destinoDe(clave, e);
                        const contenido = (
                            <>
                                <span
                                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${
                                        hecho
                                            ? 'bg-success-100 text-success-700'
                                            : actual
                                              ? 'bg-warning-100 text-warning-700'
                                              : 'bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {hecho ? (
                                        <Check className="h-4 w-4" strokeWidth={3} />
                                    ) : (
                                        <Icono className="h-4 w-4" />
                                    )}
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold text-slate-900">
                                        {texto}
                                    </span>
                                    <span className="block truncate text-xs text-slate-600">
                                        {detalle(e)}
                                    </span>
                                </span>
                            </>
                        );
                        const clase = `flex h-full min-w-0 items-center gap-3 rounded-xl border p-3 ${
                            actual
                                ? 'border-warning-200 bg-warning-50'
                                : 'border-slate-200 bg-white'
                        }`;
                        return (
                            <li key={clave} className="min-w-0">
                                {destino ? (
                                    <Link
                                        to={destino}
                                        className={`${clase} hover:border-primary-300 hover:shadow-sm ${FOCO}`}
                                    >
                                        {contenido}
                                    </Link>
                                ) : (
                                    <div className={clase}>{contenido}</div>
                                )}
                            </li>
                        );
                    })}
                </ol>
                {accion && (
                    <div className="mt-4 flex justify-end">
                        <Link
                            to={accion.a}
                            className={`${BOTON} bg-primary-600 text-white hover:bg-primary-700`}
                        >
                            {accion.texto} <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                )}
            </Tarjeta>

            <Tarjeta titulo="Datos del examen" icono={CalendarDays}>
                <dl className="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        [CalendarDays, 'Fecha', fechaLarga(e.fecha)],
                        [Timer, 'Horario', `${e.hora} · ${e.duracion} min`],
                        [DoorOpen, 'Aulas', e.aulas.length ? e.aulas.join(', ') : 'Sin elegir'],
                        [UserRound, 'Registrado por', e.registrado_por ?? '—'],
                    ].map(([Icono, etiqueta, valor]) => (
                        <div key={etiqueta} className="flex min-w-0 gap-3">
                            <Icono
                                className="mt-0.5 h-4 w-4 shrink-0 text-slate-400"
                                aria-hidden="true"
                            />
                            <div className="min-w-0">
                                <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    {etiqueta}
                                </dt>
                                <dd className="break-words font-medium text-slate-900">{valor}</dd>
                            </div>
                        </div>
                    ))}
                </dl>
                <p className="mt-4 text-sm text-slate-600">
                    {plural(e.grupos.length, 'grupo')} ({e.grupos.join(', ')}) ·{' '}
                    {plural(e.inscritos, 'inscrito')}
                    {e.junto_con > 0 && ` · junto con ${plural(e.junto_con, 'examen', 'exámenes')}`}
                </p>
            </Tarjeta>
        </div>
    );
}

Resumen.propTypes = {
    e: FORMA_EXAMEN.isRequired,
    accion: PropTypes.shape({ texto: PropTypes.string, a: PropTypes.string }),
};

// HU-28: la página de un examen (`?examen=`): su resumen, su habilitación y
// sus códigos QR en pestañas (`?pestana=`), para no saltar entre pantallas.
export default function DetalleExamen() {
    const { datos, cargando, error, recargar } = usarConsulta('/examenes');
    const [parametros, setParametros] = useSearchParams();
    const { puede } = usarSesion();
    const navegar = useNavigate();
    const [porEliminar, setPorEliminar] = useState(null);
    const id = Number(parametros.get('examen')) || null;
    const e = (datos ?? []).find((x) => x.id === id) ?? null;

    const disponible = (p) => !p.permiso || (puede(p.permiso) && (e?.aulas.length ?? 0) > 0);
    const pedida = PESTANAS.find((p) => p.clave === parametros.get('pestana'));
    const pestana = pedida && disponible(pedida) ? pedida.clave : 'resumen';

    const ir = (clave) => {
        setParametros(
            (previos) => {
                const siguiente = new URLSearchParams(previos);
                if (clave === 'resumen') siguiente.delete('pestana');
                else siguiente.set('pestana', clave);
                return siguiente;
            },
            { replace: true }
        );
        // Lo hecho en una pestaña cambia las cifras de las otras.
        recargar();
    };

    if (!e) {
        return (
            <div className="space-y-6">
                <Encabezado titulo="Examen" volver={VOLVER} />
                <Tarjeta sinRelleno>
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio
                        textoVacio="Examen no encontrado"
                        onReintentar={recargar}
                    />
                </Tarjeta>
            </div>
        );
    }

    const nombre = e.asignatura.nombre;
    const accion = accionDe(e, puede('habilitacion'), puede('codigos_qr'));
    const enCurso = e.es_hoy && !e.rendido && e.aulas.length > 0;
    const contador = {
        habilitacion: e.inscritos ? `${e.habilitados ?? 0}/${e.inscritos}` : null,
        qr: e.habilitados ? `${e.qr_emitidos ?? 0}/${e.habilitados}` : null,
    };

    return (
        <div className="space-y-6">
            <Encabezado
                titulo={
                    <span className="flex min-w-0 items-center gap-3">
                        <IconoAsignatura
                            nombre={nombre}
                            color={colorDeAsignatura(nombre)}
                            tamano="md"
                        />
                        <span className="min-w-0 break-words">
                            {tipoDe(e)} · {nombre}
                        </span>
                    </span>
                }
                subtitulo={
                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span>
                            {fechaLarga(e.fecha)} · {e.hora} · {e.duracion} min
                        </span>
                        <Insignia tono={pendiente(e) ? 'advertencia' : 'exito'} punto>
                            {e.estado}
                        </Insignia>
                    </span>
                }
                volver={VOLVER}
            >
                {enCurso && (
                    <Link
                        to={`/en-curso?examen=${e.id}`}
                        className={`${BOTON} bg-primary-600 text-white hover:bg-primary-700`}
                    >
                        <Radio className="h-4 w-4" /> En curso
                    </Link>
                )}
                {/* Lo que pasó en un examen ya tomado se ve en su reporte. */}
                {e.rendido && puede('reportes_examenes') && (
                    <Link
                        to={`/reportes?examen=${e.id}`}
                        className={`${BOTON} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50`}
                    >
                        <BarChart3 className="h-4 w-4" /> Reporte
                    </Link>
                )}
                {/* La bitácora de exámenes, con este examen elegido. */}
                {puede('reportes_examenes') && (
                    <Link
                        to={`/bitacora-examen?examen=${e.id}`}
                        className={`${BOTON} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50`}
                    >
                        <History className="h-4 w-4" /> Bitácora
                    </Link>
                )}
                {e.propio && (
                    <Link
                        to={`/examenes/nuevo?examen=${e.id}`}
                        className={`${BOTON} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50`}
                    >
                        <Pencil className="h-4 w-4" /> Editar
                    </Link>
                )}
                {/* Con ingresos o incidencias el examen ya es historia. */}
                {e.propio && !e.con_ingresos && !e.con_incidencias && (
                    <button
                        type="button"
                        onClick={() => setPorEliminar(e)}
                        className={`${BOTON} border border-danger-200 bg-white text-danger-700 hover:bg-danger-50`}
                    >
                        <Trash2 className="h-4 w-4" /> Eliminar
                    </button>
                )}
            </Encabezado>

            <div
                role="tablist"
                aria-label="Secciones del examen"
                className="flex gap-1 border-b border-slate-200"
            >
                {PESTANAS.map((p) => {
                    const activa = p.clave === pestana;
                    const permitida = disponible(p);
                    return (
                        <button
                            key={p.clave}
                            type="button"
                            role="tab"
                            aria-selected={activa}
                            disabled={!permitida}
                            title={!permitida ? 'Primero elija las aulas del examen' : undefined}
                            onClick={() => ir(p.clave)}
                            className={`-mb-px flex min-h-11 min-w-0 flex-1 items-center justify-center gap-1.5 whitespace-nowrap border-b-2 px-2 text-sm font-medium sm:flex-none sm:gap-2 sm:px-4 ${FOCO} ${
                                activa
                                    ? 'border-primary-600 text-primary-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900 disabled:cursor-not-allowed disabled:text-slate-400 disabled:hover:border-transparent'
                            }`}
                        >
                            <p.Icono className="hidden h-4 w-4 sm:block" aria-hidden="true" />
                            {p.texto}
                            {contador[p.clave] && (
                                <span
                                    className={`hidden rounded-full px-2 py-0.5 text-xs sm:inline ${
                                        activa
                                            ? 'bg-primary-100 text-primary-700'
                                            : 'bg-slate-100 text-slate-600'
                                    }`}
                                >
                                    {contador[p.clave]}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <div role="tabpanel" aria-label={PESTANAS.find((p) => p.clave === pestana).texto}>
                {pestana === 'resumen' && <Resumen e={e} accion={accion} />}
                {pestana === 'habilitacion' && <ListaDelExamen key={e.id} examenId={e.id} />}
                {pestana === 'qr' && <CodigosDelExamen key={e.id} examen={e} incrustado />}
            </div>

            <EliminarExamen
                examen={porEliminar}
                onCerrar={() => setPorEliminar(null)}
                onEliminado={() =>
                    navegar('/examenes', { replace: true, state: { aviso: 'Examen eliminado' } })
                }
            />
        </div>
    );
}
