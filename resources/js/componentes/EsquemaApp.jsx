import { useState } from 'react';
import PropTypes from 'prop-types';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Menu, X, LogOut } from 'lucide-react';
import Logo from './Logo';
import { usarSesion } from '../sesion/SesionContexto';
import { vistaDeRuta, vistasDe } from '../navegacion/vistas';
import { iniciales } from '../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

function itemClases(activo) {
    return `flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${FOCO} ${
        activo ? 'bg-primary-50 text-primary-700' : 'text-slate-700 hover:bg-slate-100'
    }`;
}

// Armazón de la aplicación: barra lateral en escritorio y barra superior
// más barra inferior en el teléfono, solo con las vistas que permiten los
// permisos de la cuenta.
//
// El usuario y su rol están a la vista en todas las pantallas. El menú se
// arma por secciones (`grupo`), la barra inferior lleva las vistas marcadas
// `movil` y el resto queda en «Más». Una vista `oculta` no figura en el
// menú: se llega a ella desde otra, que queda marcada como activa (`padre`).
//
// El usuario, el rol y las vistas salen de la sesión. «Cerrar sesión» llama
// a `salir()` y lleva al acceso.
export default function EsquemaApp({ children }) {
    const [menuAbierto, setMenuAbierto] = useState(false);
    const [saliendo, setSaliendo] = useState(false);
    const location = useLocation();
    const navegar = useNavigate();
    const { usuario: cuenta, salir } = usarSesion();
    const todas = vistasDe(cuenta);
    const vistas = todas.filter((v) => !v.oculta);
    const actual = vistaDeRuta(todas, location.pathname);
    const rutaActiva = actual?.padre ?? actual?.ruta;
    const grupos = [...new Set(vistas.map((v) => v.grupo ?? ''))];
    // Hasta cuatro en la barra y el resto en «Más»; si lo que sobra es una
    // sola vista, entra también en la barra en vez de abrir una hoja para ella.
    const marcadas = vistas.some((v) => v.movil)
        ? vistas.filter((v) => v.movil).slice(0, 4)
        : vistas.slice(0, 4);
    const sobrantes = vistas.filter((v) => !marcadas.includes(v));
    const enBarra = sobrantes.length === 1 ? vistas : marcadas;
    const resto = sobrantes.length === 1 ? [] : sobrantes;
    const conBarra = vistas.length > 1;
    const nombre = cuenta?.nombre ?? '';
    const rol = cuenta?.rol ?? '';

    async function cerrarSesion() {
        if (saliendo) return;
        setSaliendo(true);
        await salir();
        navegar('/login', { replace: true });
    }

    const usuario = (
        <div className="flex min-w-0 items-center gap-2.5">
            <span
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-800"
                aria-hidden="true"
            >
                {iniciales(nombre)}
            </span>
            <span className="min-w-0">
                <span className="line-clamp-2 block break-words text-sm font-medium leading-tight text-slate-800">
                    {nombre}
                </span>
                <span className="block truncate text-xs text-slate-600">{rol}</span>
            </span>
        </div>
    );

    return (
        <div className="flex min-h-dvh flex-col bg-slate-100 md:flex-row">
            <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white md:sticky md:top-0 md:flex md:h-dvh">
                <Link
                    to={vistas[0]?.ruta ?? '/'}
                    className={`flex items-center gap-2 border-b border-slate-200 px-5 py-4 ${FOCO}`}
                >
                    <Logo />
                    <div className="leading-tight">
                        <p className="text-sm font-semibold text-slate-800">Control de ingreso</p>
                        <p className="text-xs text-slate-600">Exámenes masivos</p>
                    </div>
                </Link>
                <nav className="flex-1 space-y-4 overflow-y-auto px-3 py-4" aria-label="Principal">
                    {grupos.map((grupo) => (
                        <div key={grupo}>
                            {grupo && (
                                <p className="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    {grupo}
                                </p>
                            )}
                            <ul className="space-y-0.5">
                                {vistas
                                    .filter((v) => (v.grupo ?? '') === grupo)
                                    .map((v) => (
                                        <li key={v.ruta}>
                                            <Link
                                                to={v.ruta}
                                                className={itemClases(rutaActiva === v.ruta)}
                                                aria-current={
                                                    rutaActiva === v.ruta ? 'page' : undefined
                                                }
                                            >
                                                <v.Icono
                                                    className="h-[18px] w-[18px] shrink-0"
                                                    strokeWidth={2}
                                                />
                                                {v.titulo}
                                            </Link>
                                        </li>
                                    ))}
                            </ul>
                        </div>
                    ))}
                </nav>
                <div className="flex items-center justify-between gap-2 border-t border-slate-200 px-4 py-3">
                    {usuario}
                    <button
                        type="button"
                        onClick={cerrarSesion}
                        disabled={saliendo}
                        title="Cerrar sesión"
                        aria-label="Cerrar sesión"
                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-danger-700 ${FOCO}`}
                    >
                        <LogOut className="h-[18px] w-[18px]" strokeWidth={2} />
                    </button>
                </div>
            </aside>

            <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2.5 md:hidden">
                <div className="flex min-w-0 items-center gap-3">
                    <Logo className="h-8 w-8" />
                    {usuario}
                </div>
                <button
                    type="button"
                    onClick={cerrarSesion}
                    disabled={saliendo}
                    title="Cerrar sesión"
                    aria-label="Cerrar sesión"
                    className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 ${FOCO}`}
                >
                    <LogOut className="h-5 w-5" strokeWidth={2} />
                </button>
            </header>

            <main
                className={`min-w-0 flex-1 md:pb-16 ${conBarra ? 'pb-[calc(5rem+env(safe-area-inset-bottom))]' : 'pb-6'}`}
            >
                <div className="mx-auto max-w-6xl px-4 py-5 md:px-8 md:py-8">{children}</div>
            </main>

            {menuAbierto && resto.length > 0 && (
                <>
                    <button
                        type="button"
                        aria-label="Cerrar menú"
                        onClick={() => setMenuAbierto(false)}
                        className="fixed inset-0 z-20 bg-slate-900/40 md:hidden"
                    />
                    <div className="fixed inset-x-0 bottom-0 z-30 rounded-t-2xl bg-white p-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] md:hidden">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-semibold text-slate-800">Más</p>
                            <button
                                type="button"
                                onClick={() => setMenuAbierto(false)}
                                aria-label="Cerrar"
                                className={`flex h-11 w-11 items-center justify-center rounded-lg text-slate-600 ${FOCO}`}
                            >
                                <X className="h-5 w-5" strokeWidth={2} />
                            </button>
                        </div>
                        <ul className="divide-y divide-slate-100">
                            {resto.map((v) => (
                                <li key={v.ruta}>
                                    <Link
                                        to={v.ruta}
                                        onClick={() => setMenuAbierto(false)}
                                        className={`flex min-h-12 items-center gap-3 text-sm font-medium ${rutaActiva === v.ruta ? 'text-primary-700' : 'text-slate-700'}`}
                                    >
                                        <v.Icono className="h-5 w-5 shrink-0" strokeWidth={2} />
                                        {v.titulo}
                                        {v.grupo && (
                                            <span className="ml-auto text-xs font-normal text-slate-500">
                                                {v.grupo}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                </>
            )}

            {conBarra && (
                <nav
                    aria-label="Principal"
                    className="fixed inset-x-0 bottom-0 z-10 grid border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden"
                    style={{
                        gridTemplateColumns: `repeat(${enBarra.length + (resto.length > 0 ? 1 : 0)}, minmax(0, 1fr))`,
                    }}
                >
                    {enBarra.map((v) => {
                        const activo = rutaActiva === v.ruta;
                        return (
                            <Link
                                key={v.ruta}
                                to={v.ruta}
                                aria-current={activo ? 'page' : undefined}
                                className={`flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 text-xs font-medium ${activo ? 'text-primary-700' : 'text-slate-600'}`}
                            >
                                <span
                                    className={`flex h-7 w-12 items-center justify-center rounded-full ${activo ? 'bg-primary-100' : ''}`}
                                >
                                    <v.Icono className="h-5 w-5" strokeWidth={2} />
                                </span>
                                <span className="max-w-full truncate">{v.titulo}</span>
                            </Link>
                        );
                    })}
                    {resto.length > 0 && (
                        <button
                            type="button"
                            onClick={() => setMenuAbierto(true)}
                            aria-expanded={menuAbierto}
                            className={`flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs font-medium ${resto.some((v) => v.ruta === rutaActiva) ? 'text-primary-700' : 'text-slate-600'}`}
                        >
                            <span className="flex h-7 w-12 items-center justify-center">
                                <Menu className="h-5 w-5" strokeWidth={2} />
                            </span>
                            Más
                        </button>
                    )}
                </nav>
            )}
        </div>
    );
}

EsquemaApp.propTypes = { children: PropTypes.node };
