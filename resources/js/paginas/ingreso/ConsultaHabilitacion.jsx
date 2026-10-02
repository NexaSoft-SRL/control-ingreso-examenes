import React from 'react';
import PropTypes from 'prop-types';
import { CheckCircle2, DoorOpen, Search, ShieldAlert, Timer, XCircle } from 'lucide-react';
import LayoutAdmin from '../../componentes/LayoutAdmin.jsx';

// El backlog pide la respuesta en menos de tres segundos (HU-13).
const LIMITE_SEGUNDOS = 3;

const formatoFecha = new Intl.DateTimeFormat('es-BO', {
    timeZone: 'America/La_Paz',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

function formatearFecha(fecha) {
    if (!fecha) {
        return null;
    }

    const instante = new Date(fecha);

    return Number.isNaN(instante.getTime()) ? null : formatoFecha.format(instante);
}

function fechaDeHoy() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/La_Paz' }).format(new Date());
}

function mensajeError(error, respaldo) {
    const errores = error?.response?.data?.errors;
    const primero = errores ? Object.values(errores)[0]?.[0] : null;

    return primero ?? error?.response?.data?.message ?? respaldo;
}

/**
 * Consulta rápida de habilitación (HU-13). Es la pantalla de la puerta: el
 * personal de control teclea el código universitario o el documento y ve la
 * condición, el ambiente y, si el estudiante no está habilitado, el motivo.
 */
function ConsultaHabilitacion({ onNavigate }) {
    const [examenes, setExamenes] = React.useState([]);
    const [examenId, setExamenId] = React.useState('');
    const [identificador, setIdentificador] = React.useState('');
    const [resultados, setResultados] = React.useState(null);
    const [segundos, setSegundos] = React.useState(null);
    const [error, setError] = React.useState(null);
    const [cargando, setCargando] = React.useState(true);
    const [consultando, setConsultando] = React.useState(false);
    const campo = React.useRef(null);

    React.useEffect(() => {
        let vigente = true;

        async function cargarExamenes() {
            try {
                const respuesta = await window.axios.get('/api/consulta-habilitacion/examenes');
                const datos = respuesta.data.data;

                if (!vigente) return;

                setExamenes(datos);

                // En la puerta casi siempre se consulta el examen del día.
                const deHoy = datos.find((examen) => examen.fecha === fechaDeHoy());
                const inicial = deHoy ?? datos[0];

                if (inicial) {
                    setExamenId(String(inicial.id));
                }
            } catch (excepcion) {
                if (vigente) {
                    setError(mensajeError(excepcion, 'No se pudieron cargar los exámenes.'));
                }
            } finally {
                if (vigente) {
                    setCargando(false);
                }
            }
        }

        cargarExamenes();

        return () => {
            vigente = false;
        };
    }, []);

    async function consultar(evento) {
        evento.preventDefault();

        const valor = identificador.trim();

        if (valor === '') {
            setResultados(null);
            setSegundos(null);
            setError('Escribe el código universitario o el documento de identidad.');
            campo.current?.focus();
            return;
        }

        setConsultando(true);
        setError(null);
        setResultados(null);

        const inicio = performance.now();

        try {
            const respuesta = await window.axios.get(
                `/api/consulta-habilitacion/examenes/${examenId}`,
                { params: { identificador: valor } }
            );

            setResultados(respuesta.data.data);
        } catch (excepcion) {
            setError(mensajeError(excepcion, 'No se pudo consultar. Intenta de nuevo.'));
        } finally {
            setSegundos((performance.now() - inicio) / 1000);
            setConsultando(false);
            // Queda listo para el siguiente de la fila: lo que se teclee
            // reemplaza la consulta anterior.
            campo.current?.focus();
            campo.current?.select();
        }
    }

    return (
        <LayoutAdmin seleccionado="consulta" onNavigate={onNavigate}>
            <section className="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">
                    Consulta de habilitación
                </h1>
                <p className="mt-1 text-sm text-slate-500">
                    Código universitario o documento de identidad, para responder en la puerta
                </p>

                {cargando ? (
                    <p className="mt-6 text-sm text-slate-500">Cargando exámenes…</p>
                ) : (
                    <form
                        className="mt-5 rounded-lg border border-slate-200 bg-white p-4"
                        onSubmit={consultar}
                        noValidate
                    >
                        <label
                            className="mb-1.5 block text-xs font-semibold text-slate-600"
                            htmlFor="examen-consulta"
                        >
                            Examen
                        </label>
                        <select
                            id="examen-consulta"
                            className="mb-4 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            value={examenId}
                            onChange={(evento) => {
                                setExamenId(evento.target.value);
                                setResultados(null);
                                setError(null);
                            }}
                            disabled={examenes.length === 0}
                        >
                            {examenes.length === 0 && (
                                <option value="">No hay exámenes registrados</option>
                            )}
                            {examenes.map((examen) => (
                                <option key={examen.id} value={examen.id}>
                                    {examen.asignatura_codigo} - {examen.nombre} - {examen.fecha}
                                </option>
                            ))}
                        </select>

                        <label
                            className="mb-1.5 block text-xs font-semibold text-slate-600"
                            htmlFor="identificador"
                        >
                            Código universitario o documento{' '}
                            <span className="text-red-600" aria-hidden="true">
                                *
                            </span>
                        </label>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <input
                                id="identificador"
                                ref={campo}
                                type="text"
                                autoComplete="off"
                                autoFocus
                                required
                                aria-required="true"
                                maxLength={30}
                                className="min-h-12 w-full rounded-md border border-slate-300 px-3 text-lg tracking-wide text-slate-900 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                placeholder="Ej. 202104821"
                                value={identificador}
                                onChange={(evento) => setIdentificador(evento.target.value)}
                                disabled={examenes.length === 0}
                            />
                            <button
                                type="submit"
                                className="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-md bg-blue-600 px-5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                disabled={consultando || examenes.length === 0}
                            >
                                <Search className="h-4 w-4" aria-hidden="true" />
                                {consultando ? 'Consultando…' : 'Consultar'}
                            </button>
                        </div>
                    </form>
                )}

                {error && (
                    <p
                        role="alert"
                        className="mt-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        {error}
                    </p>
                )}

                {resultados && resultados.length > 1 && (
                    <p className="mt-4 text-sm text-slate-600">
                        Ese valor es el código de un estudiante y el documento de otro. Confirma con
                        el nombre.
                    </p>
                )}

                {resultados?.map((estudiante) => (
                    <TarjetaResultado key={estudiante.id} estudiante={estudiante} />
                ))}

                {segundos !== null && !consultando && (
                    <p
                        className={`mt-3 flex items-center gap-1.5 text-xs ${
                            segundos < LIMITE_SEGUNDOS ? 'text-slate-500' : 'text-red-600'
                        }`}
                    >
                        <Timer className="h-3.5 w-3.5" aria-hidden="true" />
                        Respondió en {segundos.toFixed(2).replace('.', ',')} s
                    </p>
                )}
            </section>
        </LayoutAdmin>
    );
}

function TarjetaResultado({ estudiante }) {
    const habilitado = estudiante.habilitado;
    const fecha = formatearFecha(estudiante.fecha_registro);
    const sinCondicion = !habilitado && !estudiante.registrado_por && !fecha;

    return (
        <article
            aria-label={`Resultado de ${estudiante.nombre} ${estudiante.apellido}`}
            className={`mt-4 rounded-lg border-2 p-5 ${
                habilitado ? 'border-emerald-300 bg-emerald-50' : 'border-amber-300 bg-amber-50'
            }`}
        >
            <p
                className={`flex items-center gap-2 text-2xl font-bold ${
                    habilitado ? 'text-emerald-800' : 'text-amber-900'
                }`}
            >
                {habilitado ? (
                    <CheckCircle2 className="h-7 w-7 shrink-0" aria-hidden="true" />
                ) : (
                    <XCircle className="h-7 w-7 shrink-0" aria-hidden="true" />
                )}
                {habilitado ? 'Habilitado' : 'No habilitado'}
            </p>

            <h2 className="mt-3 text-lg font-semibold text-slate-900">
                {estudiante.apellido}, {estudiante.nombre}
            </h2>
            <p className="text-sm text-slate-600">
                Código {estudiante.codigo_universitario ?? '—'} · Documento {estudiante.ci}
                {estudiante.carrera ? ` · ${estudiante.carrera}` : ''}
            </p>

            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="flex items-center gap-1.5 font-semibold text-slate-600">
                        <DoorOpen className="h-4 w-4" aria-hidden="true" />
                        Ambiente asignado
                    </dt>
                    <dd className="mt-0.5 text-slate-900">
                        {estudiante.ambiente ?? 'Sin ambiente asignado todavía'}
                    </dd>
                </div>

                {!habilitado && (
                    <div>
                        <dt className="font-semibold text-slate-600">
                            Motivo de la inhabilitación
                        </dt>
                        <dd className="mt-0.5 text-slate-900">
                            {estudiante.motivo ??
                                (sinCondicion
                                    ? 'El docente todavía no registró su habilitación para este examen.'
                                    : 'Sin motivo registrado.')}
                        </dd>
                    </div>
                )}
            </dl>

            {(estudiante.registrado_por || fecha) && (
                <p className="mt-4 text-xs text-slate-500">
                    Registrado por {estudiante.registrado_por ?? 'cuenta eliminada'}
                    {fecha ? ` el ${fecha}` : ''}
                </p>
            )}
        </article>
    );
}

TarjetaResultado.propTypes = {
    estudiante: PropTypes.shape({
        id: PropTypes.number.isRequired,
        codigo_universitario: PropTypes.string,
        ci: PropTypes.string.isRequired,
        nombre: PropTypes.string.isRequired,
        apellido: PropTypes.string.isRequired,
        carrera: PropTypes.string,
        habilitado: PropTypes.bool.isRequired,
        motivo: PropTypes.string,
        ambiente: PropTypes.string,
        registrado_por: PropTypes.string,
        fecha_registro: PropTypes.string,
    }).isRequired,
};

ConsultaHabilitacion.propTypes = {
    onNavigate: PropTypes.func,
};

ConsultaHabilitacion.defaultProps = {
    onNavigate: undefined,
};

export default ConsultaHabilitacion;
