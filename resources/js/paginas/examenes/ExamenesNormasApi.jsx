import React from 'react';
import SelectorEstudiante from '../../componentes/SelectorEstudiante';

function mensajeDeError(error, respaldo) {
    const data = error?.response?.data;
    const errors = data?.errors ? Object.values(data.errors).flat() : [];

    return errors[0] ?? data?.message ?? respaldo;
}

function adaptarExamen(examen) {
    const asignatura = examen.grupo?.asignatura;
    const docente = examen.grupo?.docente;

    return {
        ...examen,
        asignatura: asignatura?.nombre ?? 'Asignatura',
        grupoTexto: `${asignatura?.codigo ?? ''} · grupo ${examen.grupo?.codigo_grupo ?? ''}`,
        docenteTexto: `${docente?.apellidos ?? ''}, ${docente?.nombres ?? ''}`,
    };
}

function adaptarNorma(norma) {
    return {
        ...norma,
        estudianteTexto: [norma.estudiante_apellido, norma.estudiante_nombre]
            .filter(Boolean)
            .join(', '),
    };
}

export default function ExamenesNormasApi() {
    const [examenes, setExamenes] = React.useState([]);
    const [examenId, setExamenId] = React.useState(null);
    const [normas, setNormas] = React.useState([]);
    const [estudiantes, setEstudiantes] = React.useState([]);
    const [alcance, setAlcance] = React.useState('general');
    const [texto, setTexto] = React.useState('');
    const [estudianteId, setEstudianteId] = React.useState('');
    const [motivo, setMotivo] = React.useState('');
    const [errores, setErrores] = React.useState({});
    const [mensaje, setMensaje] = React.useState('');
    const [errorCarga, setErrorCarga] = React.useState('');
    const [cargando, setCargando] = React.useState(true);
    const [cargandoNormas, setCargandoNormas] = React.useState(false);
    const [guardando, setGuardando] = React.useState(false);
    const [quitandoId, setQuitandoId] = React.useState(null);
    const [ambientesDe, setAmbientesDe] = React.useState(null);
    const [ambientesAsignados, setAmbientesAsignados] = React.useState([]);
    const [ocupacion, setOcupacion] = React.useState(null);
    const [catalogoAmbientes, setCatalogoAmbientes] = React.useState([]);
    const [ambienteElegido, setAmbienteElegido] = React.useState('');
    const examen = examenes.find((item) => item.id === examenId) ?? null;

    const cargarNormas = React.useCallback(async (id) => {
        setCargandoNormas(true);
        try {
            const response = await window.axios.get(`/api/examenes/${id}/normas`);
            setNormas((response.data.data ?? []).map(adaptarNorma));
            setErrorCarga('');
        } catch (error) {
            setNormas([]);
            setErrorCarga(mensajeDeError(error, 'No se pudieron cargar las normas.'));
        } finally {
            setCargandoNormas(false);
        }
    }, []);

    React.useEffect(() => {
        let vigente = true;

        async function cargar() {
            setCargando(true);
            try {
                const [examenesResponse, estudiantesResponse] = await Promise.all([
                    window.axios.get('/api/examenes'),
                    window.axios.get('/api/examenes/estudiantes'),
                ]);
                if (!vigente) return;

                const lista = (examenesResponse.data.data ?? []).map(adaptarExamen);
                setExamenes(lista);
                setEstudiantes(estudiantesResponse.data.data ?? []);
                setExamenId(lista[0]?.id ?? null);
            } catch (error) {
                if (vigente)
                    setErrorCarga(mensajeDeError(error, 'No se pudieron cargar los datos.'));
            } finally {
                if (vigente) setCargando(false);
            }
        }

        cargar();
        return () => {
            vigente = false;
        };
    }, []);

    React.useEffect(() => {
        if (examenId !== null) {
            cargarNormas(examenId);
        } else {
            setNormas([]);
        }
    }, [examenId, cargarNormas]);

    async function agregar(event) {
        event.preventDefault();
        const validation = {};
        if (!texto.trim()) validation.texto = 'Escribe el texto de la norma.';
        if (alcance === 'particular' && !estudianteId)
            validation.estudiante = 'Selecciona el estudiante.';
        if (alcance === 'particular' && !motivo.trim()) validation.motivo = 'Escribe el motivo.';
        setErrores(validation);
        setMensaje('');
        if (Object.keys(validation).length || examenId === null) return;

        setGuardando(true);
        try {
            await window.axios.post(`/api/examenes/${examenId}/normas`, {
                alcance,
                texto: texto.trim(),
                ...(alcance === 'particular'
                    ? { estudiante_id: Number(estudianteId), motivo: motivo.trim() }
                    : {}),
            });
            setTexto('');
            setEstudianteId('');
            setMotivo('');
            setErrores({});
            setMensaje('Norma guardada.');
            await cargarNormas(examenId);
        } catch (error) {
            const serverErrors = error?.response?.data?.errors;
            if (serverErrors) {
                setErrores({
                    texto: serverErrors.texto?.[0],
                    estudiante: serverErrors.estudiante_id?.[0],
                    motivo: serverErrors.motivo?.[0],
                });
            }
            setMensaje(mensajeDeError(error, 'No se pudo guardar la norma.'));
        } finally {
            setGuardando(false);
        }
    }

    async function quitar(id) {
        setQuitandoId(id);
        setMensaje('');
        try {
            await window.axios.delete(`/api/examenes/${examenId}/normas/${id}`);
            setNormas((current) => current.filter((norma) => norma.id !== id));
            setMensaje('Norma eliminada.');
        } catch (error) {
            setMensaje(mensajeDeError(error, 'No se pudo eliminar la norma.'));
        } finally {
            setQuitandoId(null);
        }
    }

    // =========================
    // HU-10: Ambientes del examen
    // =========================

    async function abrirAmbientes(examen) {
        setExamenId(null);
        setAmbientesDe(examen.id);
        setAmbienteElegido('');
        setErrorCarga('');
        setMensaje('');

        try {
            const response = await window.axios.get(`/api/examenes/${examen.id}/ambientes`);
            setAmbientesAsignados(response.data.data ?? []);
            setOcupacion(response.data.ocupacion ?? null);
        } catch (error) {
            setAmbientesAsignados([]);
            setOcupacion(null);
            setErrorCarga(mensajeDeError(error, 'No se pudieron cargar los ambientes.'));
        }

        if (catalogoAmbientes.length === 0) {
            try {
                const response = await window.axios.get('/api/admin/ambientes');
                setCatalogoAmbientes(response.data.data ?? response.data ?? []);
            } catch {
                setCatalogoAmbientes([]);
            }
        }
    }

    function cerrarAmbientes() {
        setAmbientesDe(null);
        setAmbientesAsignados([]);
        setOcupacion(null);
        setAmbienteElegido('');
    }

    async function refrescarAmbientes() {
        const response = await window.axios.get(`/api/examenes/${ambientesDe}/ambientes`);
        setAmbientesAsignados(response.data.data ?? []);
        setOcupacion(response.data.ocupacion ?? null);
    }

    async function asignarAmbiente(event) {
        event.preventDefault();
        setErrorCarga('');
        setMensaje('');

        try {
            await window.axios.post(`/api/examenes/${ambientesDe}/ambientes`, {
                ambiente_id: Number(ambienteElegido),
            });

            await refrescarAmbientes();
            setAmbienteElegido('');
            setMensaje('Ambiente asignado.');
        } catch (error) {
            setErrorCarga(mensajeDeError(error, 'No se pudo asignar el ambiente.'));
        }
    }

    async function quitarAmbiente(asignado) {
        setErrorCarga('');
        setMensaje('');

        try {
            await window.axios.delete(
                `/api/examenes/${ambientesDe}/ambientes/${asignado.ambiente_id}`
            );
            await refrescarAmbientes();
            setMensaje('Ambiente quitado.');
        } catch (error) {
            setErrorCarga(mensajeDeError(error, 'No se pudo quitar el ambiente.'));
        }
    }

    return (
        <section className="min-w-0 p-4 sm:p-8">
            <header className="mb-6">
                <h1 className="text-2xl font-bold text-slate-900">Exámenes</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Evaluaciones registradas por grupo de asignatura
                </p>
            </header>

            {errorCarga && (
                <p
                    role="alert"
                    className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                >
                    {errorCarga}
                </p>
            )}

            <div className="space-y-3 md:hidden">
                {cargando ? (
                    <p className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
                        Cargando exámenes…
                    </p>
                ) : examenes.length === 0 ? (
                    <p className="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
                        No hay exámenes registrados.
                    </p>
                ) : (
                    examenes.map((item) => (
                        <article
                            key={item.id}
                            className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="font-semibold text-slate-900">
                                        {item.asignatura}
                                    </h2>
                                    <p className="mt-1 text-xs text-slate-500">{item.grupoTexto}</p>
                                </div>
                                <button
                                    type="button"
                                    aria-pressed={examenId === item.id}
                                    className="shrink-0 font-medium text-blue-600"
                                    onClick={() => {
                                        setExamenId(item.id);
                                        setMensaje('');
                                    }}
                                >
                                    Normas
                                </button>
                            </div>
                            <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                <div>
                                    <dt className="text-xs text-slate-500">Tipo</dt>
                                    <dd>{item.nombre}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-500">Fecha</dt>
                                    <dd>{String(item.fecha).slice(0, 10)}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-slate-500">Hora y duración</dt>
                                    <dd>
                                        {String(item.hora_inicio).slice(0, 5)} ·{' '}
                                        {item.duracion_minutos} min
                                    </dd>
                                </div>
                                <div className="min-w-0">
                                    <dt className="text-xs text-slate-500">Docente</dt>
                                    <dd className="break-words">{item.docenteTexto}</dd>
                                </div>
                            </dl>
                        </article>
                    ))
                )}
            </div>

            <div className="hidden overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm md:block">
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Asignatura</th>
                            <th className="px-4 py-3 font-semibold">Tipo</th>
                            <th className="px-4 py-3 font-semibold">Fecha</th>
                            <th className="px-4 py-3 font-semibold">Hora</th>
                            <th className="px-4 py-3 font-semibold">Docente</th>
                            <th className="px-4 py-3 font-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {cargando ? (
                            <tr>
                                <td colSpan="6" className="px-4 py-6 text-center text-slate-500">
                                    Cargando exámenes…
                                </td>
                            </tr>
                        ) : examenes.length === 0 ? (
                            <tr>
                                <td colSpan="6" className="px-4 py-6 text-center text-slate-500">
                                    No hay exámenes registrados.
                                </td>
                            </tr>
                        ) : (
                            examenes.map((item) => (
                                <tr key={item.id} className="text-slate-700">
                                    <td className="px-4 py-4">
                                        <p className="font-semibold text-slate-800">
                                            {item.asignatura}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {item.grupoTexto}
                                        </p>
                                    </td>
                                    <td className="px-4 py-4">{item.nombre}</td>
                                    <td className="px-4 py-4">{String(item.fecha).slice(0, 10)}</td>
                                    <td className="px-4 py-4">
                                        <p>{String(item.hora_inicio).slice(0, 5)}</p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {item.duracion_minutos} min
                                        </p>
                                    </td>
                                    <td className="px-4 py-4">{item.docenteTexto}</td>
                                    <td className="px-4 py-4">
                                        <div className="flex gap-3">
                                            <button
                                                type="button"
                                                aria-pressed={examenId === item.id}
                                                className="font-medium text-blue-600 hover:text-blue-800"
                                                onClick={() => {
                                                    setExamenId(item.id);
                                                    setMensaje('');
                                                }}
                                            >
                                                Normas
                                            </button>

                                            <button
                                                type="button"
                                                aria-pressed={ambientesDe === item.id}
                                                className="font-medium text-emerald-600 hover:text-emerald-800"
                                                onClick={() => abrirAmbientes(item)}
                                            >
                                                Ambientes
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {examen && (
                <>
                    <section
                        aria-labelledby="normas-titulo"
                        className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div className="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div>
                                <h2 id="normas-titulo" className="text-lg font-bold text-slate-900">
                                    Normas del examen
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {examen.asignatura} · {examen.nombre} · {examen.grupoTexto}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Las generales aplican a todo el examen; las particulares nombran
                                    a un estudiante y explican el motivo.
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center justify-between gap-4 sm:justify-start">
                                <span className="text-xs font-medium text-slate-500">
                                    {normas.length} {normas.length === 1 ? 'norma' : 'normas'}
                                </span>
                                <button
                                    type="button"
                                    className="text-sm font-medium text-slate-500 hover:text-slate-800"
                                    onClick={() => setExamenId(null)}
                                >
                                    Cerrar
                                </button>
                            </div>
                        </div>

                        {cargandoNormas ? (
                            <p className="py-6 text-sm text-slate-500">Cargando normas…</p>
                        ) : normas.length ? (
                            <ol className="divide-y divide-slate-100">
                                {normas.map((norma) => {
                                    const particular = norma.alcance === 'particular';
                                    return (
                                        <li
                                            key={norma.id}
                                            className="flex items-start justify-between gap-4 border-b border-slate-100 py-4 last:border-b-0"
                                        >
                                            <div className="min-w-0">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${particular ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'}`}
                                                >
                                                    {particular ? 'Particular' : 'General'}
                                                </span>
                                                <p className="mt-2 text-sm font-medium text-slate-800">
                                                    {norma.texto}
                                                </p>
                                                {particular && (
                                                    <>
                                                        <p className="mt-1 text-xs text-slate-600">
                                                            {norma.estudianteTexto} ·{' '}
                                                            {norma.estudiante_codigo}
                                                        </p>
                                                        <p className="mt-1 text-xs text-slate-500">
                                                            Motivo: {norma.motivo}
                                                        </p>
                                                    </>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                disabled={quitandoId === norma.id}
                                                className="shrink-0 text-xs font-semibold text-red-600 disabled:opacity-50"
                                                onClick={() => quitar(norma.id)}
                                            >
                                                {quitandoId === norma.id ? 'Quitando…' : 'Quitar'}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ol>
                        ) : (
                            <p className="py-6 text-sm text-slate-500">
                                Este examen todavía no tiene normas registradas.
                            </p>
                        )}

                        <form
                            className="mt-4 border-t border-slate-100 pt-4"
                            onSubmit={agregar}
                            noValidate
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <label className="block text-xs font-semibold text-slate-600">
                                    Alcance
                                    <select
                                        value={alcance}
                                        onChange={(event) => {
                                            setAlcance(event.target.value);
                                            setErrores({});
                                            setMensaje('');
                                        }}
                                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"
                                    >
                                        <option value="general">General</option>
                                        <option value="particular">Particular</option>
                                    </select>
                                </label>
                                <label className="block text-xs font-semibold text-slate-600">
                                    Norma
                                    <input
                                        value={texto}
                                        aria-invalid={Boolean(errores.texto)}
                                        onChange={(event) => setTexto(event.target.value)}
                                        className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"
                                    />
                                    {errores.texto && (
                                        <span className="mt-1 block text-red-600">
                                            {errores.texto}
                                        </span>
                                    )}
                                </label>
                                {alcance === 'particular' && (
                                    <>
                                        <div className="min-w-0 text-xs font-semibold text-slate-600">
                                            <label htmlFor="norma-estudiante">Estudiante</label>
                                            <SelectorEstudiante
                                                id="norma-estudiante"
                                                estudiantes={estudiantes}
                                                value={estudianteId}
                                                onChange={setEstudianteId}
                                                invalid={Boolean(errores.estudiante)}
                                            />
                                            {errores.estudiante && (
                                                <span className="mt-1 block text-red-600">
                                                    {errores.estudiante}
                                                </span>
                                            )}
                                        </div>
                                        <label className="block text-xs font-semibold text-slate-600 md:col-span-2">
                                            Motivo
                                            <textarea
                                                rows="2"
                                                value={motivo}
                                                aria-invalid={Boolean(errores.motivo)}
                                                onChange={(event) => setMotivo(event.target.value)}
                                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"
                                            />
                                            {errores.motivo && (
                                                <span className="mt-1 block text-red-600">
                                                    {errores.motivo}
                                                </span>
                                            )}
                                        </label>
                                    </>
                                )}
                            </div>
                            <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <p role="status" className="break-words text-xs text-emerald-700">
                                    {mensaje}
                                </p>
                                <button
                                    type="submit"
                                    disabled={guardando}
                                    className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 sm:w-auto"
                                >
                                    {guardando ? 'Guardando…' : 'Agregar norma'}
                                </button>
                            </div>
                        </form>
                    </section>
                </>
            )}

            {ambientesDe !== null && (
                <section
                    aria-labelledby="ambientes-titulo"
                    className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div>
                            <h2 id="ambientes-titulo" className="text-lg font-bold text-slate-900">
                                Ambientes del examen
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Un examen admite varios ambientes. No se ofrece uno en mantenimiento
                                ni uno con otro examen a la misma hora.
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center justify-between gap-4 sm:justify-start">
                            <span className="text-xs font-medium text-slate-500">
                                {ambientesAsignados.length}{' '}
                                {ambientesAsignados.length === 1 ? 'ambiente' : 'ambientes'}
                            </span>
                            <button
                                type="button"
                                className="text-sm font-medium text-slate-500 hover:text-slate-800"
                                onClick={cerrarAmbientes}
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>

                    {ocupacion && (
                        <p
                            className={`mt-4 inline-block rounded-lg px-3 py-2 text-sm ${
                                ocupacion.alcanza
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'
                            }`}
                        >
                            Capacidad asignada: {ocupacion.capacidad_asignada} · Habilitados:{' '}
                            {ocupacion.habilitados}
                            {!ocupacion.alcanza && ' · falta capacidad'}
                        </p>
                    )}

                    {ambientesAsignados.length === 0 ? (
                        <p className="py-6 text-sm text-slate-500">
                            Este examen todavía no tiene ambientes asignados.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {ambientesAsignados.map((asignado) => (
                                <li
                                    key={asignado.id}
                                    className="flex items-start justify-between gap-4 border-b border-slate-100 py-4 last:border-b-0"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-slate-800">
                                            {asignado.nombre}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {asignado.ubicacion ?? 'Sin edificio'} ·{' '}
                                            {asignado.capacidad} lugares
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="shrink-0 text-xs font-semibold text-red-600"
                                        onClick={() => quitarAmbiente(asignado)}
                                    >
                                        Quitar
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <form
                        className="mt-4 border-t border-slate-100 pt-4"
                        onSubmit={asignarAmbiente}
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <label className="flex-1 text-xs font-semibold text-slate-600">
                                Ambiente
                                <select
                                    required
                                    value={ambienteElegido}
                                    onChange={(event) => setAmbienteElegido(event.target.value)}
                                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"
                                >
                                    <option value="">Selecciona un ambiente</option>
                                    {catalogoAmbientes
                                        .filter((ambiente) => ambiente.estado === 'DISPONIBLE')
                                        .map((ambiente) => (
                                            <option key={ambiente.id} value={ambiente.id}>
                                                {ambiente.nombre} · {ambiente.capacidad} lugares
                                            </option>
                                        ))}
                                </select>
                            </label>

                            <button
                                type="submit"
                                className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white sm:w-auto"
                            >
                                Asignar ambiente
                            </button>
                        </div>
                    </form>
                </section>
            )}
        </section>
    );
}
