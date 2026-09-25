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

// Menu lateral y encabezado del administrador, para pantallas que solo traen
// su contenido (p. ej. el registro de estudiantes de HU-03).
export default function LayoutAdmin({ seleccionado, onNavigate, children }) {
    const [menuAbierto, setMenuAbierto] = React.useState(false);

    function navegar(clave) {
        setMenuAbierto(false);
        onNavigate?.(clave);
    }

    const items = [
        { clave: 'padron', texto: 'Padrón', Icono: Users },
        { clave: 'asignaturas', texto: 'Asignaturas y ambientes', Icono: LayoutGrid },
        { clave: 'qr', texto: 'Códigos QR', Icono: QrCode, proximamente: true },
        { clave: 'usuarios', texto: 'Usuarios y roles', Icono: UserCog },
        { clave: 'bitacora', texto: 'Bitácora', Icono: History },
        { clave: 'respaldo', texto: 'Respaldo', Icono: DatabaseBackup, proximamente: true },
    ];

    return (
        <div className="flex min-h-screen w-full bg-white font-sans text-slate-800">
            {menuAbierto && (
                <button
                    type="button"
                    aria-label="Cerrar menú"
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

                <nav className="px-3 py-4">
                    <div className="px-3 pb-2 text-[11px] font-semibold tracking-wide text-slate-400">
                        ADMINISTRADOR
                    </div>

                    {items.map(({ clave, texto, Icono, proximamente }) => (
                        <button
                            key={clave}
                            type="button"
                            className={`mb-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm ${
                                seleccionado === clave
                                    ? 'bg-blue-600 font-semibold text-white'
                                    : 'text-slate-600 hover:bg-slate-100'
                            }`}
                            onClick={() =>
                                proximamente ? alert(`${texto}: próximamente`) : navegar(clave)
                            }
                        >
                            <span className="flex w-5 items-center justify-center">
                                <Icono className="h-[18px] w-[18px]" />
                            </span>
                            <span>{texto}</span>
                        </button>
                    ))}
                </nav>
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

                {children}
            </main>
        </div>
    );
}

LayoutAdmin.propTypes = {
    seleccionado: PropTypes.string,
    onNavigate: PropTypes.func,
    children: PropTypes.node.isRequired,
};
