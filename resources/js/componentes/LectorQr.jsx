import { useEffect, useRef } from 'react';
import PropTypes from 'prop-types';

const REPETICION_MS = 3000;

// Visor de la cámara que lee códigos QR (`qr-scanner`, con import dinámico
// para no cargarla en quien no controla). Con `activo` enciende la cámara;
// al desactivarse y al desmontar la detiene y la libera. `onLectura(texto)`
// recibe cada lectura; la misma repetida se ignora durante 3 s. `onError`
// avisa si no hay cámara o no se pudo encender (sin permiso, sin HTTPS).
export default function LectorQr({ activo, onLectura, onError, className = '' }) {
    const video = useRef(null);
    const alLeer = useRef(onLectura);
    const alFallar = useRef(onError);

    useEffect(() => {
        alLeer.current = onLectura;
        alFallar.current = onError;
    }, [onLectura, onError]);

    useEffect(() => {
        if (!activo) return undefined;
        let cancelado = false;
        let lector = null;
        const ultima = { texto: null, momento: 0 };

        const fallar = (error) => {
            if (!cancelado) alFallar.current?.(error);
        };

        (async () => {
            try {
                const { default: QrScanner } = await import('qr-scanner');
                // Sin consultar antes si hay cámara: Safari no lista las cámaras
                // hasta dar el permiso. Si no hay, `start()` falla y se avisa.
                if (cancelado || !video.current) return;
                lector = new QrScanner(
                    video.current,
                    (resultado) => {
                        const texto = resultado?.data ?? '';
                        const ahora = Date.now();
                        if (!texto) return;
                        if (texto === ultima.texto && ahora - ultima.momento < REPETICION_MS)
                            return;
                        ultima.texto = texto;
                        ultima.momento = ahora;
                        alLeer.current?.(texto);
                    },
                    {
                        preferredCamera: 'environment',
                        highlightScanRegion: true,
                        maxScansPerSecond: 4,
                    }
                );
                await lector.start();
                if (cancelado) {
                    lector?.stop();
                    lector?.destroy();
                    lector = null;
                }
            } catch (error) {
                fallar(error);
            }
        })();

        return () => {
            cancelado = true;
            if (lector) {
                lector.stop();
                lector.destroy();
                lector = null;
            }
        };
    }, [activo]);

    return (
        <video
            ref={video}
            muted
            playsInline
            aria-label="Cámara"
            className={`h-full w-full object-cover ${activo ? '' : 'hidden'} ${className}`}
        />
    );
}

LectorQr.propTypes = {
    activo: PropTypes.bool.isRequired,
    onLectura: PropTypes.func.isRequired,
    onError: PropTypes.func,
    className: PropTypes.string,
};
