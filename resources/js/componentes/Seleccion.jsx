import PropTypes from 'prop-types';

// Lista desplegable y área de texto con el mismo aspecto que Campo.
const BASE =
    'w-full rounded-lg border border-slate-300 bg-white pl-3.5 text-sm text-slate-800 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:bg-slate-100';

function Etiqueta({ etiqueta, requerido }) {
    if (!etiqueta) return null;
    return (
        <span className="mb-1.5 block text-sm font-medium text-slate-700">
            {etiqueta}
            {requerido && (
                <span className="ml-0.5 text-danger-600" aria-hidden="true">
                    *
                </span>
            )}
        </span>
    );
}

// `icono` (un componente de lucide) va al inicio de la lista.
export default function Seleccion({
    etiqueta,
    requerido,
    icono: Icono = null,
    className = '',
    children,
    ...props
}) {
    return (
        <label className={`block ${className}`}>
            <Etiqueta etiqueta={etiqueta} requerido={requerido} />
            <span className="relative block">
                {Icono && (
                    <Icono
                        className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"
                        aria-hidden="true"
                    />
                )}
                <select
                    className={`${BASE} min-h-11 ${Icono ? 'pl-10' : ''}`}
                    aria-required={requerido || undefined}
                    {...props}
                >
                    {children}
                </select>
            </span>
        </label>
    );
}

export function AreaTexto({ etiqueta, requerido, error, pie, className = '', ...props }) {
    return (
        <label className={`block ${className}`}>
            <Etiqueta etiqueta={etiqueta} requerido={requerido} />
            <textarea
                className={`${BASE} py-2.5 ${error ? 'border-danger-600' : ''}`}
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

Etiqueta.propTypes = { etiqueta: PropTypes.node, requerido: PropTypes.bool };

Seleccion.propTypes = {
    etiqueta: PropTypes.node,
    requerido: PropTypes.bool,
    icono: PropTypes.elementType,
    className: PropTypes.string,
    children: PropTypes.node,
};

AreaTexto.propTypes = {
    etiqueta: PropTypes.node,
    requerido: PropTypes.bool,
    error: PropTypes.string,
    pie: PropTypes.node,
    className: PropTypes.string,
};
