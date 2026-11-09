import { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Camera, Loader2 } from 'lucide-react';
import Boton from './Boton';
import Dialogo from './Dialogo';

// Si el navegador deja usar la cámara desde la página (HTTPS o localhost).
export const hayCamara = () =>
    typeof navigator !== 'undefined' && typeof navigator.mediaDevices?.getUserMedia === 'function';

const ERRORES = {
    NotAllowedError: 'Permiso de cámara denegado',
    SecurityError: 'Permiso de cámara denegado',
    NotFoundError: 'No se encontró una cámara',
    OverconstrainedError: 'No se encontró una cámara',
    NotReadableError: 'La cámara está en uso',
};

// La cámara del equipo con una guía para el rostro: la frontal en el
// teléfono, en espejo como un espejo (la foto se guarda sin espejo). Con
// `trasera`, la de atrás, sin espejo ni guía: para fotografiar una prueba.
// «Tomar» entrega la foto como archivo JPEG: `onTomar(archivo)`.
export default function CamaraFoto({ onTomar, onCerrar, trasera = false }) {
    const video = useRef(null);
    const [listo, setListo] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        let flujo = null;
        let vigente = true;
        navigator.mediaDevices
            .getUserMedia({
                video: {
                    facingMode: trasera ? 'environment' : 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 1280 },
                },
                audio: false,
            })
            .then((recibido) => {
                if (!vigente) {
                    recibido.getTracks().forEach((pista) => pista.stop());
                    return;
                }
                flujo = recibido;
                if (video.current) {
                    video.current.srcObject = recibido;
                    video.current.play?.().catch(() => {});
                }
            })
            .catch((fallo) => {
                if (vigente) setError(ERRORES[fallo?.name] ?? 'No se pudo abrir la cámara');
            });
        return () => {
            vigente = false;
            flujo?.getTracks().forEach((pista) => pista.stop());
        };
    }, [trasera]);

    function tomar() {
        const fuente = video.current;
        if (!fuente?.videoWidth) return;
        const lienzo = document.createElement('canvas');
        lienzo.width = fuente.videoWidth;
        lienzo.height = fuente.videoHeight;
        lienzo.getContext('2d').drawImage(fuente, 0, 0);
        lienzo.toBlob(
            (blob) => {
                if (blob) onTomar(new File([blob], 'camara.jpg', { type: 'image/jpeg' }));
            },
            'image/jpeg',
            0.92
        );
    }

    return (
        <Dialogo
            titulo="Tomar foto"
            onCerrar={onCerrar}
            ancho="max-w-md"
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton onClick={tomar} disabled={!listo || Boolean(error)}>
                        <Camera className="h-4 w-4" aria-hidden="true" /> Tomar
                    </Boton>
                </>
            }
        >
            <div className="relative mx-auto aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-slate-900">
                <video
                    ref={video}
                    className={`h-full w-full object-cover ${trasera ? '' : '-scale-x-100'}`}
                    playsInline
                    muted
                    autoPlay
                    aria-label="Vista de la cámara"
                    onLoadedData={() => setListo(true)}
                />
                {/* La guía: un óvalo para el rostro, con el resto oscurecido. */}
                {!trasera && (
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute left-1/2 top-[42%] h-[58%] w-[44%] -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-2 border-white/90 shadow-[0_0_0_9999px_rgba(15,23,42,0.45)]"
                    />
                )}
                {!listo && !error && (
                    <span className="absolute inset-0 flex items-center justify-center">
                        <Loader2 className="h-8 w-8 animate-spin text-white" aria-hidden="true" />
                        <span className="sr-only">Abriendo la cámara</span>
                    </span>
                )}
                {error && (
                    <span
                        role="alert"
                        className="absolute inset-0 flex items-center justify-center p-6 text-center text-sm font-medium text-white"
                    >
                        {error}
                    </span>
                )}
            </div>
        </Dialogo>
    );
}

CamaraFoto.propTypes = {
    onTomar: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
    trasera: PropTypes.bool,
};
