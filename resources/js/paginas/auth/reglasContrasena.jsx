import PropTypes from 'prop-types';
import { Check, X } from 'lucide-react';

// La regla de la contraseña propia, la misma del servidor (ReglasContrasena):
// 10 caracteres, mayúscula, minúscula y número.
export const REGLAS = [
    ['10 caracteres o más', (v) => v.length >= 10],
    ['Mayúscula y minúscula', (v) => /[a-z]/.test(v) && /[A-Z]/.test(v)],
    ['Número', (v) => /\d/.test(v)],
];

export function cumpleReglas(clave) {
    return REGLAS.every(([, regla]) => regla(clave));
}

// Las reglas se van cumpliendo a la vista mientras se escribe.
export function ListaReglas({ clave }) {
    return (
        <ul className="space-y-1 text-sm">
            {REGLAS.map(([texto, regla]) => {
                const ok = regla(clave);
                return (
                    <li
                        key={texto}
                        data-cumplida={ok}
                        className={`flex items-center gap-2 ${ok ? 'text-success-700' : 'text-slate-600'}`}
                    >
                        {ok ? (
                            <Check className="h-4 w-4" strokeWidth={3} />
                        ) : (
                            <X className="h-4 w-4" />
                        )}
                        {texto}
                    </li>
                );
            })}
        </ul>
    );
}

ListaReglas.propTypes = { clave: PropTypes.string.isRequired };
