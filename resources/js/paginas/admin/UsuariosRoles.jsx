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
    X,
} from 'lucide-react';

const usuarios = [
    {
        nombre: 'Dr. Rolando J. Torrico Mendoza',
        correo: 'rolando.torrico@fcyt.umss.edu.bo',
        rol: 'Administrador',
        tipo: 'administrador',
        activo: true,
    },
    {
        nombre: 'Lic. Marco Antonio Arnez',
        correo: 'marco.arnez@fcyt.umss.edu.bo',
        rol: 'Responsable académico',
        tipo: 'academico',
        activo: true,
    },
    {
        nombre: 'Ing. Patricia Villarroel Siles',
        correo: 'patricia.villarroel@fcyt.umss.edu.bo',
        rol: 'Docente',
        tipo: 'docente',
        activo: true,
    },
    {
        nombre: 'Ing. Marcelo Guzmán Flores',
        correo: 'marcelo.guzman@fcyt.umss.edu.bo',
        rol: 'Personal de control',
        tipo: 'control',
        activo: true,
    },
    {
        nombre: 'Dr. Carlos Eduardo Vargas',
        correo: 'carlos.vargas@fcyt.umss.edu.bo',
        rol: 'Docente',
        tipo: 'docente',
        activo: false,
    },
    {
        nombre: 'Lic. Valeria Bustamante Torrico',
        correo: 'valeria.bustamante@fcyt.umss.edu.bo',
        rol: 'Personal de control',
        tipo: 'control',
        activo: true,
    },
];

const estilosPorRol = {
    administrador: 'bg-slate-900 text-white',
    academico: 'bg-purple-100 text-purple-700',
    docente: 'bg-sky-100 text-sky-700',
    control: 'bg-emerald-100 text-emerald-700',
};

