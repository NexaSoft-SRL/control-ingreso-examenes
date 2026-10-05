import React from 'react';
import PropTypes from 'prop-types';
import { Building2, CalendarClock, ListChecks, Pencil, Plus, Trash2 } from 'lucide-react';
import LayoutAdmin from '../../componentes/LayoutAdmin.jsx';

const normaVacia = {
    alcance: 'general',
    texto: '',
    student_id: '',
    motivo: '',
};

const tiposExamen = [
    { valor: 'Primer parcial', etiqueta: 'Primer parcial' },
    { valor: 'Segundo parcial', etiqueta: 'Segundo parcial' },
    { valor: 'Examen final', etiqueta: 'Examen final' },
    { valor: 'Instancia', etiqueta: 'Instancia' },
];

const examenVacio = {
    grupo_id: '',
    fecha: '',
    hora_inicio: '',
    duracion_minutos: '90',
    nombre: 'Primer parcial',
};

function mensajeDeError(excepcion, respaldo) {
    const datos = excepcion?.response?.data;
    const errores = datos?.errors ? Object.values(datos.errors)[0] : null;

    return errores?.[0] ?? datos?.message ?? respaldo;
}

/**
 * Exámenes (HU-08). Cada examen cuelga de un grupo de asignatura, que es
 * quien tiene al docente responsable. Un grupo no admite dos exámenes a la
 * misma fecha y hora, y el examen se edita mientras no tenga ingresos.
 */
