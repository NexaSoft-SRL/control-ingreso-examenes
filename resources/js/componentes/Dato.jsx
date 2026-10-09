import PropTypes from 'prop-types';

// Cifra con su etiqueta. Con `onClick` es un botón; `tono` la destaca.
const TONOS = {
    neutro: 'border-slate-200 bg-white',
    advertencia: 'border-warning-200 bg-warning-50',
    exito: 'border-success-200 bg-success-50',
    primario: 'border-primary-200 bg-primary-50',
    peligro: 'border-danger-200 bg-danger-50',
};

export default function Dato({ etiqueta, valor, tono = 'neutro', onClick }) {
    const clases = `block w-full rounded-xl border p-4 text-left ${TONOS[tono]}`;
    const contenido = (
        <>
            <span className="block text-xs font-medium text-slate-600">{etiqueta}</span>
            <span className="block text-2xl font-semibold leading-tight text-slate-900">
                {valor}
            </span>
        </>
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
    tono: PropTypes.oneOf(Object.keys(TONOS)),
    onClick: PropTypes.func,
};
