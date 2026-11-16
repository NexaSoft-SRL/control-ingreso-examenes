import PropTypes from 'prop-types';
import { Children, Fragment, isValidElement, useId } from 'react';
import { Check, ChevronDown } from 'lucide-react';
import usarDesplegable from './usarDesplegable';

// Lista desplegable y área de texto con el mismo aspecto que Campo.
//
// La lista es propia, no la del navegador, pero se usa como una nativa:
// recibe `<option>` (y `<optgroup>`) como hijos, `value` y `onChange`, que
// se llama con `{ target: { value, name } }`. Se maneja con el teclado
// (flechas, Inicio, Fin, Enter, Escape, y una letra salta a la opción que
// empieza así).
const BASE =
    'flex min-h-11 w-full items-center gap-2 rounded-lg border bg-white pl-3.5 pr-3 text-left text-sm text-slate-800 focus:outline-none focus-visible:border-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/40 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';

// El área de texto conserva el aspecto de siempre.
const BASE_AREA =
    'w-full rounded-lg border border-slate-300 bg-white pl-3.5 text-sm text-slate-800 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:bg-slate-100';

function Etiqueta({ id, etiqueta, requerido }) {
    if (!etiqueta) return null;
    return (
        <span id={id} className="mb-1.5 block text-sm font-medium text-slate-700">
            {etiqueta}
            {requerido && (
                <span className="ml-0.5 text-danger-600" aria-hidden="true">
                    *
                </span>
            )}
        </span>
    );
}

// El punto de color de una opción (`data-color`), como el de las fichas.
function Punto({ color }) {
    return (
        <span
            className="h-2.5 w-2.5 shrink-0 rounded-full"
            style={{ backgroundColor: color }}
            aria-hidden="true"
        />
    );
}

Punto.propTypes = { color: PropTypes.string.isRequired };

const textoDe = (hijos) =>
    Children.toArray(hijos)
        .map((hijo) => (typeof hijo === 'string' || typeof hijo === 'number' ? String(hijo) : ''))
        .join('');

// Las `<option>` de los hijos, en orden, cada una con su grupo si está dentro
// de un `<optgroup>`.
function leerOpciones(hijos, grupo = null, salida = []) {
    Children.forEach(hijos, (hijo) => {
        if (!isValidElement(hijo)) return;
        if (hijo.type === Fragment) {
            leerOpciones(hijo.props.children, grupo, salida);
        } else if (hijo.type === 'optgroup') {
            leerOpciones(hijo.props.children, hijo.props.label, salida);
        } else if (hijo.type === 'option') {
            const texto = textoDe(hijo.props.children);
            salida.push({
                valor: String(hijo.props.value ?? texto),
                texto,
                color: hijo.props['data-color'],
                grupo,
                desactivada: Boolean(hijo.props.disabled),
            });
        }
    });
    return salida;
}

