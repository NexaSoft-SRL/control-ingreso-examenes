import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';

export default function LectorQr({ alDetectar, alCerrar }) {
    const video = useRef(null);
    const detectar = useRef(alDetectar);
    const [error, setError] = useState('');
    const [iniciando, setIniciando] = useState(true);

    useEffect(() => {
        detectar.current = alDetectar;
    }, [alDetectar]);

    useEffect(() => {
        let cerrado = false;
        let flujo = null;
        let animacion = null;
        let examinando = false;

        const detener = () => {
            cerrado = true;

            if (animacion !== null) {
                cancelAnimationFrame(animacion);
            }

            flujo?.getTracks().forEach((pista) => pista.stop());

            if (video.current) {
                video.current.pause();
                video.current.srcObject = null;
            }
        };

        const iniciar = async () => {
            if (
                !navigator.mediaDevices?.getUserMedia ||
                typeof window.BarcodeDetector !== 'function'
            ) {
                setError(
                    'Este navegador no permite la lectura QR nativa. Utiliza la identificación manual.'
                );
                setIniciando(false);
                return;
            }

            try {
                const detector = new window.BarcodeDetector({
                    formats: ['qr_code'],
                });

                flujo = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: { facingMode: { ideal: 'environment' } },
                });

                if (cerrado) {
                    flujo.getTracks().forEach((pista) => pista.stop());
                    return;
                }

                const elemento = video.current;

                if (!elemento) {
                    detener();
                    return;
                }

                elemento.srcObject = flujo;
                await elemento.play();

                if (cerrado) {
                    detener();
                    return;
                }

                setIniciando(false);

                const explorar = async () => {
                    if (cerrado || examinando) return;

                    examinando = true;

                    try {
                        const codigos = await detector.detect(elemento);
                        const valor = codigos.find(
                            (codigo) =>
                                typeof codigo.rawValue === 'string' && codigo.rawValue.trim() !== ''
                        )?.rawValue;

                        if (!cerrado && valor) {
                            detener();
                            detectar.current(valor);
                            return;
                        }
                    } catch {
                        // Una imagen temporalmente ilegible no detiene el lector.
                    } finally {
                        examinando = false;
                    }

                    if (!cerrado) {
                        animacion = requestAnimationFrame(explorar);
                    }
                };

                animacion = requestAnimationFrame(explorar);
            } catch {
                if (!cerrado) {
                    setError(
                        'No se pudo iniciar la cámara. Comprueba los permisos y vuelve a intentarlo.'
                    );
                    setIniciando(false);
                }

                detener();
            }
        };

        iniciar();

        return detener;
    }, []);

    return (
        <section
            aria-label="Lector QR"
            className="space-y-3 rounded-xl border border-slate-300 bg-slate-50 p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <h3 className="font-semibold text-slate-900">Escanear código QR</h3>

                <button
                    type="button"
                    onClick={alCerrar}
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    Cerrar cámara
                </button>
            </div>

            <video
                ref={video}
                aria-label="Vista de cámara"
                autoPlay
                muted
                playsInline
                className="aspect-video w-full rounded-lg bg-slate-900 object-cover"
            />

            {iniciando && !error && (
                <p role="status" className="text-sm text-slate-600">
                    Iniciando cámara…
                </p>
            )}

            {error && (
                <p role="alert" className="text-sm text-red-700">
                    {error}
                </p>
            )}

            <p className="text-xs text-slate-600">
                Coloca el QR dentro de la imagen. La lectura no registra automáticamente el ingreso.
            </p>
        </section>
    );
}

LectorQr.propTypes = {
    alDetectar: PropTypes.func.isRequired,
    alCerrar: PropTypes.func.isRequired,
};
