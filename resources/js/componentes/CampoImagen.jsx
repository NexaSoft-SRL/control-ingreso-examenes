import { useEffect, useId, useState } from 'react';
import PropTypes from 'prop-types';
import { Camera, ImageUp, Loader2, X } from 'lucide-react';
import { dimensionesDe, ImagenNoValida, reducirImagen } from '../utiles/imagen';
import CamaraFoto, { hayCamara } from './CamaraFoto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Una foto tomada con la cámara del teléfono o elegida de un archivo, con
// su vista previa. Se valida al elegirla (tipo, tamaño y, con `minimo`, el
// lado menor en píxeles) y se reduce antes de entregarla:
// `onCambiar(archivo | null)` recibe ya el JPEG reducido. Con `revisar`
// (archivo → { errores, avisos }) se revisa ya reducida: un error la
// rechaza y los avisos se muestran bajo la vista previa. Con `preparar`
// (archivo → archivo) se transforma ya reducida y antes de revisarla (por
// ejemplo, el recorte cuadrado de la foto del estudiante); `cuadrada` muestra
// la vista previa en cuadrado y en círculo. Con `tomarFoto`, si el navegador
// deja usar la cámara, ofrece tomarla ahí mismo además de elegir un archivo.
export default function CampoImagen({
    etiqueta,
    archivo,
    onCambiar,
    error,
    camara = 'environment',
    minimo = 0,
    revisar,
    preparar,
    cuadrada = false,
    tomarFoto = false,
}) {
    const id = useId();
    const [vista, setVista] = useState(null);
    const [propio, setPropio] = useState(null);
    const [procesando, setProcesando] = useState(false);
    const [revisando, setRevisando] = useState(false);
    const [avisos, setAvisos] = useState([]);
    const [conCamara, setConCamara] = useState(false);
    const camaraDisponible = tomarFoto && hayCamara();
    const mensaje = error ?? propio;

    async function elegir(elegido) {
        setPropio(null);
        setAvisos([]);
        if (!elegido) return;
        setProcesando(true);
        try {
            if (minimo > 0) {
                const medidas = await dimensionesDe(elegido);
                if (medidas && Math.min(medidas.ancho, medidas.alto) < minimo) {
                    throw new ImagenNoValida(`Resolución insuficiente · mínimo ${minimo} px`);
                }
            }
            let reducido = await reducirImagen(elegido);
            if (preparar) reducido = await preparar(reducido);
            if (revisar) {
                setRevisando(true);
                let revision = null;
                try {
                    revision = await revisar(reducido);
                } catch {
                    // Si la revisión misma falla, la foto pasa.
                }
                if (revision?.errores?.length) throw new ImagenNoValida(revision.errores[0]);
                setAvisos(revision?.avisos ?? []);
            }
            onCambiar(reducido);
        } catch (fallo) {
            onCambiar(null);
            setPropio(
                fallo instanceof ImagenNoValida ? fallo.message : 'La imagen no se puede leer'
            );
        } finally {
            setProcesando(false);
            setRevisando(false);
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
                <div className="relative inline-flex items-end gap-3">
                    {vista && (
                        <img
                            src={vista}
                            alt={etiqueta}
                            className={`h-44 rounded-lg border border-slate-200 object-cover ${cuadrada ? 'w-44' : 'w-auto max-w-full'}`}
                        />
                    )}
                    {vista && cuadrada && (
                        <img
                            src={vista}
                            alt=""
                            className="h-14 w-14 rounded-full border-2 border-white object-cover shadow-md"
                        />
                    )}
                    <button
                        type="button"
                        aria-label={`Quitar ${etiqueta.toLowerCase()}`}
                        onClick={() => onCambiar(null)}
                        className={`absolute ${cuadrada ? 'left-[8.25rem]' : 'right-1'} top-1 flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900/60 text-white hover:bg-slate-900/80 ${FOCO}`}
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            ) : procesando ? (
                <span
                    role="status"
                    className="flex min-h-12 items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 px-4 text-sm font-medium text-slate-700"
                >
                    <Loader2 className="h-5 w-5 animate-spin text-slate-500" />{' '}
                    {revisando ? 'Revisando foto' : 'Procesando'}
                </span>
            ) : (
                <div className={camaraDisponible ? 'grid grid-cols-2 gap-2' : ''}>
                    {camaraDisponible && (
                        <button
                            type="button"
                            onClick={() => setConCamara(true)}
                            className={`flex min-h-12 items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 text-sm font-medium text-slate-700 hover:border-primary-500 ${mensaje ? 'border-danger-600' : 'border-slate-300'} ${FOCO}`}
                        >
                            <Camera className="h-5 w-5 text-slate-500" aria-hidden="true" /> Tomar
                            foto
                        </button>
                    )}
                    <label
                        htmlFor={id}
                        className={`flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 text-sm font-medium text-slate-700 hover:border-primary-500 ${mensaje ? 'border-danger-600' : 'border-slate-300'}`}
                    >
                        {camaraDisponible ? (
                            <>
                                <ImageUp className="h-5 w-5 text-slate-500" aria-hidden="true" />{' '}
                                Elegir archivo
                            </>
                        ) : (
                            <>
                                <Camera className="h-5 w-5 text-slate-500" aria-hidden="true" />{' '}
                                Elegir foto
                            </>
                        )}
                    </label>
                </div>
            )}
            {conCamara && (
                <CamaraFoto
                    trasera={camara === 'environment'}
                    onCerrar={() => setConCamara(false)}
                    onTomar={(tomada) => {
                        setConCamara(false);
                        elegir(tomada);
                    }}
                />
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
            {archivo && avisos.length > 0 && (
                <span role="status" className="mt-1.5 block text-sm text-warning-700">
                    {avisos.join(' · ')}
                </span>
            )}
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
    revisar: PropTypes.func,
    preparar: PropTypes.func,
    cuadrada: PropTypes.bool,
    tomarFoto: PropTypes.bool,
};
