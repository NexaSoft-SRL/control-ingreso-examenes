import PropTypes from 'prop-types';
import Seleccion from './Seleccion';
// Filtro de una sola elección en forma de fichas. Cada opción puede llevar
// un punto de color y un conteo. `valor` nulo es la opción «todas». Con más
// de `maximo` opciones las fichas no caben: pasa a ser una lista desplegable.
export default function Chips({ opciones, valor, onCambiar, etiqueta, extra, maximo = 6 }) {
    if (opciones.length > maximo) {
        const indice = opciones.findIndex((o) => o.valor === valor);
        return (
            <div className="flex flex-wrap items-center gap-2">
                <Seleccion
                    aria-label={etiqueta}
                    className="min-w-0 max-w-full"
                    value={String(indice)}
                    onChange={(e) => onCambiar(opciones[Number(e.target.value)].valor)}
                >
                    {opciones.map((o, i) => (
                        <option key={o.etiqueta} value={i} data-color={o.color}>
                            {o.etiqueta}
                            {o.conteo !== undefined ? ` (${o.conteo})` : ''}
                        </option>
                    ))}
                </Seleccion>
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
                                ? 'border-umss-marino bg-umss-marino text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:border-primary-300 hover:bg-primary-50'
                        }`}
                    >
                        {o.icono ??
                            (o.color && (
                                <span
                                    className="h-2.5 w-2.5 rounded-full ring-1 ring-white/60"
                                    style={{ backgroundColor: o.color }}
                                />
                            ))}
                        {o.etiqueta}
                        {o.conteo !== undefined && (
                            <span className={activo ? 'text-primary-200' : 'text-slate-500'}>
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
