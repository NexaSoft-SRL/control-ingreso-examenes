import PropTypes from 'prop-types';
import { useEffect } from 'react';
import { X } from 'lucide-react';

// Ventana sobre la pantalla: confirmaciones, formularios cortos y vistas de
// detalle. `acciones` va al pie (normalmente dos `Boton`). Se cierra con
// Escape, con la equis o tocando fuera. En móvil sube desde abajo.
export default function Dialogo({
    titulo,
    abierto = true,
    onCerrar,
    acciones,
    ancho = 'max-w-md',
    children,
}) {
    useEffect(() => {
        if (!abierto) return undefined;
        const tecla = (e) => e.key === 'Escape' && onCerrar();
        window.addEventListener('keydown', tecla);
        return () => window.removeEventListener('keydown', tecla);
    }, [abierto, onCerrar]);

    if (!abierto) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
            <button
                type="button"
                aria-label="Cerrar"
                onClick={onCerrar}
                className="absolute inset-0 bg-slate-900/40"
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-label={titulo}
                className={`relative flex max-h-[90dvh] w-full flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-xl ${ancho}`}
            >
                <header className="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                    <h2 className="min-w-0 truncate text-base font-semibold text-slate-900">
                        {titulo}
                    </h2>
                    <button
                        type="button"
                        aria-label="Cerrar"
                        onClick={onCerrar}
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </header>
                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
                {acciones && (
                    <footer className="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-5 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                        {acciones}
                    </footer>
                )}
            </div>
        </div>
    );
}

Dialogo.propTypes = {
    titulo: PropTypes.string.isRequired,
    abierto: PropTypes.bool,
    onCerrar: PropTypes.func.isRequired,
    acciones: PropTypes.node,
    ancho: PropTypes.string,
    children: PropTypes.node,
};