function Examenes({ onNavigate }) {
    const [examenes, setExamenes] = React.useState([]);
    const [grupos, setGrupos] = React.useState([]);
    const [cargando, setCargando] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [aviso, setAviso] = React.useState(null);
    const [formulario, setFormulario] = React.useState(null);
    const [campos, setCampos] = React.useState(examenVacio);
    const [normasDe, setNormasDe] = React.useState(null);
    const [normas, setNormas] = React.useState([]);
    const [estudiantes, setEstudiantes] = React.useState([]);
    const [norma, setNorma] = React.useState(normaVacia);
    const [ambientesDe, setAmbientesDe] = React.useState(null);
    const [ambientesAsignados, setAmbientesAsignados] = React.useState([]);
    const [ocupacion, setOcupacion] = React.useState(null);
    const [catalogoAmbientes, setCatalogoAmbientes] = React.useState([]);
    const [ambienteElegido, setAmbienteElegido] = React.useState('');

    const cargar = React.useCallback(async () => {
        setCargando(true);
        setError(null);

        try {
            const respExamenes = await window.axios.get('/api/examenes');

            setExamenes(respExamenes.data.data);

            // Las asignaturas traen sus grupos: de ahí sale el selector.
            try {
                const respAsignaturas = await window.axios.get('/api/asignaturas');

                setGrupos(
                    (respAsignaturas.data.data ?? []).flatMap((asignatura) =>
                        (asignatura.grupos ?? []).map((grupo) => ({
                            id: grupo.id,
                            etiqueta: `${asignatura.codigo} · grupo ${grupo.codigo_grupo}`,
                        }))
                    )
                );
            } catch {
                // Sin el permiso de asignaturas no hay selector, pero los
                // exámenes ya registrados se siguen viendo.
                setGrupos([]);
            }
        } catch (excepcion) {
            setError(
                excepcion.response?.status === 403
                    ? excepcion.response.data.message
                    : 'No se pudieron cargar los exámenes. Intenta de nuevo.'
            );
        } finally {
            setCargando(false);
        }
    }, []);

    React.useEffect(() => {
        cargar();
    }, [cargar]);

    // Las normas de un examen se piden al abrirlas, no con la lista: son
    // de un examen a la vez (HU-09).
    async function abrirNormas(examen) {
        setError(null);
        setAviso(null);
        setNorma(normaVacia);
        setAmbientesDe(null);
        setNormasDe(examen.id);

        try {
            const respuesta = await window.axios.get(`/api/examenes/${examen.id}/normas`);

            setNormas(respuesta.data.data);
        } catch (excepcion) {
            setNormas([]);
            setError(mensajeDeError(excepcion, 'No se pudieron cargar las normas.'));
        }

        if (estudiantes.length === 0) {
            try {
                const respuesta = await window.axios.get('/api/examenes/estudiantes');

                setEstudiantes(respuesta.data.data ?? respuesta.data ?? []);
            } catch {
                // Sin el permiso del padrón no hay selector de estudiante;
                // las normas generales se registran igual.
                setEstudiantes([]);
            }
        }
    }

    function cerrarNormas() {
        setNormasDe(null);
        setNormas([]);
        setNorma(normaVacia);
    }

    async function guardarNorma(evento) {
        evento.preventDefault();
        setError(null);
        setAviso(null);

        const esParticular = norma.alcance === 'particular';

        try {
            await window.axios.post(`/api/examenes/${normasDe}/normas`, {
                alcance: norma.alcance,
                texto: norma.texto.trim(),
                ...(esParticular
                    ? {
                          estudiante_id: Number(norma.student_id),
                          motivo: norma.motivo.trim(),
                      }
                    : {}),
            });

            const respuesta = await window.axios.get(`/api/examenes/${normasDe}/normas`);

            setNormas(respuesta.data.data);
            setNorma(normaVacia);
            setAviso('Norma registrada.');
        } catch (excepcion) {
            setError(mensajeDeError(excepcion, 'No se pudo guardar la norma.'));
        }
    }

    async function eliminarNorma(registro) {
        setError(null);
        setAviso(null);

        try {
            await window.axios.delete(`/api/examenes/${normasDe}/normas/${registro.id}`);

            setNormas((actuales) => actuales.filter((una) => una.id !== registro.id));
        } catch (excepcion) {
            setError(mensajeDeError(excepcion, 'No se pudo eliminar la norma.'));
        }
    }

    // Los ambientes de un examen (HU-10) se piden al abrirlos, junto con
    // la capacidad asignada frente al número de habilitados.
    async function abrirAmbientes(examen) {
        setError(null);
        setAviso(null);
        setNormasDe(null);
        setAmbientesDe(examen.id);
        setAmbienteElegido('');

        try {
            const respuesta = await window.axios.get(`/api/examenes/${examen.id}/ambientes`);

            setAmbientesAsignados(respuesta.data.data);
            setOcupacion(respuesta.data.ocupacion);
        } catch (excepcion) {
            setAmbientesAsignados([]);
            setOcupacion(null);
            setError(mensajeDeError(excepcion, 'No se pudieron cargar los ambientes.'));
        }

        if (catalogoAmbientes.length === 0) {
            try {
                const respuesta = await window.axios.get('/api/admin/ambientes');

                setCatalogoAmbientes(respuesta.data.data ?? respuesta.data ?? []);
            } catch {
                // Sin el permiso de ambientes no hay catálogo que ofrecer.
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
        const respuesta = await window.axios.get(`/api/examenes/${ambientesDe}/ambientes`);

        setAmbientesAsignados(respuesta.data.data);
        setOcupacion(respuesta.data.ocupacion);
    }

    async function asignarAmbiente(evento) {
        evento.preventDefault();
        setError(null);
        setAviso(null);

        try {
            await window.axios.post(`/api/examenes/${ambientesDe}/ambientes`, {
                ambiente_id: Number(ambienteElegido),
            });

            await refrescarAmbientes();
            setAmbienteElegido('');
            setAviso('Ambiente asignado.');
        } catch (excepcion) {
            // El backend responde 409 si está en mantenimiento o si ya
            // tiene otro examen a esa hora.
            setError(mensajeDeError(excepcion, 'No se pudo asignar el ambiente.'));
        }
    }

    async function quitarAmbiente(asignado) {
        setError(null);
        setAviso(null);

        try {
            await window.axios.delete(
                `/api/examenes/${ambientesDe}/ambientes/${asignado.ambiente_id}`
            );

            await refrescarAmbientes();
        } catch (excepcion) {
            setError(mensajeDeError(excepcion, 'No se pudo quitar el ambiente.'));
        }
    }

    function abrirNuevo() {
        setFormulario('nuevo');
        setCampos(examenVacio);
        setError(null);
        setAviso(null);
    }

    function abrirEdicion(examen) {
        setFormulario(examen.id);
        setCampos({
            grupo_id: String(examen.grupo?.id ?? ''),
            fecha: String(examen.fecha).slice(0, 10),
            hora_inicio: String(examen.hora_inicio).slice(0, 5),
            duracion_minutos: String(examen.duracion_minutos),
            nombre: examen.nombre,
        });
        setError(null);
        setAviso(null);
    }

    function cerrarFormulario() {
        setFormulario(null);
        setCampos(examenVacio);
    }

    async function guardar(evento) {
        evento.preventDefault();
        setError(null);
        setAviso(null);

        const cuerpo = {
            grupo_id: Number(campos.grupo_id),
            fecha: campos.fecha,
            hora_inicio: campos.hora_inicio,
            duracion_minutos: Number(campos.duracion_minutos),
            nombre: campos.nombre,
        };

        try {
            if (formulario === 'nuevo') {
                await window.axios.post('/api/examenes', cuerpo);
                setAviso('Examen registrado.');
            } else {
                await window.axios.put(`/api/examenes/${formulario}`, cuerpo);
                setAviso('Examen actualizado.');
            }

            cerrarFormulario();
            await cargar();
        } catch (excepcion) {
            setError(mensajeDeError(excepcion, 'No se pudo guardar el examen.'));
        }
    }

    async function eliminar(examen) {
        setError(null);
        setAviso(null);

        try {
            await window.axios.delete(`/api/examenes/${examen.id}`);
            await cargar();
            setAviso('Examen eliminado.');
        } catch (excepcion) {
            // El backend responde 409 si el examen ya tiene ingresos.
            setError(mensajeDeError(excepcion, 'No se pudo eliminar el examen.'));
        }
    }

    return (
        <LayoutAdmin seleccionado="examenes" onNavigate={onNavigate}>
            <section className="p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Exámenes</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Evaluaciones registradas por grupo de asignatura
                        </p>
                    </div>

                    <button
                        type="button"
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        onClick={abrirNuevo}
                    >
                        <Plus className="h-4 w-4" />
                        Nuevo examen
                    </button>
                </div>

                {error && (
                    <p
                        role="alert"
                        className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        {error}
                    </p>
                )}

                {aviso && !error && (
                    <p role="status" className="mt-4 text-sm text-emerald-700">
                        {aviso}
                    </p>
                )}

                {formulario !== null && (
                    <form
                        className="mt-4 rounded-xl border border-slate-200 bg-white p-4"
                        onSubmit={guardar}
                    >
                        <h2 className="text-base font-bold text-slate-800">
                            {formulario === 'nuevo' ? 'Nuevo examen' : 'Editar examen'}
                        </h2>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="flex flex-col gap-1.5">
                                <label
                                    className="text-xs font-semibold text-slate-700"
                                    htmlFor="examen-grupo"
                                >
                                    Asignatura y grupo
                                </label>

                                <select
                                    id="examen-grupo"
                                    required
                                    disabled={formulario !== 'nuevo'}
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                    value={campos.grupo_id}
                                    onChange={(evento) =>
                                        setCampos({
                                            ...campos,
                                            grupo_id: evento.target.value,
                                        })
                                    }
                                >
                                    <option value="">Selecciona un grupo</option>
                                    {grupos.map((grupo) => (
                                        <option key={grupo.id} value={grupo.id}>
                                            {grupo.etiqueta}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label
                                    className="text-xs font-semibold text-slate-700"
                                    htmlFor="examen-tipo"
                                >
                                    Tipo
                                </label>

                                <select
                                    id="examen-tipo"
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                    value={campos.nombre}
                                    onChange={(evento) =>
                                        setCampos({ ...campos, nombre: evento.target.value })
                                    }
                                >
                                    {tiposExamen.map((tipo) => (
                                        <option key={tipo.valor} value={tipo.valor}>
                                            {tipo.etiqueta}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label
                                    className="text-xs font-semibold text-slate-700"
                                    htmlFor="examen-fecha"
                                >
                                    Fecha
                                </label>

                                <input
                                    id="examen-fecha"
                                    type="date"
                                    required
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                    value={campos.fecha}
                                    onChange={(evento) =>
                                        setCampos({ ...campos, fecha: evento.target.value })
                                    }
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label
                                    className="text-xs font-semibold text-slate-700"
                                    htmlFor="examen-hora"
                                >
                                    Hora de inicio
                                </label>

                                <input
                                    id="examen-hora"
                                    type="time"
                                    required
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                    value={campos.hora_inicio}
                                    onChange={(evento) =>
                                        setCampos({ ...campos, hora_inicio: evento.target.value })
                                    }
                                />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label
                                    className="text-xs font-semibold text-slate-700"
                                    htmlFor="examen-duracion"
                                >
                                    Duración (minutos)
                                </label>

                                <input
                                    id="examen-duracion"
                                    type="number"
                                    min="15"
                                    max="480"
                                    required
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                    value={campos.duracion_minutos}
                                    onChange={(evento) =>
                                        setCampos({
                                            ...campos,
                                            duracion_minutos: evento.target.value,
                                        })
                                    }
                                />
                            </div>
                        </div>

                        <div className="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                                onClick={cerrarFormulario}
                            >
                                Cancelar
                            </button>

                            <button
                                type="submit"
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                                {formulario === 'nuevo' ? 'Registrar examen' : 'Guardar cambios'}
                            </button>
                        </div>
                    </form>
                )}

                {cargando && <p className="mt-6 text-sm text-slate-500">Cargando…</p>}

                {!cargando && !error && examenes.length === 0 && (
                    <p className="mt-6 text-sm text-slate-500">
                        Todavía no hay exámenes registrados.
                    </p>
                )}

                {!cargando && examenes.length > 0 && (
                    <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="grid min-w-[920px] grid-cols-[minmax(155px,1.25fr)_minmax(125px,1fr)_minmax(115px,0.9fr)_minmax(90px,0.7fr)_minmax(130px,1fr)_minmax(205px,1.5fr)] items-center gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                            <div>ASIGNATURA</div>
                            <div>TIPO</div>
                            <div>FECHA</div>
                            <div>HORA</div>
                            <div>DOCENTE</div>
                            <div>ACCIONES</div>
                        </div>

                        {examenes.map((examen) => (
                            <div
                                key={examen.id}
                                className="grid min-w-[920px] grid-cols-[minmax(155px,1.25fr)_minmax(125px,1fr)_minmax(115px,0.9fr)_minmax(90px,0.7fr)_minmax(130px,1fr)_minmax(205px,1.5fr)] items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0"
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold text-slate-800">
                                        {examen.grupo?.asignatura?.nombre ?? 'Sin asignatura'}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {examen.grupo?.asignatura?.codigo} · grupo{' '}
                                        {examen.grupo?.codigo_grupo}
                                    </p>
                                </div>

                                <div className="min-w-0 text-slate-600">{examen.nombre}</div>

                                <div className="flex items-center gap-1.5 whitespace-nowrap text-slate-600">
                                    <CalendarClock className="h-3.5 w-3.5 text-slate-400" />
                                    {String(examen.fecha).slice(0, 10)}
                                </div>

                                <div className="whitespace-nowrap text-slate-600">
                                    {String(examen.hora_inicio).slice(0, 5)}
                                    <span className="block text-xs text-slate-400">
                                        {examen.duracion_minutos} min
                                    </span>
                                </div>

                                <div className="min-w-0 break-words text-xs text-slate-500">
                                    {examen.grupo?.docente
                                        ? `${examen.grupo.docente.apellidos}, ${examen.grupo.docente.nombres}`
                                        : 'Sin docente'}
                                </div>

                                <div className="grid grid-cols-2 gap-1">
                                    <button
                                        type="button"
                                        aria-label={`Editar examen ${examen.id}`}
                                        className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium whitespace-nowrap text-blue-600 hover:bg-blue-50"
                                        onClick={() => abrirEdicion(examen)}
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                        Editar
                                    </button>

                                    <button
                                        type="button"
                                        aria-label={`Ambientes del examen ${examen.id}`}
                                        className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100"
                                        onClick={() => abrirAmbientes(examen)}
                                    >
                                        <Building2 className="h-3.5 w-3.5" />
                                        Ambientes
                                    </button>

                                    <button
                                        type="button"
                                        aria-label={`Normas del examen ${examen.id}`}
                                        className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100"
                                        onClick={() => abrirNormas(examen)}
                                    >
                                        <ListChecks className="h-3.5 w-3.5" />
                                        Normas
                                    </button>

                                    <button
                                        type="button"
                                        aria-label={`Eliminar examen ${examen.id}`}
                                        className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium whitespace-nowrap text-red-600 hover:bg-red-50"
                                        onClick={() => eliminar(examen)}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {ambientesDe !== null && (
                    <div className="mt-6 rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-start justify-between">
                            <div>
                                <h2 className="text-base font-bold text-slate-800">
                                    Ambientes del examen
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Un examen admite varios ambientes. No se ofrece uno en
                                    mantenimiento ni uno con otro examen a la misma hora.
                                </p>
                            </div>

                            <button
                                type="button"
                                className="text-sm text-slate-500 hover:text-slate-700"
                                onClick={cerrarAmbientes}
                            >
                                Cerrar
                            </button>
                        </div>

                        {ocupacion && (
                            <p
                                className={`mt-3 inline-block rounded-lg px-3 py-2 text-sm ${
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

                        {ambientesAsignados.length === 0 && (
                            <p className="mt-4 text-sm text-slate-500">
                                Este examen todavía no tiene ambientes asignados.
                            </p>
                        )}

                        {ambientesAsignados.length > 0 && (
                            <ul className="mt-4 divide-y divide-slate-100">
                                {ambientesAsignados.map((asignado) => (
                                    <li
                                        key={asignado.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div>
                                            <p className="text-sm font-semibold text-slate-800">
                                                {asignado.nombre}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {asignado.ubicacion ?? 'Sin edificio'} ·{' '}
                                                {asignado.capacidad} lugares
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            aria-label={`Quitar ambiente ${asignado.ambiente_id}`}
                                            className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium whitespace-nowrap text-red-600 hover:bg-red-50"
                                            onClick={() => quitarAmbiente(asignado)}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Quitar
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <form
                            className="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-end"
                            onSubmit={asignarAmbiente}
                        >
                            <div className="flex flex-1 flex-col gap-1.5">
                                <label
                                    className="text-xs font-semibold text-slate-700"
                                    htmlFor="examen-ambiente"
                                >
                                    Ambiente
                                </label>

                                <select
                                    id="examen-ambiente"
                                    required
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                    value={ambienteElegido}
                                    onChange={(evento) => setAmbienteElegido(evento.target.value)}
                                >
                                    <option value="">Selecciona un ambiente</option>
                                    {catalogoAmbientes
                                        .filter((ambiente) =>
                                            ambiente.estado === 'DISPONIBLE' &&
                                            !ambientesAsignados.some(
                                                (asig) => Number(asig.ambiente_id) === ambiente.id
                                            )
                                        )
                                        .map((ambiente) => (
                                            <option key={ambiente.id} value={ambiente.id}>
                                                {ambiente.nombre} · {ambiente.capacidad} lugares
                                            </option>
                                        ))}
                                </select>
                            </div>

                            <button
                                type="submit"
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                                Asignar ambiente
                            </button>
                        </form>
                    </div>
                )}

                {normasDe !== null && (
                    <div className="mt-6 rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-start justify-between">
                            <div>
                                <h2 className="text-base font-bold text-slate-800">
                                    Normas del examen
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Las generales rigen para todos; las particulares nombran a un
                                    estudiante y exigen su motivo.
                                </p>
                            </div>

                            <button
                                type="button"
                                className="text-sm text-slate-500 hover:text-slate-700"
                                onClick={cerrarNormas}
                            >
                                Cerrar
                            </button>
                        </div>

                        {normas.length === 0 && (
                            <p className="mt-4 text-sm text-slate-500">
                                Este examen todavía no tiene normas.
                            </p>
                        )}

                        {normas.length > 0 && (
                            <ul className="mt-4 divide-y divide-slate-100">
                                {normas.map((registro) => (
                                    <li
                                        key={registro.id}
                                        className="flex items-start justify-between gap-4 py-3"
                                    >
                                        <div>
                                            <span
                                                className={`inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                    registro.alcance === 'general'
                                                        ? 'bg-blue-100 text-blue-700'
                                                        : 'bg-amber-100 text-amber-700'
                                                }`}
                                            >
                                                {registro.alcance === 'general'
                                                    ? 'General'
                                                    : 'Particular'}
                                            </span>

                                            <p className="mt-1 text-sm text-slate-800">
                                                {registro.texto}
                                            </p>

                                            {registro.estudiante_id && (
                                                <p className="text-xs text-slate-500">
                                                    {registro.estudiante_apellido},{' '}
                                                    {registro.estudiante_nombre} ·{' '}
                                                    {registro.estudiante_codigo}
                                                </p>
                                            )}

                                            {registro.motivo && (
                                                <p className="text-xs text-slate-500">
                                                    Motivo: {registro.motivo}
                                                </p>
                                            )}
                                        </div>

                                        <button
                                            type="button"
                                            aria-label={`Eliminar norma ${registro.id}`}
                                            className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium whitespace-nowrap text-red-600 hover:bg-red-50"
                                            onClick={() => eliminarNorma(registro)}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Quitar
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <form
                            className="mt-4 border-t border-slate-100 pt-4"
                            onSubmit={guardarNorma}
                        >
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="flex flex-col gap-1.5">
                                    <label
                                        className="text-xs font-semibold text-slate-700"
                                        htmlFor="norma-alcance"
                                    >
                                        Alcance
                                    </label>

                                    <select
                                        id="norma-alcance"
                                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                        value={norma.alcance}
                                        onChange={(evento) =>
                                            setNorma({ ...norma, alcance: evento.target.value })
                                        }
                                    >
                                        <option value="general">General</option>
                                        <option value="particular">Particular</option>
                                    </select>
                                </div>

                                <div className="flex flex-col gap-1.5 lg:col-span-3">
                                    <label
                                        className="text-xs font-semibold text-slate-700"
                                        htmlFor="norma-texto"
                                    >
                                        Norma
                                    </label>

                                    <input
                                        id="norma-texto"
                                        type="text"
                                        required
                                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                        value={norma.texto}
                                        onChange={(evento) =>
                                            setNorma({ ...norma, texto: evento.target.value })
                                        }
                                    />
                                </div>

                                {norma.alcance === 'particular' && (
                                    <>
                                        <div className="flex flex-col gap-1.5">
                                            <label
                                                className="text-xs font-semibold text-slate-700"
                                                htmlFor="norma-estudiante"
                                            >
                                                Estudiante
                                            </label>

                                            <select
                                                id="norma-estudiante"
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                                value={norma.student_id}
                                                onChange={(evento) =>
                                                    setNorma({
                                                        ...norma,
                                                        student_id: evento.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">Selecciona un estudiante</option>
                                                {estudiantes.map((estudiante) => (
                                                    <option
                                                        key={estudiante.id}
                                                        value={estudiante.id}
                                                    >
                                                        {estudiante.nombre} ·{' '}
                                                        {estudiante.codigo_universitario}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="flex flex-col gap-1.5 lg:col-span-3">
                                            <label
                                                className="text-xs font-semibold text-slate-700"
                                                htmlFor="norma-motivo"
                                            >
                                                Motivo
                                            </label>

                                            <input
                                                id="norma-motivo"
                                                type="text"
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                                                value={norma.motivo}
                                                onChange={(evento) =>
                                                    setNorma({
                                                        ...norma,
                                                        motivo: evento.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    </>
                                )}
                            </div>

                            <div className="mt-3 flex justify-end">
                                <button
                                    type="submit"
                                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                >
                                    Agregar norma
                                </button>
                            </div>
                        </form>
                    </div>
                )}
            </section>
        </LayoutAdmin>
    );
}

Examenes.propTypes = {
    onNavigate: PropTypes.func.isRequired,
};

export default Examenes;
