import React from 'react';
import PropTypes from 'prop-types';
import {
    CheckCircle2,
    DatabaseBackup,
    History,
    LayoutGrid,
    Menu,
    MonitorCheck,
    QrCode,
    ShieldCheck,
    User,
    UserCog,
    Users,
    Wrench,
} from 'lucide-react';

const asignaturasIniciales = [
    {
        materia: 'Redes de Computadoras',
        docente: 'Ing. Rolando J. Torrico Mendoza',
    },
    {
        materia: 'Base de Datos I',
        docente: 'Lic. Marco Antonio Arnez Claros',
    },
    {
        materia: 'Sistemas Operativos',
        docente: 'Ing. Patricia Villarroel Siles',
    },
    {
        materia: 'Inteligencia Artificial',
        docente: 'Dr. Carlos Eduardo Vargas Rojas',
    },
    {
        materia: 'Taller de Ingeniería de Software',
        docente: 'Ing. Marcelo Guzmán Flores',
    },
];

const ambientesIniciales = [
    {
        nombre: 'Aula Magna - FCyT',
        capacidad: '120 personas',
        estado: 'Disponible',
    },
    {
        nombre: 'Módulo 3 - Aula 205',
        capacidad: '60',
        estado: 'Disponible',
    },
    {
        nombre: 'Laboratorio de Sistemas 1',
        capacidad: '45',
        estado: 'Mantenimiento',
    },
    {
        nombre: 'Aula 691B',
        capacidad: '88',
        estado: 'Disponible',
    },
    {
        nombre: 'Auditorio Edificio Nuevo',
        capacidad: '150',
        estado: 'Disponible',
    },
];

