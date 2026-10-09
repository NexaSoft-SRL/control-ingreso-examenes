import PropTypes from 'prop-types';
import { Search, X } from 'lucide-react';

// Caja de búsqueda de una lista: lupa, texto y botón para limpiar.
// `onCambiar` recibe el texto.
export default function Buscador({
    valor,
    onCambiar,
    placeholder = 'Buscar',
    etiqueta = placeholder,
    className = '',
}) {
    return (
        <label
            className={`flex min-h-11 min-w-0 items-center gap-2 rounded-lg border border-slate-300 bg-white pl-3 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/40 ${className}`}
        >
            <Search className="h-4 w-4 shrink-0 text-slate-500" />
            <input
                type="text"
                inputMode="search"
                enterKeyHint="search"
                value={valor}
                onChange={(e) => onCambiar(e.target.value)}
                placeholder={placeholder}
                aria-label={etiqueta}
                className="min-w-0 flex-1 bg-transparent py-2.5 text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none"
            />
            {valor ? (
                <button
                    type="button"
                    aria-label="Limpiar búsqueda"
                    onClick={() => onCambiar('')}
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                >
                    <X className="h-4 w-4" />
                </button>
            ) : (
                <span className="w-3 shrink-0" />
            )}
        </label>
    );
}

Buscador.propTypes = {
    valor: PropTypes.string.isRequired,
    onCambiar: PropTypes.func.isRequired,
    placeholder: PropTypes.string,
    etiqueta: PropTypes.string,
    className: PropTypes.string,
};
