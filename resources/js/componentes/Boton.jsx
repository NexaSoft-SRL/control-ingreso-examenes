import PropTypes from 'prop-types';

const VARIANTES = {
    primario: 'bg-primary-600 text-white hover:bg-primary-700 focus-visible:outline-primary-600',
    secundario:
        'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus-visible:outline-primary-600',
    peligro: 'bg-danger-600 text-white hover:bg-danger-700 focus-visible:outline-danger-600',
    fantasma: 'text-slate-700 hover:bg-slate-100 focus-visible:outline-primary-600',
    enlace: 'text-primary-700 hover:bg-primary-50 focus-visible:outline-primary-600',
    peligroContorno:
        'bg-white text-danger-700 border border-danger-600 hover:bg-danger-50 focus-visible:outline-danger-600',
};

const TAMANOS = {
    chico: 'min-h-9 px-3 py-1.5 text-sm',
    normal: 'min-h-11 px-4 py-2.5 text-sm',
    grande: 'px-6 py-4 text-base',
};

export default function Boton({
    variante = 'primario',
    tamano = 'normal',
    className = '',
    ...props
}) {
    return (
        <button
            className={`inline-flex items-center justify-center gap-2 rounded-lg font-medium
                transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2
                disabled:opacity-50 disabled:pointer-events-none
                ${VARIANTES[variante]} ${TAMANOS[tamano]} ${className}`}
            {...props}
        />
    );
}

Boton.propTypes = {
    variante: PropTypes.oneOf(Object.keys(VARIANTES)),
    tamano: PropTypes.oneOf(Object.keys(TAMANOS)),
    className: PropTypes.string,
};
