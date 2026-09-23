import PropTypes from 'prop-types';

const pestanas = [
    { clave: 'padron', texto: 'Registro de estudiantes' },
    { clave: 'cargaMasiva', texto: 'Carga masiva' },
];

export default function PestanasPadron({ activa, onNavigate }) {
    return (
        <nav
            aria-label="Secciones del padrón"
            className="flex gap-1 border-b border-slate-200 bg-white px-4 md:px-6"
        >
            {pestanas.map(({ clave, texto }) => (
                <button
                    key={clave}
                    type="button"
                    aria-current={activa === clave ? 'page' : undefined}
                    className={`-mb-px border-b-2 px-3 py-3 text-sm font-medium ${
                        activa === clave
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                    }`}
                    onClick={() => activa !== clave && onNavigate?.(clave)}
                >
                    {texto}
                </button>
            ))}
        </nav>
    );
}

PestanasPadron.propTypes = {
    activa: PropTypes.oneOf(['padron', 'cargaMasiva']).isRequired,
    onNavigate: PropTypes.func,
};
