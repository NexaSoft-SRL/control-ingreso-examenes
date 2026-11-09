import PropTypes from 'prop-types';
import { useEffect, useRef } from 'react';
import { X } from 'lucide-react';

// Ventana sobre la pantalla: confirmaciones, formularios cortos y vistas de
// detalle. `acciones` va al pie (normalmente dos `Boton`). Se cierra con
// Escape, con la equis o tocando fuera. En móvil sube desde abajo.
// Al abrir, el foco pasa al primer campo (o al diálogo) y, al cerrar, vuelve
// a donde estaba: el teclado no se queda escribiendo detrás.
export default function Dialogo({
    titulo,
    abierto = true,
    onCerrar,
    acciones,
    ancho = 'max-w-md',
    children,
}) {
    const caja = useRef(null);
    // El foco de antes se toma al dibujar, antes de que un campo con
    // `autoFocus` dentro del diálogo lo mueva.
    const anteriorRef = useRef(null);
    if (abierto && anteriorRef.current === null && typeof document !== 'undefined') {
        anteriorRef.current = document.activeElement;
    }

    useEffect(() => {
        if (!abierto) return undefined;
        const anterior = anteriorRef.current;
        const dentro = caja.current;
        if (dentro && !dentro.contains(document.activeElement)) {
            const campo = dentro.querySelector(
                'input:not([type=hidden]):not([disabled]):not(.sr-only), textarea:not([disabled]), select:not([disabled])'
            );
            (campo ?? dentro).focus();
        }
        return () => {
            anteriorRef.current = null;
            if (anterior instanceof HTMLElement && document.contains(anterior)) anterior.focus();
        };
    }, [abierto]);

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
                ref={caja}
                tabIndex={-1}
                role="dialog"
                aria-modal="true"
                aria-label={titulo}
                className={`relative flex max-h-[90dvh] w-full flex-col rounded-t-2xl bg-white shadow-xl outline-none sm:rounded-xl ${ancho}`}
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
