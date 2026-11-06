import { useEffect, useId, useState } from 'react';
import PropTypes from 'prop-types';
import { Camera, Loader2, X } from 'lucide-react';
import { dimensionesDe, ImagenNoValida, reducirImagen } from '../utiles/imagen';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Una foto tomada con la cámara del teléfono o elegida de un archivo, con
// su vista previa. Se valida al elegirla (tipo, tamaño y, con `minimo`, el
// lado menor en píxeles) y se reduce antes de entregarla:
// `onCambiar(archivo | null)` recibe ya el JPEG reducido.
export default function CampoImagen({
    etiqueta,
    archivo,
    onCambiar,
    error,
    camara = 'environment',
    minimo = 0,
    maximoMB,
}) {
    const id = useId();
    const [vista, setVista] = useState(null);
    const [propio, setPropio] = useState(null);
    const [procesando, setProcesando] = useState(false);
    const mensaje = error ?? propio;

    async function elegir(elegido) {
        setPropio(null);
        if (!elegido) return;
        setProcesando(true);
        try {
            const limiteMB = maximoMB ?? null;
            if (limiteMB !== null && elegido.size > limiteMB * 1024 * 1024) {
                throw new ImagenNoValida(`Máximo ${limiteMB} MB`);
            }
            if (minimo > 0) {
                const medidas = await dimensionesDe(elegido);
                if (medidas && Math.min(medidas.ancho, medidas.alto) < minimo) {
                    throw new ImagenNoValida(`Resolución insuficiente · mínimo ${minimo} px`);
                }
            }
            onCambiar(await reducirImagen(elegido));
        } catch (fallo) {
            onCambiar(null);
            setPropio(
                fallo instanceof ImagenNoValida ? fallo.message : 'La imagen no se puede leer'
            );
        } finally {
            setProcesando(false);
        }
    }

    useEffect(() => {
        if (!archivo) {
            setVista(null);
            return undefined;
        }
        const url = URL.createObjectURL(archivo);
        setVista(url);
        return () => URL.revokeObjectURL(url);
    }, [archivo]);

    return (
        <div>
            <span className="mb-1.5 block text-sm font-medium text-slate-700">{etiqueta}</span>
            {archivo ? (
                <div className="relative inline-block">
                    {vista && (
                        <img
                            src={vista}
                            alt={etiqueta}
                            className="h-44 w-auto max-w-full rounded-lg border border-slate-200 object-cover"
                        />
                    )}
                    <button
                        type="button"
                        aria-label={`Quitar ${etiqueta.toLowerCase()}`}
                        onClick={() => onCambiar(null)}
                        className={`absolute right-1 top-1 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900/60 text-white hover:bg-slate-900/80 ${FOCO}`}
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            ) : (
                <label
                    htmlFor={id}
                    className={`flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 text-sm font-medium text-slate-700 hover:border-primary-500 ${mensaje ? 'border-danger-600' : 'border-slate-300'}`}
                >
                    {procesando ? (
                        <>
                            <Loader2 className="h-5 w-5 animate-spin text-slate-500" /> Procesando
                        </>
                    ) : (
                        <>
                            <Camera className="h-5 w-5 text-slate-500" /> Elegir foto
                        </>
                    )}
                </label>
            )}
            <input
                id={id}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                capture={camara ?? undefined}
                className="sr-only"
                aria-label={etiqueta}
                aria-invalid={mensaje ? 'true' : undefined}
                aria-describedby={mensaje ? `${id}-error` : undefined}
                disabled={procesando}
                onChange={(e) => {
                    elegir(e.target.files?.[0] ?? null);
                    e.target.value = '';
                }}
            />
            {mensaje && (
                <span
                    id={`${id}-error`}
                    role="alert"
                    className="mt-1.5 block text-sm text-danger-600"
                >
                    {mensaje}
                </span>
            )}
        </div>
    );
}

CampoImagen.propTypes = {
    etiqueta: PropTypes.string.isRequired,
    archivo: PropTypes.object,
    onCambiar: PropTypes.func.isRequired,
    error: PropTypes.string,
    // `null` deja elegir de la galería además de la cámara.
    camara: PropTypes.oneOf(['environment', 'user', null]),
    minimo: PropTypes.number,
    maximoMB: PropTypes.number,
};
