import PropTypes from 'prop-types';
import { useState } from 'react';

// Campo de formulario. Para todas las pantallas a la vez:
//  - `requerido` pone el asterisco: se sabe qué es obligatorio antes de enviar.
//  - `validar` corre mientras se escribe, no recién al enviar.
//  - `sufijo` fija lo que no hace falta teclear (el dominio del correo).
//  - `error` muestra el mensaje del servidor (validación 422) bajo el campo.
export default function Campo({
    etiqueta,
    error,
    requerido,
    validar,
    sufijo,
    ayuda,
    className = '',
    ...props
}) {
    const [valor, setValor] = useState(props.defaultValue ?? '');
    const [tocado, setTocado] = useState(false);

    const actual = props.value ?? valor;
    const vacio = requerido && String(actual).trim() === '';
    const errorVivo =
        error ?? (tocado ? (vacio ? 'Obligatorio' : validar ? validar(actual) : null) : null);

    return (
        <label className={`block ${className}`}>
            {etiqueta && (
                <span className="mb-1.5 block text-sm font-medium text-slate-700">
                    {etiqueta}
                    {requerido && (
                        <span className="ml-0.5 text-danger-600" aria-hidden="true">
                            *
                        </span>
                    )}
                </span>
            )}
            <span
                className={`flex overflow-hidden rounded-lg border bg-white focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/40 ${
                    errorVivo ? 'border-danger-600' : 'border-slate-300'
                }`}
            >
                <input
                    className="min-h-11 min-w-0 flex-1 bg-transparent px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none"
                    aria-required={requerido || undefined}
                    aria-invalid={errorVivo ? 'true' : undefined}
                    {...props}
                    onChange={(e) => {
                        setValor(e.target.value);
                        setTocado(true);
                        props.onChange?.(e);
                    }}
                    onBlur={(e) => {
                        setTocado(true);
                        props.onBlur?.(e);
                    }}
                />
                {sufijo && (
                    <span className="flex shrink-0 items-center border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">
                        {sufijo}
                    </span>
                )}
            </span>
            {errorVivo ? (
                <span className="mt-1.5 block text-sm text-danger-600">{errorVivo}</span>
            ) : (
                ayuda && <span className="mt-1.5 block text-xs text-slate-500">{ayuda}</span>
            )}
        </label>
    );
}

Campo.propTypes = {
    etiqueta: PropTypes.node,
    error: PropTypes.string,
    requerido: PropTypes.bool,
    validar: PropTypes.func,
    sufijo: PropTypes.node,
    ayuda: PropTypes.node,
    className: PropTypes.string,
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    defaultValue: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    onChange: PropTypes.func,
    onBlur: PropTypes.func,
};
