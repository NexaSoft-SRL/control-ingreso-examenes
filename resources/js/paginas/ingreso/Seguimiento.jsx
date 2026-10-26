import PropTypes from 'prop-types';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    Check,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    RefreshCw,
    ScanLine,
    Search,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { estadoDe, mensajeDe } from '../../api/errores';
import { useAviso } from '../../componentes/Aviso';
import { usarSesion } from '../../sesion/SesionContexto';
import IncidenciaAula from './IncidenciaAula';
import { tipoIncidenciaLegible } from './tiposIncidencia';

// La búsqueda viaja al servidor 300 ms después de la última tecla.
const ESPERA_BUSQUEDA = 300;

function nombreEstudiante(estudiante) {
    return `${estudiante.apellidos}, ${estudiante.nombres}`;
}

function fechaYHora(fecha, hora) {
    if (!fecha) return '';
    const [anio, mes, dia] = fecha.split('-');
    return `${dia}/${mes}/${anio}${hora ? ` · ${hora}` : ''}`;
}

// '2026-10-26 08:41:20' o '2026-10-26T08:41:20-04:00' → '08:41'.
const horaDe = (instante) => String(instante ?? '').slice(11, 16);

function tipoExamenLegible(tipo) {
    const etiquetas = {
        PRIMER_PARCIAL: 'Primer parcial',
        SEGUNDO_PARCIAL: 'Segundo parcial',
        FINAL: 'Examen final',
        SEGUNDA_INSTANCIA: 'Segunda instancia',
        MESA: 'Examen de mesa',
    };
    return etiquetas[tipo] ?? tipo;
}

function Cifras({ cifras }) {
    return (
        <dl className="grid grid-cols-3 gap-2">
            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                <dt className="text-xs font-medium text-emerald-800">Ingresaron</dt>
                <dd className="mt-1 text-2xl font-bold tabular-nums text-emerald-950">
                    {cifras.ingresaron}
                </dd>
            </div>
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-3">
                <dt className="text-xs font-medium text-amber-800">Faltan</dt>
                <dd className="mt-1 text-2xl font-bold tabular-nums text-amber-950">
                    {cifras.faltan}
                </dd>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-3">
                <dt className="text-xs font-medium text-slate-600">Asignados</dt>
                <dd className="mt-1 text-2xl font-bold tabular-nums text-slate-950">
                    {cifras.asignados}
                </dd>
            </div>
        </dl>
    );
}

Cifras.propTypes = {
    cifras: PropTypes.shape({
        ingresaron: PropTypes.number.isRequired,
        faltan: PropTypes.number.isRequired,
        asignados: PropTypes.number.isRequired,
    }).isRequired,
};