function UsuariosRoles({ onNavigate }) {
    const [menuAbierto, setMenuAbierto] = React.useState(false);
    const [mostrarFormulario, setMostrarFormulario] = React.useState(false);
    const [, setUsuarioEditando] = React.useState(null);

    const [nuevoUsuario, setNuevoUsuario] = React.useState({
        nombre: '',
        correo: '',
        rol: 'Docente',
    });

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
                {/* LOGO */}
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

                {/* MENU */}
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
                        selected
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
                {/* BARRA SUPERIOR */}
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

                {/* CONTENIDO */}
                <section className="p-4 md:p-6 lg:p-8">
                    {/* TITULO Y BOTON */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">Usuarios y roles</h1>

                            <p className="mt-1 text-sm text-slate-500">
                                Gestión de cuentas y permisos del sistema
                            </p>
                        </div>

                        <button
                            type="button"
                            className="self-start rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 sm:self-auto"
                            onClick={() => setMostrarFormulario(true)}
                        >
                            + Nuevo usuario
                        </button>
                    </div>

                    {/* PESTAÑAS */}
                    <div className="mt-5 flex gap-6 border-b border-slate-200">
                        <button
                            type="button"
                            className="border-b-2 border-blue-600 px-1 pb-2.5 text-sm font-semibold text-blue-600"
                        >
                            Usuarios
                        </button>

                        <button type="button" className="px-1 pb-2.5 text-sm text-slate-500">
                            Roles
                        </button>
                    </div>

                    {/* TABLA */}
                    <div className="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="grid min-w-[720px] grid-cols-[1.25fr_1.15fr_0.95fr_0.55fr_0.5fr] items-center gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                            <div>NOMBRE</div>
                            <div>CORREO</div>
                            <div>ROL</div>
                            <div>ESTADO</div>
                            <div>ACCIONES</div>
                        </div>

                        {usuarios.map((usuario, index) => (
                            <div
                                key={index}
                                className="grid min-w-[720px] grid-cols-[1.25fr_1.15fr_0.95fr_0.55fr_0.5fr] items-center gap-3 border-b border-slate-100 px-4 py-3.5 text-sm last:border-b-0"
                            >
                                <div className="font-semibold text-slate-800">{usuario.nombre}</div>

                                <div className="text-xs text-slate-500">{usuario.correo}</div>

                                <div>
                                    <span
                                        className={`inline-block rounded-full px-2.5 py-1 text-xs font-medium ${estilosPorRol[usuario.tipo]}`}
                                    >
                                        {usuario.rol}
                                    </span>
                                </div>

                                <div>
                                    <span
                                        className={`relative inline-flex h-4 w-7 items-center rounded-full transition-colors ${
                                            usuario.activo ? 'bg-blue-600' : 'bg-slate-300'
                                        }`}
                                    >
                                        <span
                                            className={`inline-block h-3 w-3 transform rounded-full bg-white transition-transform ${
                                                usuario.activo
                                                    ? 'translate-x-3.5'
                                                    : 'translate-x-0.5'
                                            }`}
                                        />
                                    </span>
                                </div>

                                <div>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-blue-600 hover:text-blue-700"
                                        onClick={() => {
                                            setUsuarioEditando(usuario);
                                            setNuevoUsuario({
                                                nombre: usuario.nombre,
                                                correo: usuario.correo,
                                                rol: usuario.rol,
                                            });
                                            setMostrarFormulario(true);
                                        }}
                                    >
                                        Editar
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* PIE */}
                    <p className="mt-3 text-xs text-slate-400">
                        Las cuentas las crea el Administrador.
                    </p>
                </section>

                {mostrarFormulario && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
                        <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                            <div className="mb-5 flex items-start justify-between">
                                <div>
                                    <h2 className="text-lg font-bold text-slate-900">
                                        Nuevo usuario
                                    </h2>

                                    <p className="mt-1 text-sm text-slate-500">
                                        Crear una nueva cuenta del sistema
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    className="text-slate-400 hover:text-slate-600"
                                    onClick={() => setMostrarFormulario(false)}
                                    aria-label="Cerrar"
                                >
                                    <X className="h-5 w-5" strokeWidth={1.75} />
                                </button>
                            </div>

                            <div className="mb-4">
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                                    Nombre completo
                                </label>

                                <input
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    value={nuevoUsuario.nombre}
                                    onChange={(e) =>
                                        setNuevoUsuario({
                                            ...nuevoUsuario,
                                            nombre: e.target.value,
                                        })
                                    }
                                    placeholder="Ingrese el nombre completo"
                                />
                            </div>

                            <div className="mb-4">
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                                    Correo
                                </label>

                                <input
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    value={nuevoUsuario.correo}
                                    onChange={(e) =>
                                        setNuevoUsuario({
                                            ...nuevoUsuario,
                                            correo: e.target.value,
                                        })
                                    }
                                    placeholder="correo@fcyt.umss.edu.bo"
                                />
                            </div>

                            <div className="mb-5">
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700">
                                    Rol
                                </label>

                                <select
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    value={nuevoUsuario.rol}
                                    onChange={(e) =>
                                        setNuevoUsuario({
                                            ...nuevoUsuario,
                                            rol: e.target.value,
                                        })
                                    }
                                >
                                    <option>Administrador</option>
                                    <option>Responsable académico</option>
                                    <option>Docente</option>
                                    <option>Personal de control</option>
                                </select>
                            </div>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                                    onClick={() => setMostrarFormulario(false)}
                                >
                                    Cancelar
                                </button>

                                <button
                                    type="button"
                                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                    onClick={() => {
                                        alert('Usuario creado correctamente');

                                        setMostrarFormulario(false);

                                        setNuevoUsuario({
                                            nombre: '',
                                            correo: '',
                                            rol: 'Docente',
                                        });
                                    }}
                                >
                                    Crear usuario
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </main>
        </div>
    );
}

UsuariosRoles.propTypes = {
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

export default UsuariosRoles;
