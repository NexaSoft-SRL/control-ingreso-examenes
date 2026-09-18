import React from 'react';
import PropTypes from 'prop-types';
import {
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
} from 'lucide-react';

/**
 * Bitacora (HU-07), lado Frontend. El backend de esta historia (Jofre) hoy
 * solo registra operaciones automaticamente; todavia no existe un endpoint
 * para listarlas ni filtrarlas (no hay BitacoraController ni ruta GET). Esto
 * es el esqueleto de la pantalla con datos de ejemplo, listo para cambiar la
 * fuente de datos por una llamada real en cuanto ese endpoint exista.
 *
 * El filtro por usuario y por rango de fechas ya funciona sobre estos datos
 * de ejemplo, para no tener que rehacer esa logica despues.
 */
const eventosIniciales = [
    {
        fecha: '2026-11-18T10:14:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Generación de códigos QR',
        entidad: 'Redes de Computadoras - Examen Final',
    },
    {
        fecha: '2026-11-18T09:45:00',
        usuario: 'Ing. Patricia Villarroel Siles',
        accion: 'Habilitación de estudiante',
        entidad: 'Estudiante 202104821 (Alvarado Claros)',
    },
    {
        fecha: '2026-11-18T08:30:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Inicio de sesión',
        entidad: 'Cuenta Administrador',
    },
    {
        fecha: '2026-11-17T16:20:00',
        usuario: 'Lic. Marco Antonio Arnez Claros',
        accion: 'Registro de examen',
        entidad: 'Base de Datos I - 2do Parcial',
    },
    {
        fecha: '2026-11-17T14:15:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Carga masiva de padrón',
        entidad: 'Archivo padron_2026_fcyt.csv (184 reg.)',
    },
    {
        fecha: '2026-11-16T11:05:00',
        usuario: 'Dr. Carlos Eduardo Vargas Rojas',
        accion: 'Modificación de aula',
        entidad: 'Laboratorio de Sistemas 1 (Mantenimiento)',
    },
    {
        fecha: '2026-11-16T09:12:00',
        usuario: 'Ing. Marcelo Guzmán Flores',
        accion: 'Habilitación de estudiante',
        entidad: 'Estudiante 201901349 (Camacho Zeballos)',
    },
    {
        fecha: '2026-11-15T17:50:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Actualización de rol',
        entidad: 'Lic. Valeria Bustamante Torrico (Personal de control)',
    },
    {
        fecha: '2026-11-15T15:30:00',
        usuario: 'Ing. Patricia Villarroel Siles',
        accion: 'Inicio de sesión',
        entidad: 'Cuenta Docente',
    },
    {
        fecha: '2026-11-14T10:00:00',
        usuario: 'Dr. Rolando J. Torrico Mendoza',
        accion: 'Respaldo del sistema',
        entidad: 'Backup_FCyT_20261114.sql',
    },
];

const totalEventosDelServidor = 248;

function formatearFechaHora(isoFecha) {
    const fecha = new Date(isoFecha);
    const meses = [
        'Ene',
        'Feb',
        'Mar',
        'Abr',
        'May',
        'Jun',
        'Jul',
        'Ago',
        'Sep',
        'Oct',
        'Nov',
        'Dic',
    ];

    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = meses[fecha.getMonth()];
    const horas = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');

    return `${dia}/${mes}/${fecha.getFullYear()} ${horas}:${minutos}`;
}

function Bitacora({ onNavigate }) {
    const [menuAbierto, setMenuAbierto] = React.useState(false);

    const usuarios = React.useMemo(
        () => [...new Set(eventosIniciales.map((evento) => evento.usuario))],
        []
    );

    const [usuarioFiltro, setUsuarioFiltro] = React.useState('');
    const [desde, setDesde] = React.useState('');
    const [hasta, setHasta] = React.useState('');
    const [filtrosAplicados, setFiltrosAplicados] = React.useState({
        usuario: '',
        desde: '',
        hasta: '',
    });

    const eventosFiltrados = React.useMemo(() => {
        return eventosIniciales.filter((evento) => {
            if (filtrosAplicados.usuario && evento.usuario !== filtrosAplicados.usuario) {
                return false;
            }

            const fechaEvento = evento.fecha.slice(0, 10);

            if (filtrosAplicados.desde && fechaEvento < filtrosAplicados.desde) {
                return false;
            }

            if (filtrosAplicados.hasta && fechaEvento > filtrosAplicados.hasta) {
                return false;
            }

            return true;
        });
    }, [filtrosAplicados]);

    function manejarFiltrar(evento) {
        evento.preventDefault();
        setFiltrosAplicados({ usuario: usuarioFiltro, desde, hasta });
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
                        onClick={() => alert('Padrón: próximamente')}
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

                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white">
                        <User className="h-[18px] w-[18px]" strokeWidth={1.75} />
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
                                {usuarios.map((usuario) => (
                                    <option key={usuario} value={usuario}>
                                        {usuario}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label
                                className="text-xs font-semibold text-slate-700"
                                htmlFor="desde-filtro"
                            >
                                Desde
                            </label>

                            <input
                                id="desde-filtro"
                                type="date"
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                value={desde}
                                onChange={(e) => setDesde(e.target.value)}
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label
                                className="text-xs font-semibold text-slate-700"
                                htmlFor="hasta-filtro"
                            >
                                Hasta
                            </label>

                            <input
                                id="hasta-filtro"
                                type="date"
                                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                value={hasta}
                                onChange={(e) => setHasta(e.target.value)}
                            />
                        </div>

                        <button
                            type="submit"
                            className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700"
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

                        {eventosFiltrados.map((evento, indice) => (
                            <div
                                key={indice}
                                className="grid min-w-[760px] grid-cols-[1fr_1.3fr_1.2fr_1.6fr] items-center gap-3 border-b border-slate-100 px-4 py-3.5 text-sm last:border-b-0"
                            >
                                <div className="font-mono text-xs text-slate-500">
                                    {formatearFechaHora(evento.fecha)}
                                </div>
                                <div className="font-semibold text-slate-800">{evento.usuario}</div>
                                <div>{evento.accion}</div>
                                <div className="text-slate-600">{evento.entidad}</div>
                            </div>
                        ))}
                    </div>

                    <p className="mt-3 text-sm text-slate-500">
                        Mostrando {eventosFiltrados.length} de {totalEventosDelServidor} eventos
                    </p>
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
