import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';

// La cámara exige conexión segura (HTTPS, o localhost).
function conexionSegura() {
    return typeof window === 'undefined' || window.isSecureContext !== false;
}

export default function LectorQr({ alDetectar, alCerrar, alFallar }) {
    const video = useRef(null);
    const detectar = useRef(alDetectar);
    const fallar = useRef(alFallar);
    const [error, setError] = useState('');
    const [iniciando, setIniciando] = useState(true);

    useEffect(() => {
        detectar.current = alDetectar;
        fallar.current = alFallar;
    }, [alDetectar, alFallar]);

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

        const sinCamara = (texto) => {
            setError(texto);
            setIniciando(false);
            fallar.current?.(texto);
        };

        const iniciar = async () => {
            if (!conexionSegura()) {
                sinCamara('Cámara no disponible: requiere conexión segura (HTTPS)');
                return;
            }

            if (
                !navigator.mediaDevices?.getUserMedia ||
                typeof window.BarcodeDetector !== 'function'
            ) {
                sinCamara('Cámara no disponible en este navegador');
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
                    sinCamara('Cámara no disponible: sin permiso de acceso');
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
                <h3 className="font-semibold text-slate-900">Código QR del estudiante</h3>

                <button
                    type="button"
                    onClick={alCerrar}
                    className="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Cerrar cámara
                </button>
            </div>

            {!error && (
                <video
                    ref={video}
                    aria-label="Vista de cámara"
                    autoPlay
                    muted
                    playsInline
                    className="aspect-video w-full rounded-lg bg-slate-900 object-cover"
                />
            )}

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
        </section>
    );
}

LectorQr.propTypes = {
    alDetectar: PropTypes.func.isRequired,
    alCerrar: PropTypes.func.isRequired,
    alFallar: PropTypes.func,
};
