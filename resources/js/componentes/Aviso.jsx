import { useCallback, useEffect, useState } from 'react';
import { Check, X } from 'lucide-react';

const TONOS = {
    exito: 'bg-slate-900 text-white',
    error: 'bg-danger-600 text-white',
};

// Confirmación breve del resultado de una acción («Reporte descargado»).
// `const [aviso, avisar] = useAviso()`; se dibuja con `{aviso}` y se lanza
// con `avisar('Texto')` o `avisar('Texto', 'error')`. Se cierra solo.
export function useAviso(duracion = 3200) {
    const [estado, setEstado] = useState(null);
    const avisar = useCallback(
        (texto, tono = 'exito') => setEstado({ texto, tono, id: Date.now() }),
        []
    );

    useEffect(() => {
        if (!estado) return undefined;
        const reloj = setTimeout(() => setEstado(null), duracion);
        return () => clearTimeout(reloj);
    }, [estado, duracion]);

    const aviso = estado && (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-none fixed inset-x-0 bottom-24 z-50 flex justify-center px-4 md:bottom-8"
        >
            <div
                className={`pointer-events-auto flex max-w-full items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium shadow-lg ${TONOS[estado.tono] ?? TONOS.exito}`}
            >
                {estado.tono === 'error' ? (
                    <X className="h-4 w-4 shrink-0" strokeWidth={3} />
                ) : (
                    <Check className="h-4 w-4 shrink-0" strokeWidth={3} />
                )}
                <span className="min-w-0">{estado.texto}</span>
            </div>
        </div>
    );

    return [aviso, avisar];
}
