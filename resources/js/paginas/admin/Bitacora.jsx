import React from 'react';
import PropTypes from 'prop-types';
import {
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
} from 'lucide-react';

/**
 * Bitacora (HU-07), lado Frontend. Consume GET /api/bitacora (backend de
 * Jofre), que filtra por usuario_id, fecha (un solo dia, Y-m-d) y operacion.
 */
const etiquetaOperacion = {
    'asignatura.registrar': 'Registro de asignatura',
    'asignatura.eliminar': 'Eliminación de asignatura',
};

const etiquetaTabla = {
    asignaturas: 'Asignatura',
};

const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

// Se parsea el texto tal cual llega para no correr la hora por la zona horaria del navegador.
function formatearFechaHora(fecha) {
    const partes = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(fecha ?? '');

    if (!partes) {
        return fecha ?? '';
    }

    const [, anio, mes, dia, horas, minutos] = partes;

    return `${dia}/${meses[Number(mes) - 1]}/${anio} ${horas}:${minutos}`;
}

function describirEntidad(operacion) {
    if (operacion.descripcion) {
        return operacion.descripcion;
    }

    if (!operacion.tabla_afectada) {
        return '—';
    }

    const tabla = etiquetaTabla[operacion.tabla_afectada] ?? operacion.tabla_afectada;

    return operacion.registro_id === null ? tabla : `${tabla} #${operacion.registro_id}`;
}

