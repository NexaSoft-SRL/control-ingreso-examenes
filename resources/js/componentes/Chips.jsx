import PropTypes from 'prop-types';
// Filtro de una sola elección en forma de fichas. Cada opción puede llevar
// un punto de color y un conteo. `valor` nulo es la opción «todas». Con más
// de `maximo` opciones las fichas no caben: pasa a ser una lista desplegable.
export default function Chips({ opciones, valor, onCambiar, etiqueta, extra, maximo = 6 }) {
    if (opciones.length > maximo) {
        const indice = opciones.findIndex((o) => o.valor === valor);
        const color = opciones[indice]?.color;
        return (
            <div className="flex flex-wrap items-center gap-2">
                <label className="flex min-h-10 min-w-0 max-w-full items-center gap-2 rounded-full border border-slate-300 bg-white pl-3.5 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/40">
                    {color && (
                        <span
                            className="h-2.5 w-2.5 shrink-0 rounded-full"
                            style={{ backgroundColor: color }}
                        />
                    )}
                    <select
                        className="min-h-10 min-w-0 max-w-full truncate bg-transparent py-2 text-sm font-medium text-slate-800 focus:outline-none"
                        aria-label={etiqueta}
                        value={indice}
                        onChange={(e) => onCambiar(opciones[Number(e.target.value)].valor)}
                    >
                        {opciones.map((o, i) => (
                            <option key={o.etiqueta} value={i}>
                                {o.etiqueta}
                                {o.conteo !== undefined ? ` (${o.conteo})` : ''}
                            </option>
                        ))}
                    </select>
                </label>
                {extra}
            </div>
        );
    }

    return (
        <div
            className="-mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0"
            role="group"
            aria-label={etiqueta}
        >
            {opciones.map((o) => {
                const activo = valor === o.valor;
                return (
                    <button
                        key={o.etiqueta}
                        type="button"
                        aria-pressed={activo}
                        onClick={() => onCambiar(o.valor)}
                        className={`inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border px-3.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 ${
                            activo
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                        }`}
                    >
                        {o.color && (
                            <span
                                className="h-2.5 w-2.5 rounded-full ring-1 ring-white/60"
                                style={{ backgroundColor: o.color }}
                            />
                        )}
                        {o.etiqueta}
                        {o.conteo !== undefined && (
                            <span className={activo ? 'text-slate-300' : 'text-slate-500'}>
                                {o.conteo}
                            </span>
                        )}
                    </button>
                );
            })}
            {extra}
        </div>
    );
}

Chips.propTypes = {
    opciones: PropTypes.arrayOf(
        PropTypes.shape({
            valor: PropTypes.any,
            etiqueta: PropTypes.string.isRequired,
            color: PropTypes.string,
            conteo: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
        })
    ).isRequired,
    valor: PropTypes.any,
    onCambiar: PropTypes.func.isRequired,
    etiqueta: PropTypes.string,
    extra: PropTypes.node,
    maximo: PropTypes.number,
};