function AsignaturasAmbientes({ onNavigate }) {
    const [menuAbierto, setMenuAbierto] = React.useState(false);

    const [asignaturas, setAsignaturas] = React.useState(asignaturasIniciales);

    const [ambientes, setAmbientes] = React.useState(ambientesIniciales);

    /* =========================
       ESTADO ASIGNATURAS
    ========================== */

    const [mostrarFormularioAsignatura, setMostrarFormularioAsignatura] = React.useState(false);

    const [asignaturaEditando, setAsignaturaEditando] = React.useState(null);

    const [nuevaAsignatura, setNuevaAsignatura] = React.useState({
        materia: '',
        docente: '',
    });

    /* =========================
       ESTADO AMBIENTES
    ========================== */

    const [mostrarFormularioAmbiente, setMostrarFormularioAmbiente] = React.useState(false);

    const [ambienteEditando, setAmbienteEditando] = React.useState(null);

    const [nuevoAmbiente, setNuevoAmbiente] = React.useState({
        nombre: '',
        capacidad: '',
        estado: 'Disponible',
    });

    /* =========================
       FUNCIONES ASIGNATURAS
    ========================== */

    const abrirNuevaAsignatura = () => {
        setAsignaturaEditando(null);

        setNuevaAsignatura({
            materia: '',
            docente: '',
        });

        setMostrarFormularioAsignatura(true);
    };

    const abrirEditarAsignatura = (index) => {
        const asignatura = asignaturas[index];

        setAsignaturaEditando(index);

        setNuevaAsignatura({
            materia: asignatura.materia,
            docente: asignatura.docente,
        });

        setMostrarFormularioAsignatura(true);
    };

    const cancelarAsignatura = () => {
        setMostrarFormularioAsignatura(false);
        setAsignaturaEditando(null);

        setNuevaAsignatura({
            materia: '',
            docente: '',
        });
    };

    const guardarAsignatura = () => {
        if (nuevaAsignatura.materia.trim() === '' || nuevaAsignatura.docente.trim() === '') {
            alert('Completa todos los campos de la asignatura.');
            return;
        }

        if (asignaturaEditando === null) {
            setAsignaturas([
                ...asignaturas,
                {
                    materia: nuevaAsignatura.materia.trim(),
                    docente: nuevaAsignatura.docente.trim(),
                },
            ]);

            alert('Asignatura creada correctamente.');
        } else {
            const asignaturasActualizadas = [...asignaturas];

            asignaturasActualizadas[asignaturaEditando] = {
                materia: nuevaAsignatura.materia.trim(),
                docente: nuevaAsignatura.docente.trim(),
            };

            setAsignaturas(asignaturasActualizadas);

            alert('Asignatura actualizada correctamente.');
        }

        cancelarAsignatura();
    };

    /* =========================
       FUNCIONES AMBIENTES
    ========================== */

    const abrirNuevoAmbiente = () => {
        setAmbienteEditando(null);

        setNuevoAmbiente({
            nombre: '',
            capacidad: '',
            estado: 'Disponible',
        });

        setMostrarFormularioAmbiente(true);
    };

    const abrirEditarAmbiente = (index) => {
        const ambiente = ambientes[index];

        setAmbienteEditando(index);

        setNuevoAmbiente({
            nombre: ambiente.nombre,
            capacidad: ambiente.capacidad,
            estado: ambiente.estado,
        });

        setMostrarFormularioAmbiente(true);
    };

    const cancelarAmbiente = () => {
        setMostrarFormularioAmbiente(false);
        setAmbienteEditando(null);

        setNuevoAmbiente({
            nombre: '',
            capacidad: '',
            estado: 'Disponible',
        });
    };

    const guardarAmbiente = () => {
        if (nuevoAmbiente.nombre.trim() === '' || nuevoAmbiente.capacidad.trim() === '') {
            alert('Completa todos los campos del ambiente.');
            return;
        }

        if (ambienteEditando === null) {
            setAmbientes([
                ...ambientes,
                {
                    nombre: nuevoAmbiente.nombre.trim(),
                    capacidad: nuevoAmbiente.capacidad.trim(),
                    estado: nuevoAmbiente.estado,
                },
            ]);

            alert('Ambiente creado correctamente.');
        } else {
            const ambientesActualizados = [...ambientes];

            ambientesActualizados[ambienteEditando] = {
                nombre: nuevoAmbiente.nombre.trim(),
                capacidad: nuevoAmbiente.capacidad.trim(),
                estado: nuevoAmbiente.estado,
            };

            setAmbientes(ambientesActualizados);

            alert('Ambiente actualizado correctamente.');
        }

        cancelarAmbiente();
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

                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white">
                        <User className="h-[18px] w-[18px]" strokeWidth={1.75} />
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
                        <div className="grid min-w-[560px] grid-cols-[1.2fr_1.4fr_0.5fr] items-center gap-3 border-b border-slate-100 bg-blue-50/60 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                            <div>MATERIA</div>
                            <div>DOCENTE ASIGNADO</div>
                            <div>ACCIONES</div>
                        </div>

                        {asignaturas.map((asignatura, index) => (
                            <div
                                key={index}
                                className="grid min-w-[560px] grid-cols-[1.2fr_1.4fr_0.5fr] items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0"
                            >
                                <div className="font-semibold text-slate-800">
                                    {asignatura.materia}
                                </div>
                                <div className="text-slate-500">{asignatura.docente}</div>
                                <div>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                                        onClick={() => abrirEditarAsignatura(index)}
                                    >
                                        Editar
                                    </button>
                                </div>
                            </div>
                        ))}
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
                        <div className="grid min-w-[620px] grid-cols-[1.3fr_0.7fr_0.8fr_0.5fr] items-center gap-3 border-b border-slate-100 bg-blue-50/60 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                            <div>NOMBRE DE AULA</div>
                            <div>CAPACIDAD</div>
                            <div>ESTADO</div>
                            <div>ACCIONES</div>
                        </div>

                        {ambientes.map((ambiente, index) => (
                            <div
                                key={index}
                                className="grid min-w-[620px] grid-cols-[1.3fr_0.7fr_0.8fr_0.5fr] items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0"
                            >
                                <div className="font-semibold text-slate-800">
                                    {ambiente.nombre}
                                </div>
                                <div className="text-slate-500">{ambiente.capacidad}</div>
                                <div>
                                    <span
                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${
                                            ambiente.estado === 'Disponible'
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-rose-100 text-rose-700'
                                        }`}
                                    >
                                        {ambiente.estado === 'Disponible' ? (
                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                        ) : (
                                            <Wrench className="h-3.5 w-3.5" />
                                        )}
                                        {ambiente.estado}
                                    </span>
                                </div>
                                <div>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                                        onClick={() => abrirEditarAmbiente(index)}
                                    >
                                        Editar
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
                        <h2 className="text-lg font-bold text-slate-900">
                            {asignaturaEditando === null ? 'Nueva asignatura' : 'Editar asignatura'}
                        </h2>

                        <p className="mt-1 mb-5 text-sm text-slate-500">
                            {asignaturaEditando === null
                                ? 'Registra una nueva materia'
                                : 'Modifica los datos de la asignatura'}
                        </p>

                        <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                            Materia
                        </label>

                        <input
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

                        <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                            Docente asignado
                        </label>

                        <input
                            type="text"
                            value={nuevaAsignatura.docente}
                            onChange={(e) =>
                                setNuevaAsignatura({
                                    ...nuevaAsignatura,
                                    docente: e.target.value,
                                })
                            }
                            placeholder="Ej. Ing. Juan Pérez"
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
                                {asignaturaEditando === null ? 'Guardar' : 'Guardar cambios'}
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

                        <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                            Nombre del aula
                        </label>

                        <input
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

                        <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                            Capacidad
                        </label>

                        <input
                            type="text"
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

                        <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                            Estado
                        </label>

                        <select
                            value={nuevoAmbiente.estado}
                            onChange={(e) =>
                                setNuevoAmbiente({
                                    ...nuevoAmbiente,
                                    estado: e.target.value,
                                })
                            }
                            className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="Disponible">Disponible</option>
                            <option value="Mantenimiento">Mantenimiento</option>
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
