import PropTypes from 'prop-types';

// El escudo de la UMSS. Se sirve siempre desde la misma ruta: para cambiarlo
// por otro de mejor calidad basta reemplazar el archivo. El tamaño lo da el
// alto (`className`); el ancho sigue a la imagen.
export const RUTA_ESCUDO = '/images/escudo-umss.png';

export default function Escudo({ className = 'h-10', alt = 'Escudo de la UMSS' }) {
    return (
        <img
            src={RUTA_ESCUDO}
            alt={alt}
            className={`w-auto shrink-0 object-contain ${className}`}
            draggable={false}
        />
    );
}

Escudo.propTypes = { className: PropTypes.string, alt: PropTypes.string };

// Escudo, «UMSS» y «Control de exámenes» debajo, en versalitas.
export function Marca({ className = '', escudo = 'h-10' }) {
    return (
        <span className={`flex min-w-0 items-center gap-2.5 ${className}`}>
            <Escudo className={escudo} />
            <span className="min-w-0 leading-none">
                <span className="block text-lg font-extrabold tracking-wide text-umss-marino">
                    UMSS
                </span>
                <span className="mt-0.5 block truncate text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                    Control de exámenes
                </span>
            </span>
        </span>
    );
}

Marca.propTypes = { className: PropTypes.string, escudo: PropTypes.string };
