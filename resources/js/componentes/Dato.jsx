import PropTypes from 'prop-types';

// Cifra con su etiqueta. Con `onClick` es un botón; `tono` la destaca;
// `icono` la acompaña con el color del tono.
const TONOS = {
    neutro: 'border-slate-200 bg-white',
    advertencia: 'border-warning-200 bg-warning-50',
    exito: 'border-success-200 bg-success-50',
    primario: 'border-primary-200 bg-primary-50',
    peligro: 'border-danger-200 bg-danger-50',
};

const ICONOS = {
    neutro: 'bg-slate-100 text-slate-500',
    advertencia: 'bg-warning-100 text-warning-800',
    exito: 'bg-success-100 text-success-700',
    primario: 'bg-primary-100 text-primary-700',
    peligro: 'bg-danger-100 text-danger-700',
};

export default function Dato({ etiqueta, valor, icono: Icono, tono = 'neutro', onClick }) {
    const clases = `block w-full rounded-xl border p-4 text-left ${TONOS[tono]}`;
    const contenido = (
        <span className="flex items-start justify-between gap-2">
            <span className="min-w-0">
                <span className="block text-xs font-medium text-slate-600">{etiqueta}</span>
                <span className="block text-2xl font-semibold leading-tight text-slate-900">
                    {valor}
                </span>
            </span>
            {Icono && (
                <span
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${ICONOS[tono]}`}
                >
                    <Icono className="h-[1.125rem] w-[1.125rem]" aria-hidden="true" />
                </span>
            )}
        </span>
    );

    return onClick ? (
        <button
            type="button"
            onClick={onClick}
            className={`${clases} hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600`}
        >
            {contenido}
        </button>
    ) : (
        <div className={clases}>{contenido}</div>
    );
}

Dato.propTypes = {
    etiqueta: PropTypes.node.isRequired,
    valor: PropTypes.node,
    icono: PropTypes.elementType,
    tono: PropTypes.oneOf(Object.keys(TONOS)),
    onClick: PropTypes.func,
};