// `icono` (un componente de lucide) va al inicio de la lista.
export default function Seleccion({
    etiqueta,
    requerido,
    icono: Icono = null,
    className = '',
    children,
    value,
    onChange,
    disabled = false,
    name,
    'aria-label': ariaLabel,
}) {
    const idEtiqueta = useId();
    const opciones = leerOpciones(children);
    const elegida = opciones.find((o) => o.valor === String(value ?? '')) ?? opciones[0];
    const d = usarDesplegable({
        opciones,
        elegida,
        deshabilitada: disabled,
        alElegir: (o) => {
            if (!o.desactivada) onChange?.({ target: { value: o.valor, name } });
        },
        texto: (o) => o.texto,
    });

    // Las opciones seguidas de un mismo grupo van juntas.
    const bloques = opciones.reduce((acumulado, o) => {
        const ultimo = acumulado[acumulado.length - 1];
        if (ultimo && ultimo[0].grupo === o.grupo) ultimo.push(o);
        else acumulado.push([o]);
        return acumulado;
    }, []);

    return (
        <div ref={d.caja} className={`relative ${className}`}>
            <Etiqueta id={idEtiqueta} etiqueta={etiqueta} requerido={requerido} />
            <button
                type="button"
                role="combobox"
                data-valor={elegida?.valor}
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={d.abierta}
                aria-required={requerido || undefined}
                aria-label={etiqueta ? undefined : ariaLabel}
                aria-labelledby={etiqueta ? idEtiqueta : undefined}
                aria-controls={d.abierta ? `${d.id}-lista` : undefined}
                aria-activedescendant={d.abierta ? d.idDe(d.activa) : undefined}
                onClick={d.alternar}
                onKeyDown={d.teclas}
                className={`${BASE} ${
                    d.abierta ? 'border-primary-500 ring-2 ring-primary-500/40' : 'border-slate-300'
                }`}
            >
                {Icono && <Icono className="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />}
                {elegida?.color && <Punto color={elegida.color} />}
                <span className="min-w-0 flex-1 truncate">{elegida?.texto}</span>
                <ChevronDown
                    className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${d.abierta ? 'rotate-180' : ''}`}
                    aria-hidden="true"
                />
            </button>

            {d.abierta && (
                <ul
                    ref={d.lista}
                    id={`${d.id}-lista`}
                    role="listbox"
                    aria-label={`Opciones de ${etiqueta ?? ariaLabel}`}
                    className="absolute z-30 mt-1.5 max-h-72 w-max min-w-full max-w-[min(24rem,calc(100vw-2rem))] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-primary-900/10"
                >
                    {bloques.map((bloque) => {
                        const filas = bloque.map((o) => {
                            const i = opciones.indexOf(o);
                            const marcada = o === elegida;
                            return (
                                <li
                                    key={`${o.grupo ?? ''}|${o.valor}`}
                                    id={d.idDe(i)}
                                    role="option"
                                    data-valor={o.valor}
                                    aria-selected={marcada}
                                    aria-disabled={o.desactivada || undefined}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onMouseEnter={() => d.setActiva(i)}
                                    onClick={() => d.elegir(o)}
                                    className={`flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm ${
                                        o.desactivada
                                            ? 'cursor-not-allowed text-slate-400'
                                            : `cursor-pointer ${
                                                  marcada
                                                      ? 'bg-primary-50 font-semibold text-primary-800'
                                                      : i === d.activa
                                                        ? 'bg-slate-100 text-slate-900'
                                                        : 'text-slate-800'
                                              }`
                                    }`}
                                >
                                    {o.color && <Punto color={o.color} />}
                                    <span className="min-w-0 flex-1 truncate">{o.texto}</span>
                                    <Check
                                        className={`h-4 w-4 shrink-0 text-primary-600 ${marcada ? '' : 'invisible'}`}
                                        aria-hidden="true"
                                    />
                                </li>
                            );
                        });
                        const grupo = bloque[0].grupo;
                        return grupo ? (
                            <li key={`grupo-${grupo}`} role="presentation">
                                <ul role="group" aria-label={grupo}>
                                    <li
                                        role="presentation"
                                        className="mt-1 border-t border-slate-100 px-2.5 pb-1 pt-2.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        {grupo}
                                    </li>
                                    {filas}
                                </ul>
                            </li>
                        ) : (
                            filas
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

Seleccion.propTypes = {
    etiqueta: PropTypes.node,
    requerido: PropTypes.bool,
    icono: PropTypes.elementType,
    className: PropTypes.string,
    children: PropTypes.node,
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    onChange: PropTypes.func,
    disabled: PropTypes.bool,
    name: PropTypes.string,
    'aria-label': PropTypes.string,
};

export function AreaTexto({ etiqueta, requerido, error, pie, className = '', ...props }) {
    return (
        <label className={`block ${className}`}>
            <Etiqueta etiqueta={etiqueta} requerido={requerido} />
            <textarea
                className={`${BASE_AREA} py-2.5 ${error ? 'border-danger-600' : ''}`}
                aria-required={requerido || undefined}
                aria-invalid={error ? 'true' : undefined}
                {...props}
            />
            {(error || pie) && (
                <span className="mt-1 flex justify-between gap-3 text-xs">
                    <span className="text-danger-600">{error}</span>
                    <span className="shrink-0 text-slate-600">{pie}</span>
                </span>
            )}
        </label>
    );
}

Etiqueta.propTypes = {
    id: PropTypes.string,
    etiqueta: PropTypes.node,
    requerido: PropTypes.bool,
};

AreaTexto.propTypes = {
    etiqueta: PropTypes.node,
    requerido: PropTypes.bool,
    error: PropTypes.string,
    pie: PropTypes.node,
    className: PropTypes.string,
};
