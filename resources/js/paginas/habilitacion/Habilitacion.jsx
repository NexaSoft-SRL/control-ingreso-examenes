import React from 'react';
import PropTypes from 'prop-types';
import { CheckCircle2, Download, XCircle } from 'lucide-react';
import LayoutAdmin from '../../componentes/LayoutAdmin.jsx';

function mensajeError(error, respaldo) {
    const errores = error?.response?.data?.errors;
    const primero = errores ? Object.values(errores)[0]?.[0] : null;

    return primero ?? error?.response?.data?.message ?? respaldo;
}

function Habilitacion({ onNavigate }) {
    const [examenes, setExamenes] = React.useState([]);
    const [examenSeleccionado, setExamenSeleccionado] = React.useState('');
    const [estudiantes, setEstudiantes] = React.useState([]);
    const [totales, setTotales] = React.useState({ total: 0, habilitados: 0, no_habilitados: 0 });
    const [seleccionados, setSeleccionados] = React.useState([]);
    const [motivo, setMotivo] = React.useState('');
    const [cargando, setCargando] = React.useState(true);
    const [guardando, setGuardando] = React.useState(false);
    const [aviso, setAviso] = React.useState(null);

    const cargarEstudiantes = React.useCallback(async (examenId) => {
        if (!examenId) {
            setEstudiantes([]);
            setTotales({ total: 0, habilitados: 0, no_habilitados: 0 });
            return;
        }

        setCargando(true);

        try {
            const respuesta = await window.axios.get(
                `/api/habilitacion/examenes/${examenId}/estudiantes`
            );

            setEstudiantes(respuesta.data.data);
            setTotales(respuesta.data.totales);
            setSeleccionados([]);
        } catch (error) {
            setEstudiantes([]);
            setTotales({ total: 0, habilitados: 0, no_habilitados: 0 });
            setAviso({
                tipo: 'error',
                texto: mensajeError(error, 'No se pudo cargar el padrón para este examen.'),
            });
        } finally {
            setCargando(false);
        }
    }, []);

    React.useEffect(() => {
        let vigente = true;

        async function cargarExamenes() {
            try {
                const respuesta = await window.axios.get('/api/habilitacion/examenes');
                const datos = respuesta.data.data;

                if (!vigente) return;

                setExamenes(datos);
                if (datos.length > 0) {
                    const primero = String(datos[0].id);
                    setExamenSeleccionado(primero);
                    cargarEstudiantes(primero);
                } else {
                    setCargando(false);
                }
            } catch (error) {
                if (vigente) {
                    setAviso({
                        tipo: 'error',
                        texto: mensajeError(error, 'No se pudieron cargar los exámenes.'),
                    });
                    setCargando(false);
                }
            }
        }

        cargarExamenes();

        return () => {
            vigente = false;
        };
    }, [cargarEstudiantes]);

    function cambiarSeleccion(estudianteId) {
        setSeleccionados((anteriores) =>
            anteriores.includes(estudianteId)
                ? anteriores.filter((id) => id !== estudianteId)
                : [...anteriores, estudianteId]
        );
    }

    function alternarTodos() {
        setSeleccionados((anteriores) =>
            anteriores.length === estudiantes.length ? [] : estudiantes.map(({ id }) => id)
        );
    }

    async function registrar(condicion) {
        if (seleccionados.length === 0) {
            setAviso({ tipo: 'error', texto: 'Selecciona al menos un estudiante.' });
            return;
        }

        if (condicion === 'NO_HABILITADO' && motivo.trim() === '') {
            setAviso({ tipo: 'error', texto: 'El motivo es obligatorio para inhabilitar.' });
            return;
        }

        setGuardando(true);
        setAviso(null);

        try {
            await window.axios.post(
                `/api/habilitacion/examenes/${examenSeleccionado}/condiciones`,
                {
                    estudiante_ids: seleccionados,
                    condicion,
                    motivo: condicion === 'NO_HABILITADO' ? motivo.trim() : null,
                }
            );

            await cargarEstudiantes(examenSeleccionado);
            setMotivo('');
            setAviso({
                tipo: 'exito',
                texto: 'Condición registrada para los estudiantes seleccionados.',
            });
        } catch (error) {
            setAviso({
                tipo: 'error',
                texto: mensajeError(error, 'No se pudo registrar la condición.'),
            });
        } finally {
            setGuardando(false);
        }
    }

    function etiquetaExamen(examen) {
        return `${examen.asignatura_codigo} - ${examen.nombre} - ${examen.fecha}`;
    }

    return (
        <LayoutAdmin seleccionado="habilitacion" onNavigate={onNavigate}>
            <section className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">
                            Habilitación
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Quiénes pueden rendir cada examen y por qué no los demás
                        </p>
                    </div>
                    {examenSeleccionado && (
                        <a
                            href={`/api/habilitacion/examenes/${examenSeleccionado}/exportar`}
                            className="inline-flex min-h-10 items-center justify-center gap-2 self-start rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            download
                        >
                            <Download className="h-4 w-4" aria-hidden="true" />
                            Exportar listado
                        </a>
                    )}
                </div>

                <div className="mb-5 max-w-xl">
                    <label
                        className="mb-1.5 block text-xs font-semibold text-slate-600"
                        htmlFor="examen"
                    >
                        Examen
                    </label>
                    <select
                        id="examen"
                        className="min-h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        value={examenSeleccionado}
                        onChange={(evento) => {
                            setExamenSeleccionado(evento.target.value);
                            setAviso(null);
                            cargarEstudiantes(evento.target.value);
                        }}
                        disabled={examenes.length === 0}
                    >
                        {examenes.length === 0 && (
                            <option value="">No hay exámenes registrados</option>
                        )}
                        {examenes.map((examen) => (
                            <option key={examen.id} value={examen.id}>
                                {etiquetaExamen(examen)}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mb-5 flex flex-wrap gap-2 text-sm">
                    <span className="rounded-md bg-slate-100 px-3 py-2 text-slate-700">
                        {totales.total} en el padrón
                    </span>
                    <span className="rounded-md bg-emerald-50 px-3 py-2 font-medium text-emerald-700">
                        {totales.habilitados} habilitados
                    </span>
                    <span className="rounded-md bg-amber-50 px-3 py-2 font-medium text-amber-800">
                        {totales.no_habilitados} sin habilitar
                    </span>
                </div>

                <div className="mb-4 rounded-lg border border-slate-200 bg-white p-4">
                    <label
                        className="mb-2 block text-xs font-semibold text-slate-600"
                        htmlFor="motivo"
                    >
                        Motivo de la inhabilitación
                    </label>
                    <input
                        id="motivo"
                        className="mb-3 min-h-10 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        placeholder="Obligatorio para inhabilitar"
                        value={motivo}
                        onChange={(evento) => setMotivo(evento.target.value)}
                    />
                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            className="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            onClick={() => registrar('HABILITADO')}
                            disabled={guardando || cargando || seleccionados.length === 0}
                        >
                            <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                            Habilitar seleccionados
                        </button>
                        <button
                            type="button"
                            className="inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-amber-600 px-4 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
                            onClick={() => registrar('NO_HABILITADO')}
                            disabled={guardando || cargando || seleccionados.length === 0}
                        >
                            <XCircle className="h-4 w-4" aria-hidden="true" />
                            Inhabilitar seleccionados
                        </button>
                    </div>
                </div>

                {aviso && (
                    <p
                        role="alert"
                        className={`mb-4 rounded-md px-3 py-2 text-sm ${
                            aviso.tipo === 'exito'
                                ? 'bg-emerald-50 text-emerald-800'
                                : 'bg-red-50 text-red-700'
                        }`}
                    >
                        {aviso.texto}
                    </p>
                )}

                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    {cargando ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-500">
                            Cargando padrón…
                        </p>
                    ) : estudiantes.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-500">
                            {examenes.length === 0
                                ? 'No hay exámenes disponibles.'
                                : 'No hay estudiantes activos en el padrón.'}
                        </p>
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[850px] border-collapse text-left">
                                    <thead className="bg-slate-50 text-[11px] font-semibold uppercase text-slate-500">
                                        <tr>
                                            <th className="w-12 px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    aria-label="Seleccionar todos"
                                                    checked={
                                                        seleccionados.length === estudiantes.length
                                                    }
                                                    onChange={alternarTodos}
                                                />
                                            </th>
                                            <th className="px-3 py-3">Código</th>
                                            <th className="px-3 py-3">Estudiante</th>
                                            <th className="px-3 py-3">Documento</th>
                                            <th className="px-3 py-3">Carrera</th>
                                            <th className="px-3 py-3">Condición</th>
                                            <th className="px-3 py-3">Motivo</th>
                                            <th className="px-3 py-3">Registró</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-sm">
                                        {estudiantes.map((estudiante) => (
                                            <tr key={estudiante.id}>
                                                <td className="px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`Seleccionar ${estudiante.nombre} ${estudiante.apellido}`}
                                                        checked={seleccionados.includes(
                                                            estudiante.id
                                                        )}
                                                        onChange={() =>
                                                            cambiarSeleccion(estudiante.id)
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-3 font-mono text-xs">
                                                    {estudiante.codigo_universitario ?? '—'}
                                                </td>
                                                <td className="px-3 py-3 font-medium text-slate-800">
                                                    {estudiante.apellido}, {estudiante.nombre}
                                                </td>
                                                <td className="px-3 py-3">{estudiante.ci}</td>
                                                <td className="px-3 py-3">
                                                    {estudiante.carrera ?? '—'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <EtiquetaCondicion
                                                        condicion={estudiante.condicion}
                                                    />
                                                </td>
                                                <td className="px-3 py-3">
                                                    {estudiante.motivo ?? '—'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {estudiante.registrado_por ?? '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="divide-y divide-slate-100 md:hidden">
                                {estudiantes.map((estudiante) => (
                                    <article key={estudiante.id} className="p-4">
                                        <div className="flex items-start gap-3">
                                            <input
                                                type="checkbox"
                                                aria-label={`Seleccionar ${estudiante.nombre} ${estudiante.apellido}`}
                                                checked={seleccionados.includes(estudiante.id)}
                                                onChange={() => cambiarSeleccion(estudiante.id)}
                                                className="mt-1"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <h2 className="font-semibold text-slate-900">
                                                        {estudiante.apellido}, {estudiante.nombre}
                                                    </h2>
                                                    <EtiquetaCondicion
                                                        condicion={estudiante.condicion}
                                                    />
                                                </div>
                                                <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                                                    <DatoEstudiante
                                                        etiqueta="Código"
                                                        valor={estudiante.codigo_universitario}
                                                    />
                                                    <DatoEstudiante
                                                        etiqueta="Documento"
                                                        valor={estudiante.ci}
                                                    />
                                                    <DatoEstudiante
                                                        etiqueta="Carrera"
                                                        valor={estudiante.carrera}
                                                    />
                                                    <DatoEstudiante
                                                        etiqueta="Registró"
                                                        valor={estudiante.registrado_por}
                                                    />
                                                    <div className="col-span-2">
                                                        <DatoEstudiante
                                                            etiqueta="Motivo"
                                                            valor={estudiante.motivo}
                                                        />
                                                    </div>
                                                </dl>
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </section>
        </LayoutAdmin>
    );
}

function EtiquetaCondicion({ condicion }) {
    const habilitado = condicion === 'HABILITADO';

    return (
        <span
            className={`inline-flex whitespace-nowrap items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${
                habilitado ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'
            }`}
        >
            {habilitado ? (
                <CheckCircle2 className="h-3.5 w-3.5" />
            ) : (
                <XCircle className="h-3.5 w-3.5" />
            )}
            {habilitado ? 'Habilitado' : 'No habilitado'}
        </span>
    );
}

EtiquetaCondicion.propTypes = {
    condicion: PropTypes.string.isRequired,
};

function DatoEstudiante({ etiqueta, valor }) {
    return (
        <div className="min-w-0">
            <dt className="font-semibold text-slate-500">{etiqueta}</dt>
            <dd className="mt-0.5 break-words text-slate-800">{valor || '—'}</dd>
        </div>
    );
}

DatoEstudiante.propTypes = {
    etiqueta: PropTypes.string.isRequired,
    valor: PropTypes.string,
};

DatoEstudiante.defaultProps = {
    valor: null,
};

Habilitacion.propTypes = {
    onNavigate: PropTypes.func,
};

Habilitacion.defaultProps = {
    onNavigate: undefined,
};

export default Habilitacion;