function Bitacora({ onNavigate }) {
    const [menuAbierto, setMenuAbierto] = React.useState(false);

    const [operaciones, setOperaciones] = React.useState([]);
    const [cargando, setCargando] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [usuariosConocidos, setUsuariosConocidos] = React.useState([]);
    const [operacionesConocidas, setOperacionesConocidas] = React.useState(
        Object.keys(etiquetaOperacion)
    );

    const [usuarioFiltro, setUsuarioFiltro] = React.useState('');
    const [fechaFiltro, setFechaFiltro] = React.useState('');
    const [operacionFiltro, setOperacionFiltro] = React.useState('');

    const ultimaConsulta = React.useRef(0);

    const consultar = React.useCallback(
        async (filtros) => {
            const numeroConsulta = ++ultimaConsulta.current;
            const params = {};

            if (filtros.usuario) params.usuario_id = filtros.usuario;
            if (filtros.fecha) params.fecha = filtros.fecha;
            if (filtros.operacion) params.operacion = filtros.operacion;

            setCargando(true);
            setError(null);

            try {
                const respuesta = await window.axios.get('/api/bitacora', { params });

                if (numeroConsulta !== ultimaConsulta.current) {
                    return;
                }

                const datos = respuesta.data.data;
                setOperaciones(datos);

                setUsuariosConocidos((anteriores) => {
                    const porId = new Map(anteriores.map((usuario) => [usuario.id, usuario]));
                    datos.forEach((operacion) => {
                        if (operacion.usuario) {
                            porId.set(operacion.usuario.id, operacion.usuario);
                        }
                    });
                    return [...porId.values()].sort((a, b) => a.name.localeCompare(b.name));
                });

                setOperacionesConocidas((anteriores) => [
                    ...new Set([...anteriores, ...datos.map((operacion) => operacion.operacion)]),
                ]);
            } catch (excepcion) {
                if (numeroConsulta !== ultimaConsulta.current) {
                    return;
                }

                const estado = excepcion.response?.status;

                if (estado === 401 || estado === 419) {
                    onNavigate?.('login');
                    return;
                }

                setOperaciones([]);
                setError(
                    estado === 422
                        ? 'Los filtros no son válidos. Revisa la fecha y vuelve a intentar.'
                        : 'No se pudo cargar la bitácora. Intenta de nuevo.'
                );
            } finally {
                if (numeroConsulta === ultimaConsulta.current) {
                    setCargando(false);
                }
            }
        },
        [onNavigate]
    );

    React.useEffect(() => {
        consultar({});
    }, [consultar]);

    function manejarFiltrar(evento) {
        evento.preventDefault();
        consultar({ usuario: usuarioFiltro, fecha: fechaFiltro, operacion: operacionFiltro });
    }

    function navegar(clave) {
        setMenuAbierto(false);
        onNavigate?.(clave);
    }

    return (
        <div className="flex min-h-screen w-full bg-white font-sans text-slate-800">
            {menuAbierto && (
                <div
                    className="fixed inset-0 z-30 bg-slate-900/40 md:hidden"
                    onClick={() => setMenuAbierto(false)}
                />
            )}

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
                        <div className="text-sm leading-tight font-bold text-slate-800">UMSS</div>
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
                        selected
                    />
                    <MenuItem
                        icon={<DatabaseBackup className="h-[18px] w-[18px]" />}
                        text="Respaldo"
                        onClick={() => alert('Respaldo: próximamente')}
                    />
                </div>
            </aside>

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
                    <h1 className="text-2xl font-bold text-slate-900">Bitácora</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Registro de eventos y operaciones del sistema
                    </p>

                    <form
                        className="mt-5 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:flex-wrap sm:items-end"
                        onSubmit={manejarFiltrar}
                    >
                        <div className="flex flex-1 flex-col gap-1.5 sm:min-w-[220px]">
                            <label
                                className="text-xs font-semibold text-slate-700"
                                htmlFor="usuario-filtro"
                            >
                                Usuario
                            </label>

                            <select
                                id="usuario-filtro"
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                value={usuarioFiltro}
                                onChange={(e) => setUsuarioFiltro(e.target.value)}
                            >
                                <option value="">Todos los usuarios</option>
                                {usuariosConocidos.map((usuario) => (
                                    <option key={usuario.id} value={usuario.id}>
                                        {usuario.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label
                                className="text-xs font-semibold text-slate-700"
                                htmlFor="fecha-filtro"
                            >
                                Fecha
                            </label>

                            <input
                                id="fecha-filtro"
                                type="date"
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                value={fechaFiltro}
                                onChange={(e) => setFechaFiltro(e.target.value)}
                            />
                        </div>

                        <div className="flex flex-col gap-1.5 sm:min-w-[200px]">
                            <label
                                className="text-xs font-semibold text-slate-700"
                                htmlFor="operacion-filtro"
                            >
                                Operación
                            </label>

                            <select
                                id="operacion-filtro"
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                value={operacionFiltro}
                                onChange={(e) => setOperacionFiltro(e.target.value)}
                            >
                                <option value="">Todas las operaciones</option>
                                {operacionesConocidas.map((codigo) => (
                                    <option key={codigo} value={codigo}>
                                        {etiquetaOperacion[codigo] ?? codigo}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <button
                            type="submit"
                            className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
                            disabled={cargando}
                        >
                            Filtrar
                        </button>
                    </form>

                    <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="grid min-w-[760px] grid-cols-[1fr_1.3fr_1.2fr_1.6fr] items-center gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                            <div>FECHA Y HORA</div>
                            <div>USUARIO</div>
                            <div>ACCIÓN</div>
                            <div>ENTIDAD AFECTADA</div>
                        </div>

                        {cargando && (
                            <p className="px-4 py-6 text-center text-sm text-slate-500">
                                Cargando operaciones…
                            </p>
                        )}

                        {!cargando && error && (
                            <p role="alert" className="px-4 py-6 text-center text-sm text-red-600">
                                {error}
                            </p>
                        )}

                        {!cargando && !error && operaciones.length === 0 && (
                            <p className="px-4 py-6 text-center text-sm text-slate-500">
                                No hay eventos para los filtros seleccionados.
                            </p>
                        )}

                        {!cargando &&
                            !error &&
                            operaciones.map((operacion) => (
                                <div
                                    key={operacion.id}
                                    className="grid min-w-[760px] grid-cols-[1fr_1.3fr_1.2fr_1.6fr] items-center gap-3 border-b border-slate-100 px-4 py-3.5 text-sm last:border-b-0"
                                >
                                    <div className="font-mono text-xs text-slate-500">
                                        {formatearFechaHora(operacion.fecha_operacion)}
                                    </div>
                                    <div className="font-semibold text-slate-800">
                                        {operacion.usuario ? (
                                            operacion.usuario.name
                                        ) : (
                                            <span className="font-normal text-slate-400 italic">
                                                Usuario eliminado
                                            </span>
                                        )}
                                    </div>
                                    <div>
                                        {etiquetaOperacion[operacion.operacion] ??
                                            operacion.operacion}
                                    </div>
                                    <div className="text-slate-600">
                                        {describirEntidad(operacion)}
                                    </div>
                                </div>
                            ))}
                    </div>

                    {!cargando && !error && (
                        <p className="mt-3 text-sm text-slate-500">
                            Mostrando {operaciones.length}{' '}
                            {operaciones.length === 1 ? 'evento' : 'eventos'}
                        </p>
                    )}
                </section>
            </main>
        </div>
    );
}

Bitacora.propTypes = {
    onNavigate: PropTypes.func,
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

export default Bitacora;