// «Mi aula» (HU-21): presentes, ausentes e incidencias de un aula del examen
// de hoy. El auxiliar ve las aulas que atiende; el docente llega desde «En
// curso» a cualquiera de su examen. Es de consulta: no registra ingresos.
export default function Seguimiento() {
    const { usuario, puede } = usarSesion();
    const esDocente = usuario?.rol === 'Docente';
    const [aviso, avisar] = useAviso();
    const [aulas, setAulas] = useState([]);
    // `?examen=&aula=` llega desde «En curso» (HU-22) con el aula ya elegida.
    const [parametros] = useSearchParams();
    const [pedida, setPedida] = useState(() => {
        const examenId = Number(parametros.get('examen'));
        const aulaId = Number(parametros.get('aula'));
        return examenId && aulaId ? { examen_id: examenId, aula_id: aulaId } : null;
    });
    const [seleccion, setSeleccion] = useState(pedida);
    const [detalle, setDetalle] = useState(null);
    const [estado, setEstado] = useState('faltan');
    const [busqueda, setBusqueda] = useState('');
    const [buscado, setBuscado] = useState('');
    const [pagina, setPagina] = useState(1);
    const [cargando, setCargando] = useState(true);
    const [actualizando, setActualizando] = useState(false);
    const [error, setError] = useState('');
    const [desconectado, setDesconectado] = useState(false);
    const [accion, setAccion] = useState('');
    const errores = useRef(0);
    const controlador = useRef(null);
    const seleccionRef = useRef(seleccion);
    seleccionRef.current = seleccion;
    const seleccionada = useMemo(
        () =>
            aulas.find(
                (aula) =>
                    aula.examen_id === seleccion?.examen_id && aula.aula_id === seleccion?.aula_id
            ) ?? null,
        [aulas, seleccion]
    );

    const cargarAulas = useCallback(async (senal) => {
        try {
            const { data } = await api.get('/seguimiento/aulas', { signal: senal });
            const lista = Array.isArray(data?.data) ? data.data : [];
            setAulas(lista);
            const actual = seleccionRef.current;
            if (
                !actual ||
                !lista.some(
                    (aula) => aula.examen_id === actual.examen_id && aula.aula_id === actual.aula_id
                )
            ) {
                const primera = lista[0];
                setSeleccion(
                    primera ? { examen_id: primera.examen_id, aula_id: primera.aula_id } : null
                );
            }
        } catch (fallo) {
            if (estadoDe(fallo) !== 0) {
                setError(mensajeDe(fallo, 'No se pudieron cargar las aulas.'));
            }
        } finally {
            setCargando(false);
        }
    }, []);

    const actualizar = useCallback(
        async ({ silencioso = false } = {}) => {
            if (!seleccion || document.hidden) return;
            controlador.current?.abort();
            const actual = new AbortController();
            controlador.current = actual;
            if (!silencioso) setActualizando(true);
            try {
                const { data } = await api.get(
                    `/seguimiento/examenes/${seleccion.examen_id}/aulas/${seleccion.aula_id}`,
                    {
                        params: { estado, buscar: buscado, pagina },
                        signal: actual.signal,
                    }
                );
                if (actual.signal.aborted) return;
                setDetalle(data?.data ?? null);
                setError('');
                errores.current = 0;
                setDesconectado(false);
            } catch (fallo) {
                if (actual.signal.aborted) return;
                const codigoEstado = estadoDe(fallo);
                if (codigoEstado === null) {
                    errores.current += 1;
                    if (errores.current >= 3) setDesconectado(true);
                    return;
                }

                errores.current = 0;
                setDesconectado(false);
                const mensaje =
                    codigoEstado === 401 || codigoEstado === 419
                        ? 'Sesión vencida'
                        : codigoEstado === 403 || codigoEstado === 404
                          ? mensajeDe(fallo, 'Sin acceso')
                          : codigoEstado >= 500
                            ? 'No se pudo actualizar la lista'
                            : mensajeDe(fallo, 'No se pudo actualizar la lista');
                setError(mensaje);
            } finally {
                if (!actual.signal.aborted) setActualizando(false);
            }
        },
        [buscado, estado, pagina, seleccion]
    );

    useEffect(() => {
        const actual = new AbortController();
        void cargarAulas(actual.signal);
        return () => {
            actual.abort();
            controlador.current?.abort();
        };
    }, [cargarAulas]);

    useEffect(() => {
        setDetalle(null);
    }, [seleccion]);

    useEffect(() => {
        if (seleccion) void actualizar();
    }, [actualizar, seleccion]);

    useEffect(() => {
        const intervalo = window.setInterval(() => {
            if (!document.hidden) void actualizar({ silencioso: true });
        }, 15_000);
        const visible = () => {
            if (!document.hidden) void actualizar({ silencioso: true });
        };
        document.addEventListener('visibilitychange', visible);
        return () => {
            window.clearInterval(intervalo);
            document.removeEventListener('visibilitychange', visible);
        };
    }, [actualizar]);

    // Espera a que se deje de teclear antes de pedir la lista.
    useEffect(() => {
        const texto = busqueda.trim();
        if (texto === buscado) return undefined;
        const reloj = window.setTimeout(() => setBuscado(texto), ESPERA_BUSQUEDA);
        return () => window.clearTimeout(reloj);
    }, [busqueda, buscado]);

    useEffect(() => {
        setPagina(1);
    }, [estado, buscado, seleccion]);

    const elegirAula = (aula) => {
        setError('');
        setBusqueda('');
        setBuscado('');
        setPedida(null);
        setPagina(1);
        setSeleccion({ examen_id: aula.examen_id, aula_id: aula.aula_id });
    };

    const atender = async (id) => {
        setAccion(String(id));
        setError('');
        try {
            await api.patch(`/seguimiento/incidencias/${id}/atendida`);
            avisar('Incidencia atendida');
            await actualizar();
        } catch (fallo) {
            avisar(mensajeDe(fallo, 'No se pudo atender'), 'error');
        } finally {
            setAccion('');
        }
    };

    if (cargando) {
        return (
            <div className="flex min-h-48 items-center justify-center gap-2 text-slate-600">
                <LoaderCircle className="animate-spin" size={20} aria-hidden="true" />
                Cargando…
            </div>
        );
    }

    // Se pidió un aula que no es de la cuenta o no es de hoy: se avisa en vez
    // de cambiar en silencio a otra.
    const pedidaNoDisponible =
        pedida !== null &&
        !aulas.some(
            (aula) => aula.examen_id === pedida.examen_id && aula.aula_id === pedida.aula_id
        );
    const operaPuerta = Boolean(detalle?.puede_operar) && puede('punto_control');
    const esHoy =
        detalle !== null &&
        String(detalle.examen.hora_servidor ?? '').slice(0, 10) === detalle.examen.fecha;

    return (
        <main className="mx-auto w-full max-w-6xl space-y-4 px-3 py-4 sm:px-6 sm:py-6">
            <header className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold tracking-tight text-slate-950">
                    {esDocente ? 'Lista del aula' : 'Mi aula'}
                </h1>
                {esDocente && (
                    <Link
                        to="/en-curso"
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <ArrowLeft size={16} aria-hidden="true" />
                        En curso
                    </Link>
                )}
            </header>

            {pedidaNoDisponible && aulas.length > 0 && (
                <p
                    role="status"
                    className="rounded-xl bg-amber-50 p-3 text-sm font-medium text-amber-900"
                >
                    Aula pedida no disponible
                </p>
            )}
            {desconectado && (
                <div
                    role="status"
                    className="flex items-center gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"
                >
                    <AlertTriangle size={18} aria-hidden="true" />
                    Sin conexión
                </div>
            )}
            {error && (
                <div
                    role="alert"
                    className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"
                >
                    {error}
                </div>
            )}

            {aulas.length === 0 ? (
                <section className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                    <Activity className="mx-auto text-slate-400" size={28} aria-hidden="true" />
                    <h2 className="mt-3 font-semibold text-slate-900">Sin aula asignada</h2>
                </section>
            ) : (
                <>
                    <nav aria-label="Aula" className="flex gap-2 overflow-x-auto pb-1">
                        {aulas.map((aula) => {
                            const activa =
                                aula.examen_id === seleccion?.examen_id &&
                                aula.aula_id === seleccion?.aula_id;
                            return (
                                <button
                                    key={`${aula.examen_id}-${aula.aula_id}`}
                                    type="button"
                                    aria-pressed={activa}
                                    onClick={() => elegirAula(aula)}
                                    className={`min-w-40 rounded-xl border px-4 py-3 text-left ${
                                        activa
                                            ? 'border-primary-600 bg-primary-50 text-primary-950'
                                            : 'border-slate-200 bg-white text-slate-800'
                                    }`}
                                >
                                    <span className="block font-semibold">{aula.aula}</span>
                                    <span className="mt-1 block truncate text-xs">
                                        {aula.asignatura}
                                    </span>
                                    <span className="mt-1 block text-xs text-slate-600">
                                        {fechaYHora(aula.fecha, aula.hora)}
                                    </span>
                                </button>
                            );
                        })}
                    </nav>

                    {seleccionada && detalle && (
                        <>
                            <section className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-xl font-bold text-slate-950">
                                            Aula {detalle.aula.nombre}
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-600">
                                            {tipoExamenLegible(detalle.examen.tipo)} ·{' '}
                                            {detalle.examen.asignatura} ·{' '}
                                            {fechaYHora(detalle.examen.fecha, detalle.examen.hora)}
                                            {' · Servidor '}
                                            {new Date(
                                                detalle.examen.hora_servidor
                                            ).toLocaleTimeString('es-BO', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                                second: '2-digit',
                                            })}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {esHoy && operaPuerta && (
                                            <IncidenciaAula
                                                examenId={seleccionada.examen_id}
                                                aula={{
                                                    id: detalle.aula.id,
                                                    nombre: detalle.aula.nombre,
                                                }}
                                                por={usuario?.nombre}
                                                hora={horaDe(detalle.examen.hora_servidor)}
                                                alRegistrar={() => {
                                                    avisar('Incidencia registrada');
                                                    void actualizar({ silencioso: true });
                                                }}
                                            />
                                        )}
                                        {detalle.abierto && operaPuerta && (
                                            <Link
                                                to={`/punto-control?examen=${seleccionada.examen_id}&aula=${detalle.aula.id}`}
                                                className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                            >
                                                <ScanLine size={16} aria-hidden="true" />
                                                Controlar
                                            </Link>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => void actualizar()}
                                            disabled={actualizando}
                                            className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:opacity-50"
                                        >
                                            <RefreshCw
                                                size={16}
                                                className={actualizando ? 'animate-spin' : ''}
                                                aria-hidden="true"
                                            />
                                            Actualizar
                                        </button>
                                    </div>
                                </div>
                                <Cifras cifras={detalle.cifras} />
                            </section>

                            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                                    <div
                                        className="flex gap-2"
                                        role="tablist"
                                        aria-label="Lista de estudiantes"
                                    >
                                        {[
                                            ['faltan', `Faltan ${detalle.cifras.faltan}`],
                                            [
                                                'ingresaron',
                                                `Ingresaron ${detalle.cifras.ingresaron}`,
                                            ],
                                        ].map(([valor, etiqueta]) => (
                                            <button
                                                key={valor}
                                                type="button"
                                                role="tab"
                                                aria-selected={estado === valor}
                                                onClick={() => setEstado(valor)}
                                                className={`rounded-full px-4 py-2 text-sm font-semibold ${
                                                    estado === valor
                                                        ? 'bg-slate-900 text-white'
                                                        : 'bg-slate-100 text-slate-700'
                                                }`}
                                            >
                                                {etiqueta}
                                            </button>
                                        ))}
                                    </div>
                                    <label className="relative w-full sm:max-w-xs">
                                        <Search
                                            className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                                            size={16}
                                            aria-hidden="true"
                                        />
                                        <input
                                            type="search"
                                            value={busqueda}
                                            onChange={(evento) => setBusqueda(evento.target.value)}
                                            placeholder="Nombre, código o documento"
                                            aria-label="Buscar por nombre, código o documento"
                                            className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm"
                                        />
                                    </label>
                                </div>

                                {detalle.estudiantes.length === 0 ? (
                                    <p className="p-8 text-center text-sm text-slate-600">
                                        {buscado ? 'Sin resultados' : 'Sin estudiantes'}
                                    </p>
                                ) : (
                                    <ul
                                        aria-label={
                                            estado === 'faltan'
                                                ? 'Estudiantes ausentes'
                                                : 'Estudiantes que ingresaron'
                                        }
                                    >
                                        {detalle.estudiantes.map((estudiante) => (
                                            <li
                                                key={`${estado}-${estudiante.estudiante_id}`}
                                                className="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium text-slate-900">
                                                        {nombreEstudiante(estudiante)}
                                                    </span>
                                                    <span className="block text-xs text-slate-600">
                                                        {estudiante.codigo}
                                                        {estudiante.grupos?.length
                                                            ? ` · ${estudiante.grupos.join(', ')}`
                                                            : ''}
                                                        {estudiante.registrado_en
                                                            ? ` · ${estudiante.registrado_en.slice(11, 16)}`
                                                            : ''}
                                                    </span>
                                                </span>
                                                <span
                                                    className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                        estudiante.estado === 'Expulsado'
                                                            ? 'bg-rose-100 text-rose-800'
                                                            : estudiante.estado === 'Ausente'
                                                              ? 'bg-slate-100 text-slate-700'
                                                              : 'bg-emerald-100 text-emerald-800'
                                                    }`}
                                                >
                                                    {estudiante.estado}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {detalle.pagina.ultima > 1 && (
                                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
                                        <span className="text-slate-600">
                                            Página {detalle.pagina.actual} de{' '}
                                            {detalle.pagina.ultima}
                                        </span>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                disabled={pagina <= 1}
                                                aria-label="Página anterior"
                                                onClick={() =>
                                                    setPagina((valor) => Math.max(1, valor - 1))
                                                }
                                                className="rounded-lg border border-slate-300 p-2 disabled:opacity-40"
                                            >
                                                <ChevronLeft size={18} aria-hidden="true" />
                                            </button>
                                            <button
                                                type="button"
                                                disabled={pagina >= detalle.pagina.ultima}
                                                aria-label="Página siguiente"
                                                onClick={() =>
                                                    setPagina((valor) =>
                                                        Math.min(detalle.pagina.ultima, valor + 1)
                                                    )
                                                }
                                                className="rounded-lg border border-slate-300 p-2 disabled:opacity-40"
                                            >
                                                <ChevronRight size={18} aria-hidden="true" />
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </section>

                            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                <header className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                    <h3 className="font-semibold text-slate-900">
                                        Incidencias · {detalle.cifras.incidencias}
                                    </h3>
                                </header>
                                {detalle.incidencias.length === 0 ? (
                                    <p className="p-5 text-sm text-slate-600">Sin incidencias</p>
                                ) : (
                                    <>
                                        {detalle.cifras.incidencias >
                                            detalle.incidencias.length && (
                                            <p className="px-4 pt-3 text-xs text-slate-500">
                                                Últimas 50
                                            </p>
                                        )}
                                        <ul className="divide-y divide-slate-100">
                                            {detalle.incidencias.map((incidencia) => (
                                                <li key={incidencia.id} className="space-y-2 p-4">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800">
                                                                {tipoIncidenciaLegible(
                                                                    incidencia.tipo
                                                                )}
                                                            </span>
                                                        </div>
                                                        {incidencia.atendida_en ? (
                                                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700">
                                                                <Check
                                                                    size={14}
                                                                    aria-hidden="true"
                                                                />
                                                                {[
                                                                    `Atendida por ${incidencia.atendida_por ?? '—'}`,
                                                                    horaDe(incidencia.atendida_en),
                                                                ]
                                                                    .filter(Boolean)
                                                                    .join(' · ')}
                                                            </span>
                                                        ) : detalle.puede_atender ? (
                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    accion === String(incidencia.id)
                                                                }
                                                                onClick={() =>
                                                                    void atender(incidencia.id)
                                                                }
                                                                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50"
                                                            >
                                                                {accion === String(incidencia.id)
                                                                    ? 'Guardando…'
                                                                    : 'Atender'}
                                                            </button>
                                                        ) : (
                                                            <span className="text-xs font-semibold text-amber-700">
                                                                Sin atender
                                                            </span>
                                                        )}
                                                    </div>
                                                    {incidencia.estudiante && (
                                                        <p className="text-sm font-semibold text-slate-900">
                                                            {incidencia.estudiante}
                                                        </p>
                                                    )}
                                                    <p className="text-sm text-slate-700">
                                                        {incidencia.detalle}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {[
                                                            `Reportó ${incidencia.reportante}`,
                                                            incidencia.rol_reportante,
                                                            horaDe(incidencia.reportada_en),
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}
                            </section>
                        </>
                    )}
                </>
            )}
            {aviso}
        </main>
    );
}
