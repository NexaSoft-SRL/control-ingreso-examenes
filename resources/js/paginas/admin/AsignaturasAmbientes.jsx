import React from 'react';
import PropTypes from 'prop-types';
import {
    CheckCircle2,
    DatabaseBackup,
    History,
    LayoutGrid,
    LogOut,
    Menu,
    MonitorCheck,
    QrCode,
    ShieldCheck,
    User,
    UserCog,
    Users,
    Wrench,
} from 'lucide-react';

// El backend acepta estos tres estados (StoreAmbienteRequest).
const ESTADOS_AMBIENTE = [
    { valor: 'DISPONIBLE', etiqueta: 'Disponible' },
    { valor: 'MANTENIMIENTO', etiqueta: 'Mantenimiento' },
    { valor: 'OCUPADO', etiqueta: 'Ocupado' },
];

const AMBIENTE_VACIO = {
    nombre: '',
    ubicacion: '',
    capacidad: '',
    estado: 'DISPONIBLE',
};

const ASIGNATURA_VACIA = {
    codigo: '',
    materia: '',
    docente_id: '',
    cupo: '40',
};

function mensajeDeError(error, respaldo) {
    const datos = error?.response?.data;
    const errores = datos?.errors ? Object.values(datos.errors)[0] : null;

    return errores?.[0] ?? datos?.message ?? respaldo;
}

