import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';

// Por qué no se encendió la cámara, según el error de getUserMedia.
const MOTIVOS = {
    NotFoundError: 'Cámara no disponible: no se encontró una cámara',
    OverconstrainedError: 'Cámara no disponible: no se encontró una cámara',
    NotReadableError: 'Cámara no disponible: la está usando otra aplicación',
};

// La cámara exige conexión segura (HTTPS, o localhost).
function conexionSegura() {
    return typeof window === 'undefined' || window.isSecureContext !== false;
}

// Lee el QR de un cuadro de la cámara. Usa el lector nativo donde existe
// (Chrome en Android); donde no (Safari en iPhone y Mac, Firefox), el de
// qr-scanner, que decodifica en un worker propio.
async function crearLector() {
    if (typeof window.BarcodeDetector === 'function') {
        const nativo = new window.BarcodeDetector({ formats: ['qr_code'] });

        return {
            leer: async (fuente) =>
                (await nativo.detect(fuente)).find(
                    (codigo) => typeof codigo.rawValue === 'string' && codigo.rawValue.trim() !== ''
                )?.rawValue,
            liberar: () => {},
        };
    }

    const { default: QrScanner } = await import('qr-scanner');
    const motor = await QrScanner.createQrEngine();

    return {
        leer: async (fuente) => {
            try {
                const resultado = await QrScanner.scanImage(fuente, {
                    qrEngine: motor,
                    returnDetailedScanResult: true,
                });
                return resultado.data?.trim() || undefined;
            } catch {
                // Sin código en este cuadro.
                return undefined;
            }
        },
        liberar: () => motor?.terminate?.(),
    };
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
        let lector = null;

        const detener = () => {
            cerrado = true;

            if (animacion !== null) {
                cancelAnimationFrame(animacion);
            }

            flujo?.getTracks().forEach((pista) => pista.stop());
            lector?.liberar();
            lector = null;

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

            if (!navigator.mediaDevices?.getUserMedia) {
                sinCamara('Cámara no disponible en este navegador');
                return;
            }

            try {
                lector = await crearLector();

                if (cerrado) {
                    lector.liberar();
                    return;
                }

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
                        const valor = await lector?.leer(elemento);

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
            } catch (falla) {
                if (!cerrado) {
                    sinCamara(
                        MOTIVOS[falla?.name] ?? 'Cámara no disponible: sin permiso de acceso'
                    );
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
