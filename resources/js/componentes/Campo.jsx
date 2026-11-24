import PropTypes from 'prop-types';
import { Eye, EyeOff } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { FORMATOS } from '../utiles/formatos';

// Campo de formulario. Para todas las pantallas a la vez:
//  - `requerido` pone el asterisco: se sabe qué es obligatorio antes de enviar.
//  - `validar` corre mientras se escribe, no recién al enviar.
//  - `sufijo` fija lo que no hace falta teclear (el dominio del correo).
//  - `icono` (un componente de lucide) va al inicio del campo.
//  - `iconoEtiqueta` (otro de lucide) acompaña a la etiqueta.
//  - `error` muestra el mensaje del servidor (validación 422) bajo el campo.
//  - Con `type="password"` aparece el ojito para ver u ocultar lo escrito.
//  - `formato` ('letras', 'digitos', 'documento', 'usuario') encarrila lo que
//    se escribe o se pega: lo que no corresponde no entra y un aviso corto dice
//    qué se admite. Ver utiles/formatos.js.
//  - El mensaje queda ligado al campo (`aria-describedby`) y se anuncia.
export default function Campo({
    etiqueta,
    error,
    requerido,
    validar,
    sufijo,
    icono: Icono = null,
    iconoEtiqueta: IconoEtiqueta = null,
    ayuda,
    formato,
    className = '',
    ...props
}) {
    const id = useId();
    const [valor, setValor] = useState(props.defaultValue ?? '');
    const [tocado, setTocado] = useState(false);
    const [visible, setVisible] = useState(false);
    const esContrasena = props.type === 'password';
    const reglaDeFormato = formato ? FORMATOS[formato] : null;
    const [descartado, setDescartado] = useState(false);
    const reloj = useRef(null);
    useEffect(() => () => clearTimeout(reloj.current), []);
    // Si React reutiliza este campo para otro (otro formulario que alterna en
    // la misma posición), no hereda el «tocado» del anterior: nacería
    // diciendo «Obligatorio». Solo con etiquetas de texto, que son estables.
    const etiquetaDeTexto = typeof etiqueta === 'string' ? etiqueta : null;
    const anterior = useRef(etiquetaDeTexto);
    useEffect(() => {
        if (anterior.current !== etiquetaDeTexto) {
            anterior.current = etiquetaDeTexto;
            setTocado(false);
        }
    }, [etiquetaDeTexto]);

    const actual = props.value ?? valor;
    const vacio = requerido && String(actual).trim() === '';
    const errorVivo =
        error ?? (tocado ? (vacio ? 'Obligatorio' : validar ? validar(actual) : null) : null);
    // Lo descartado se avisa un instante, salvo que haya un error que decir.
    const avisoDeFormato = !errorVivo && descartado ? reglaDeFormato?.aviso : null;

    return (
        <label className={`block ${className}`}>
            {etiqueta && (
                <span className="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-slate-700">
                    {IconoEtiqueta && (
                        <IconoEtiqueta
                            className="h-4 w-4 shrink-0 text-primary-600"
                            aria-hidden="true"
                        />
                    )}
                    {etiqueta}
                    {requerido && (
                        <span className="-ml-1 text-danger-600" aria-hidden="true">
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
                {Icono && (
                    <span className="flex shrink-0 items-center pl-3.5 text-slate-500">
                        <Icono className="h-4 w-4" aria-hidden="true" />
                    </span>
                )}
                <input
                    className="min-h-11 min-w-0 flex-1 bg-transparent px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none"
                    aria-required={requerido || undefined}
                    aria-invalid={errorVivo ? 'true' : undefined}
                    aria-describedby={errorVivo ? `${id}-error` : ayuda ? `${id}-ayuda` : undefined}
                    {...(reglaDeFormato?.propiedades ?? {})}
                    {...props}
                    type={esContrasena && visible ? 'text' : props.type}
                    onChange={(e) => {
                        if (reglaDeFormato) {
                            const campo = e.target;
                            const limpio = reglaDeFormato.limpiar(campo.value);
                            if (limpio !== campo.value) {
                                // Conserva el cursor donde estaba, descontando lo quitado.
                                const quitado = campo.value.length - limpio.length;
                                const cursor = Math.max(0, (campo.selectionStart ?? 0) - quitado);
                                campo.value = limpio;
                                campo.setSelectionRange?.(cursor, cursor);
                                setDescartado(true);
                                clearTimeout(reloj.current);
                                reloj.current = setTimeout(() => setDescartado(false), 2000);
                            }
                        }
                        setValor(e.target.value);
                        setTocado(true);
                        props.onChange?.(e);
                    }}
                    onBlur={(e) => {
                        // Pasar por un campo vacío no es un error: «Obligatorio»
                        // sale al escribir y borrar, o al enviar.
                        if (String(e.target.value).trim() !== '') setTocado(true);
                        props.onBlur?.(e);
                    }}
                />
                {esContrasena && (
                    <button
                        type="button"
                        className="flex min-h-11 w-11 shrink-0 items-center justify-center text-slate-500 hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600"
                        aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                        aria-pressed={visible}
                        onClick={() => setVisible((v) => !v)}
                    >
                        {visible ? (
                            <EyeOff className="h-4 w-4" aria-hidden="true" />
                        ) : (
                            <Eye className="h-4 w-4" aria-hidden="true" />
                        )}
                    </button>
                )}
                {sufijo && (
                    <span className="flex shrink-0 items-center border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">
                        {sufijo}
                    </span>
                )}
            </span>
            {errorVivo ? (
                <span
                    id={`${id}-error`}
                    aria-live="polite"
                    className="mt-1.5 block text-sm text-danger-600"
                >
                    {errorVivo}
                </span>
            ) : avisoDeFormato ? (
                <span aria-live="polite" className="mt-1.5 block text-xs text-warning-700">
                    {avisoDeFormato}
                </span>
            ) : (
                ayuda && (
                    <span id={`${id}-ayuda`} className="mt-1.5 block text-xs text-slate-500">
                        {ayuda}
                    </span>
                )
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
    icono: PropTypes.elementType,
    iconoEtiqueta: PropTypes.elementType,
    ayuda: PropTypes.node,
    formato: PropTypes.oneOf(['letras', 'digitos', 'documento', 'usuario']),
    className: PropTypes.string,
    type: PropTypes.string,
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    defaultValue: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    onChange: PropTypes.func,
    onBlur: PropTypes.func,
};