function AsignaturasAmbientes({ onNavigate }) {
    const [menuAbierto, setMenuAbierto] = React.useState(false);

    const [asignaturas, setAsignaturas] = React.useState([]);
    const [ambientes, setAmbientes] = React.useState([]);
    const [docentes, setDocentes] = React.useState([]);
    const [aviso, setAviso] = React.useState(null);

    React.useEffect(() => {
        cargarAsignaturas();
        cargarAmbientes();
        cargarDocentes();
    }, []);

    function avisar(texto, tipo = 'error') {
        setAviso({ texto, tipo });
    }

    async function cargarAsignaturas() {
        try {
            const respuesta = await window.axios.get('/api/asignaturas');

            setAsignaturas(respuesta.data.data ?? []);
        } catch (error) {
            console.error('Error cargando asignaturas:', error);
            avisar('No se pudieron cargar las asignaturas.');
        }
    }

    // Los ambientes viven en la base (HU-06): la pantalla los leia de una
    // lista escrita en el codigo y lo registrado se perdia al recargar.
    async function cargarAmbientes() {
        try {
            const respuesta = await window.axios.get('/api/admin/ambientes');

            setAmbientes(respuesta.data ?? []);
        } catch (error) {
            console.error('Error cargando ambientes:', error);
            avisar('No se pudieron cargar los ambientes.');
        }
    }

    async function cargarDocentes() {
        try {
            const respuesta = await window.axios.get('/api/docentes');

            setDocentes(respuesta.data.data ?? []);
        } catch (error) {
            console.error('Error cargando docentes:', error);
        }
    }

    /* =========================
       ESTADO ASIGNATURAS
    ========================== */

    const [mostrarFormularioAsignatura, setMostrarFormularioAsignatura] = React.useState(false);

    const [nuevaAsignatura, setNuevaAsignatura] = React.useState(ASIGNATURA_VACIA);

    /* =========================
       ESTADO AMBIENTES
    ========================== */

    const [mostrarFormularioAmbiente, setMostrarFormularioAmbiente] = React.useState(false);

    const [ambienteEditando, setAmbienteEditando] = React.useState(null);

    const [nuevoAmbiente, setNuevoAmbiente] = React.useState(AMBIENTE_VACIO);

    /* =========================
       FUNCIONES ASIGNATURAS
    ========================== */

    const abrirNuevaAsignatura = () => {
        setNuevaAsignatura(ASIGNATURA_VACIA);
        setAviso(null);
        setMostrarFormularioAsignatura(true);
    };

    const cancelarAsignatura = () => {
        setMostrarFormularioAsignatura(false);
        setNuevaAsignatura(ASIGNATURA_VACIA);
    };

    const guardarAsignatura = async () => {
        if (
            nuevaAsignatura.codigo.trim() === '' ||
            nuevaAsignatura.materia.trim() === '' ||
            nuevaAsignatura.docente_id === ''
        ) {
            avisar('La sigla, el nombre y el docente responsable son obligatorios.');

            return;
        }

        try {
            await window.axios.post('/api/asignaturas', {
                codigo: nuevaAsignatura.codigo.trim(),
                nombre: nuevaAsignatura.materia.trim(),
                semestre: null,
                descripcion: null,
                grupos: [
                    {
                        codigo_grupo: 'A',
                        docente_id: Number(nuevaAsignatura.docente_id),
                        cupo: Number(nuevaAsignatura.cupo) || 0,
                    },
                ],
            });

            await cargarAsignaturas();

            avisar('Asignatura registrada.', 'exito');

            cancelarAsignatura();
        } catch (error) {
            console.error(error);
            avisar(mensajeDeError(error, 'No se pudo guardar la asignatura.'));
        }
    };

    const eliminarAsignatura = async (asignatura) => {
        setAviso(null);

        try {
            await window.axios.delete(`/api/asignaturas/${asignatura.id}`);

            await cargarAsignaturas();

            avisar('Asignatura eliminada.', 'exito');
        } catch (error) {
            // El backend responde 409 cuando la asignatura tiene examenes.
            avisar(mensajeDeError(error, 'No se pudo eliminar la asignatura.'));
        }
    };

    /* =========================
       FUNCIONES AMBIENTES
    ========================== */

    const abrirNuevoAmbiente = () => {
        setAmbienteEditando(null);
        setNuevoAmbiente(AMBIENTE_VACIO);
        setAviso(null);
        setMostrarFormularioAmbiente(true);
    };

    const abrirEditarAmbiente = (ambiente) => {
        setAmbienteEditando(ambiente.id);

        setNuevoAmbiente({
            nombre: ambiente.nombre,
            ubicacion: ambiente.ubicacion ?? '',
            capacidad: String(ambiente.capacidad ?? ''),
            estado: ambiente.estado ?? 'DISPONIBLE',
        });

        setAviso(null);
        setMostrarFormularioAmbiente(true);
    };

    const cancelarAmbiente = () => {
        setMostrarFormularioAmbiente(false);
        setAmbienteEditando(null);
        setNuevoAmbiente(AMBIENTE_VACIO);
    };

    const guardarAmbiente = async () => {
        if (nuevoAmbiente.nombre.trim() === '' || nuevoAmbiente.capacidad === '') {
            avisar('El nombre y la capacidad del ambiente son obligatorios.');

            return;
        }

        const datos = {
            nombre: nuevoAmbiente.nombre.trim(),
            ubicacion: nuevoAmbiente.ubicacion.trim() || null,
            capacidad: Number(nuevoAmbiente.capacidad),
            estado: nuevoAmbiente.estado,
        };

        try {
            if (ambienteEditando === null) {
                await window.axios.post('/api/admin/ambientes', datos);
            } else {
                await window.axios.put(`/api/admin/ambientes/${ambienteEditando}`, datos);
            }

            await cargarAmbientes();

            avisar(
                ambienteEditando === null ? 'Ambiente registrado.' : 'Ambiente actualizado.',
                'exito'
            );

            cancelarAmbiente();
        } catch (error) {
            console.error(error);
            avisar(mensajeDeError(error, 'No se pudo guardar el ambiente.'));
        }
    };

    const eliminarAmbiente = async (ambiente) => {
        setAviso(null);

        try {
            await window.axios.delete(`/api/admin/ambientes/${ambiente.id}`);

            await cargarAmbientes();

            avisar('Ambiente eliminado.', 'exito');
        } catch (error) {
            avisar(mensajeDeError(error, 'No se pudo eliminar el ambiente.'));
        }
    };

    function navegar(clave) {
        setMenuAbierto(false);
        onNavigate(clave);
    }

    return (
        <div className="flex min-h-screen w-full bg-white font-sans text-slate-800">
            {menuAbierto && (
                <div
                    className="fixed inset-0 z-30 bg-slate-900/40 md:hidden"
                    onClick={() => setMenuAbierto(false)}
                />
            )}

            {/* BARRA LATERAL */}
            <aside
                className={`fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-200 md:static md:translate-x-0 ${
                    menuAbierto ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center gap-3 border-b border-slate-100 px-5">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white">
                        <ShieldCheck className="h-5 w-5" strokeWidth={2} />
                    </div>

                    <div>
                        <div className="text-sm leading-tight font-bold text-slate-800">
                            UMSS FCyT
                        </div>
                        <div className="mt-0.5 text-[11px] tracking-wide text-slate-500">
                            CONTROL DE INGRESO
                        </div>
                    </div>
                </div>

                <div className="px-3 py-4">
                    <div className="px-3 pb-2 text-[11px] font-semibold tracking-wide text-slate-400">
                        ADMINISTRADOR
                    </div>

                    <MenuItem
                        icon={<Users className="h-[18px] w-[18px]" />}
                        text="Padrón"
                        onClick={() => navegar('padron')}
                    />

                    <MenuItem
                        icon={<LayoutGrid className="h-[18px] w-[18px]" />}
                        text="Asignaturas y ambientes"
                        selected
                        onClick={() => navegar('asignaturas')}
                    />

                    <MenuItem
                        icon={<QrCode className="h-[18px] w-[18px]" />}
                        text="Códigos QR"
                        onClick={() => alert('Códigos QR: próximamente')}
                    />

                    <MenuItem
                        icon={<UserCog className="h-[18px] w-[18px]" />}
                        text="Usuarios y roles"
                        onClick={() => navegar('usuarios')}
                    />

                    <MenuItem
                        icon={<History className="h-[18px] w-[18px]" />}
                        text="Bitácora"
                        onClick={() => navegar('bitacora')}
                    />

                    <MenuItem
                        icon={<DatabaseBackup className="h-[18px] w-[18px]" />}
                        text="Respaldo"
                        onClick={() => alert('Respaldo: próximamente')}
                    />
                </div>
            </aside>

            {/* CONTENIDO PRINCIPAL */}
            <main className="min-w-0 flex-1 bg-slate-50">
                <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 md:hidden"
                            onClick={() => setMenuAbierto(true)}
                            aria-label="Abrir menú"
                        >
                            <Menu className="h-5 w-5" strokeWidth={1.75} />
                        </button>

                        <div className="flex items-center gap-2 text-sm font-medium text-slate-800">
                            <MonitorCheck className="h-[18px] w-[18px] text-blue-600" />
                            <span className="hidden sm:inline">
                                Sistema Institucional de Verificación
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
                            onClick={() => navegar('salir')}
                            aria-label="Cerrar sesión"
                        >
                            <LogOut className="h-[18px] w-[18px]" strokeWidth={1.75} />
                            <span className="hidden sm:inline">Cerrar sesión</span>
                        </button>

                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white">
                            <User className="h-[18px] w-[18px]" strokeWidth={1.75} />
                        </div>
                    </div>
                </header>

                <section className="p-4 md:p-6 lg:p-8">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">
                                Asignaturas y ambientes
                            </h1>

                            <p className="mt-1 text-sm text-slate-500">
                                Configuración de materias y aulas disponibles
                            </p>
                        </div>
                    </div>

                    {aviso && (
                        <div
                            role="alert"
                            className={`mt-5 rounded-lg px-4 py-3 text-sm ${
                                aviso.tipo === 'exito'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-rose-50 text-rose-700'
                            }`}
                        >
                            {aviso.texto}
                        </div>
                    )}

                    {/* ASIGNATURAS */}
                    <div className="mt-7 mb-2 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="h-3 w-1 rounded bg-blue-600" />
                            <h2 className="text-base font-bold text-slate-800">Asignaturas</h2>
                            <span className="rounded bg-blue-50 px-2 py-0.5 text-xs text-slate-500">
                                {asignaturas.length} registros
                            </span>
                        </div>

                        <button
                            type="button"
                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            onClick={abrirNuevaAsignatura}
                        >
                            + Nueva
                        </button>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="grid min-w-[620px] grid-cols-[0.6fr_1.2fr_1.4fr_0.5fr] items-center gap-3 border-b border-slate-100 bg-blue-50/60 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                            <div>SIGLA</div>
                            <div>MATERIA</div>
                            <div>DOCENTE RESPONSABLE</div>
                            <div>ACCIONES</div>
                        </div>

                        {asignaturas.length === 0 && (
                            <div className="px-4 py-6 text-center text-sm text-slate-400">
                                Todavía no hay asignaturas registradas.
                            </div>
                        )}

                        {asignaturas.map((asignatura) => {
                            const docente = asignatura.grupos?.[0]?.docente;

                            return (
                                <div
                                    key={asignatura.id}
                                    className="grid min-w-[620px] grid-cols-[0.6fr_1.2fr_1.4fr_0.5fr] items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0"
                                >
                                    <div className="font-mono text-xs text-slate-500">
                                        {asignatura.codigo}
                                    </div>
                                    <div className="font-semibold text-slate-800">
                                        {asignatura.nombre}
                                    </div>
                                    <div className="text-slate-500">
                                        {docente
                                            ? `${docente.nombres} ${docente.apellidos}`
                                            : 'Sin docente asignado'}
                                    </div>
                                    <div>
                                        <button
                                            type="button"
                                            className="text-sm font-medium text-rose-600 hover:text-rose-700"
                                            onClick={() => eliminarAsignatura(asignatura)}
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* SEPARADOR */}
                    <div className="mt-9 mb-2 flex justify-between text-xs tracking-wide text-slate-400">
                        <span>● FCyT INFRAESTRUCTURA</span>
                        <span>EDIF. CENTRAL & NUEVO</span>
                    </div>

                    {/* AMBIENTES */}
                    <div className="mt-5 mb-2 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="h-3 w-1 rounded bg-emerald-600" />
                            <h2 className="text-base font-bold text-slate-800">Ambientes</h2>
                            <span className="rounded bg-blue-50 px-2 py-0.5 text-xs text-slate-500">
                                {ambientes.length} registros
                            </span>
                        </div>

                        <button
                            type="button"
                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            onClick={abrirNuevoAmbiente}
                        >
                            + Nuevo
                        </button>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="grid min-w-[720px] grid-cols-[1.3fr_1fr_0.7fr_0.8fr_0.7fr] items-center gap-3 border-b border-slate-100 bg-blue-50/60 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                            <div>NOMBRE DE AULA</div>
                            <div>EDIFICIO</div>
                            <div>CAPACIDAD</div>
                            <div>ESTADO</div>
                            <div>ACCIONES</div>
                        </div>

                        {ambientes.length === 0 && (
                            <div className="px-4 py-6 text-center text-sm text-slate-400">
                                Todavía no hay ambientes registrados.
                            </div>
                        )}

                        {ambientes.map((ambiente) => (
                            <div
                                key={ambiente.id}
                                className="grid min-w-[720px] grid-cols-[1.3fr_1fr_0.7fr_0.8fr_0.7fr] items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0"
                            >
                                <div className="font-semibold text-slate-800">
                                    {ambiente.nombre}
                                </div>
                                <div className="text-slate-500">{ambiente.ubicacion || '—'}</div>
                                <div className="text-slate-500">{ambiente.capacidad}</div>
                                <div>
                                    <span
                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${
                                            ambiente.estado === 'DISPONIBLE'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-rose-100 text-rose-700'
                                        }`}
                                    >
                                        {ambiente.estado === 'DISPONIBLE' ? (
                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                        ) : (
                                            <Wrench className="h-3.5 w-3.5" />
                                        )}
                                        {ESTADOS_AMBIENTE.find(
                                            (estado) => estado.valor === ambiente.estado
                                        )?.etiqueta ?? ambiente.estado}
                                    </span>
                                </div>
                                <div className="flex gap-3">
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                                        onClick={() => abrirEditarAmbiente(ambiente)}
                                    >
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-rose-600 hover:text-rose-700"
                                        onClick={() => eliminarAmbiente(ambiente)}
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </main>

            {/* MODAL ASIGNATURA */}
            {mostrarFormularioAsignatura && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                        <h2 className="text-lg font-bold text-slate-900">Nueva asignatura</h2>

                        <p className="mt-1 mb-5 text-sm text-slate-500">
                            Registra una nueva materia con su docente responsable
                        </p>

                        <label
                            htmlFor="sigla"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Sigla
                        </label>

                        <input
                            id="sigla"
                            type="text"
                            value={nuevaAsignatura.codigo}
                            onChange={(e) =>
                                setNuevaAsignatura({
                                    ...nuevaAsignatura,
                                    codigo: e.target.value,
                                })
                            }
                            placeholder="Ej. INF-342"
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />

                        <label
                            htmlFor="materia"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Materia
                        </label>

                        <input
                            id="materia"
                            type="text"
                            value={nuevaAsignatura.materia}
                            onChange={(e) =>
                                setNuevaAsignatura({
                                    ...nuevaAsignatura,
                                    materia: e.target.value,
                                })
                            }
                            placeholder="Ej. Redes de Computadoras"
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />

                        <label
                            htmlFor="docente"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Docente responsable
                        </label>

                        <select
                            id="docente"
                            value={nuevaAsignatura.docente_id}
                            onChange={(e) =>
                                setNuevaAsignatura({
                                    ...nuevaAsignatura,
                                    docente_id: e.target.value,
                                })
                            }
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Selecciona un docente</option>
                            {docentes.map((docente) => (
                                <option key={docente.id} value={docente.id}>
                                    {docente.apellidos}, {docente.nombres} ({docente.codigo_docente}
                                    )
                                </option>
                            ))}
                        </select>

                        {docentes.length === 0 && (
                            <p className="mb-4 text-xs text-rose-600">
                                No hay docentes registrados. Se dan de alta en Usuarios y roles.
                            </p>
                        )}

                        <label
                            htmlFor="cupo"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Cupo del grupo
                        </label>

                        <input
                            id="cupo"
                            type="number"
                            min="0"
                            value={nuevaAsignatura.cupo}
                            onChange={(e) =>
                                setNuevaAsignatura({
                                    ...nuevaAsignatura,
                                    cupo: e.target.value,
                                })
                            }
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />

                        <div className="mt-1 flex justify-end gap-2">
                            <button
                                type="button"
                                className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                                onClick={cancelarAsignatura}
                            >
                                Cancelar
                            </button>

                            <button
                                type="button"
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                onClick={guardarAsignatura}
                            >
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL AMBIENTE */}
            {mostrarFormularioAmbiente && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                    <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                        <h2 className="text-lg font-bold text-slate-900">
                            {ambienteEditando === null ? 'Nuevo ambiente' : 'Editar ambiente'}
                        </h2>

                        <p className="mt-1 mb-5 text-sm text-slate-500">
                            {ambienteEditando === null
                                ? 'Registra un nuevo ambiente'
                                : 'Modifica los datos del ambiente'}
                        </p>

                        <label
                            htmlFor="nombre-aula"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Nombre del aula
                        </label>

                        <input
                            id="nombre-aula"
                            type="text"
                            value={nuevoAmbiente.nombre}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    nombre: e.target.value,
                                })
                            }
                            placeholder="Ej. Aula 302"
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />

                        <label
                            htmlFor="edificio"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Edificio
                        </label>

                        <input
                            id="edificio"
                            type="text"
                            value={nuevoAmbiente.ubicacion}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    ubicacion: e.target.value,
                                })
                            }
                            placeholder="Ej. Edificio Nuevo, planta baja"
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />

                        <label
                            htmlFor="capacidad"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Capacidad
                        </label>

                        <input
                            id="capacidad"
                            type="number"
                            min="1"
                            value={nuevoAmbiente.capacidad}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    capacidad: e.target.value,
                                })
                            }
                            placeholder="Ej. 50"
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />

                        <label
                            htmlFor="estado"
                            className="mb-1.5 block text-xs font-semibold text-slate-700"
                        >
                            Estado
                        </label>

                        <select
                            id="estado"
                            value={nuevoAmbiente.estado}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    estado: e.target.value,
                                })
                            }
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        >
                            {ESTADOS_AMBIENTE.map((estado) => (
                                <option key={estado.valor} value={estado.valor}>
                                    {estado.etiqueta}
                                </option>
                            ))}
                        </select>

                        <div className="mt-1 flex justify-end gap-2">
                            <button
                                type="button"
                                className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                                onClick={cancelarAmbiente}
                            >
                                Cancelar
                            </button>

                            <button
                                type="button"
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                onClick={guardarAmbiente}
                            >
                                {ambienteEditando === null ? 'Guardar' : 'Guardar cambios'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

AsignaturasAmbientes.propTypes = {
    onNavigate: PropTypes.func.isRequired,
};

function MenuItem({ icon, text, selected, onClick }) {
    return (
        <div
            className={`mb-1 flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm ${
                selected
                    ? 'bg-blue-600 font-semibold text-white'
                    : 'text-slate-600 hover:bg-slate-100'
            }`}
            onClick={onClick}
        >
            <span className="flex w-5 items-center justify-center">{icon}</span>
            <span>{text}</span>
        </div>
    );
}

MenuItem.propTypes = {
    icon: PropTypes.node.isRequired,
    text: PropTypes.string.isRequired,
    selected: PropTypes.bool,
    onClick: PropTypes.func,
};

export default AsignaturasAmbientes;
