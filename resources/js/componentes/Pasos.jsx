import PropTypes from 'prop-types';
import { Check } from 'lucide-react';

// Indicador de pasos de un proceso guiado: lo hecho, lo actual y lo que
// falta. Se puede volver a un paso anterior, no saltar adelante.
export default function Pasos({ pasos, actual, onIr }) {
    return (
        <ol className="flex flex-wrap items-center gap-x-2 gap-y-3">
            {pasos.map((titulo, i) => {
                const hecho = i < actual;
                const activo = i === actual;
                return (
                    <li key={titulo} className="flex items-center gap-2">
                        <button
                            type="button"
                            disabled={!hecho}
                            onClick={() => onIr?.(i)}
                            className={`flex min-h-10 items-center gap-2 rounded-full py-1 pl-1 pr-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 ${
                                activo
                                    ? 'bg-primary-600 text-white'
                                    : hecho
                                      ? 'bg-success-100 text-success-700 hover:bg-success-200'
                                      : 'bg-slate-200 text-slate-700'
                            }`}
                        >
                            <span
                                className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold ${
                                    activo
                                        ? 'bg-white text-primary-700'
                                        : hecho
                                          ? 'bg-success-600 text-white'
                                          : 'bg-white text-slate-700'
                                }`}
                            >
                                {hecho ? <Check className="h-3.5 w-3.5" strokeWidth={3} /> : i + 1}
                            </span>
                            {titulo}
                        </button>
                        {i < pasos.length - 1 && (
                            <span className="hidden h-px w-6 bg-slate-300 sm:block" />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

Pasos.propTypes = {
    pasos: PropTypes.arrayOf(PropTypes.string).isRequired,
    actual: PropTypes.number.isRequired,
    onIr: PropTypes.func,
};
