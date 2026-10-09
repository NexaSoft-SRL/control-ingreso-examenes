import PropTypes from 'prop-types';
import { useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const BOTON =
    'flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Parte en páginas una lista que ya está entera en memoria (los catálogos que
// la API devuelve completos). `clave` resume los filtros vigentes: al cambiar,
// se vuelve a la primera página. Las listas grandes paginan en el servidor:
// api/usarPaginaServidor.js.
export function usePaginas(lista, porPagina = 25, clave = '') {
    const [estado, setEstado] = useState({ pagina: 1, clave });
    const ultima = Math.max(1, Math.ceil(lista.length / porPagina));
    const pagina = estado.clave === clave ? Math.min(estado.pagina, ultima) : 1;
    const desde = (pagina - 1) * porPagina;

    return {
        visibles: lista.slice(desde, desde + porPagina),
        paginacion: {
            total: lista.length,
            pagina,
            porPagina,
            onCambiar: (p) => setEstado({ pagina: p, clave }),
        },
    };
}

// Pie de una lista paginada: «1–25 de 849» y los botones de página. Con una
// sola página muestra solo el total. `unidad` es un texto o el par
// [singular, plural].
export default function Paginacion({
    total,
    pagina,
    porPagina,
    onCambiar,
    unidad = '',
    className = '',
}) {
    const ultima = Math.max(1, Math.ceil(total / porPagina));
    const desde = total === 0 ? 0 : (pagina - 1) * porPagina + 1;
    const hasta = Math.min(pagina * porPagina, total);
    const cifra = (n) => n.toLocaleString('es-BO');
    const nombre = Array.isArray(unidad) ? unidad[total === 1 ? 0 : 1] : unidad;

    return (
        <nav
            className={`flex min-h-14 flex-wrap items-center justify-between gap-3 px-4 py-2 text-sm text-slate-600 ${className}`}
            aria-label="Paginación"
        >
            <span>
                {ultima > 1 ? `${cifra(desde)}–${cifra(hasta)} de ${cifra(total)}` : cifra(total)}{' '}
                {nombre}
            </span>
            {ultima > 1 && (
                <span className="flex items-center gap-2">
                    <button
                        type="button"
                        className={BOTON}
                        disabled={pagina === 1}
                        onClick={() => onCambiar(pagina - 1)}
                        aria-label="Página anterior"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <span className="tabular-nums">
                        {pagina} / {ultima}
                    </span>
                    <button
                        type="button"
                        className={BOTON}
                        disabled={pagina === ultima}
                        onClick={() => onCambiar(pagina + 1)}
                        aria-label="Página siguiente"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </span>
            )}
        </nav>
    );
}

Paginacion.propTypes = {
    total: PropTypes.number.isRequired,
    pagina: PropTypes.number.isRequired,
    porPagina: PropTypes.number.isRequired,
    onCambiar: PropTypes.func.isRequired,
    unidad: PropTypes.oneOfType([PropTypes.string, PropTypes.arrayOf(PropTypes.string)]),
    className: PropTypes.string,
};
