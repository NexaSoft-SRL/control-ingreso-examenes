import { useEffect, useMemo, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Circle, Clapperboard, Loader2, Square, Video, X } from 'lucide-react';
import Boton from './Boton';
import Dialogo from './Dialogo';
import { hayCamara } from './CamaraFoto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// El servidor acepta hasta 8 MB por archivo; grabado aquí, un minuto en
// calidad baja pesa unos 4 MB.
export const SEGUNDOS_VIDEO = 60;
export const MAXIMO_BYTES_VIDEO = 8 * 1024 * 1024;
const TIPOS = ['video/mp4', 'video/webm', 'video/quicktime'];
const ERRORES = {
    NotAllowedError: 'Permiso de cámara denegado',
    SecurityError: 'Permiso de cámara denegado',
    NotFoundError: 'No se encontró una cámara',
    OverconstrainedError: 'No se encontró una cámara',
    NotReadableError: 'La cámara está en uso',
};

const reloj = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

// El primer formato que el navegador sabe grabar (Safari, MP4; el resto, WebM).
function formatoDeGrabacion() {
    const grabadora = globalThis.MediaRecorder;
    if (!grabadora?.isTypeSupported) return '';
    return (
        ['video/mp4;codecs=avc1', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'].find(
            (tipo) => grabadora.isTypeSupported(tipo)
        ) ?? ''
    );
}

// Cuánto dura un video elegido (en segundos), o null si no se puede leer.
export function duracionDe(archivo) {
    return new Promise((resolver) => {
        const url = URL.createObjectURL(archivo);
        const video = document.createElement('video');
        let listo = false;
        const terminar = (valor) => {
            if (listo) return;
            listo = true;
            URL.revokeObjectURL(url);
            resolver(valor);
        };
        // Si el navegador no lee sus datos, no se juzga la duración.
        setTimeout(() => terminar(null), 4000);
        video.preload = 'metadata';
        video.onloadedmetadata = () =>
            terminar(Number.isFinite(video.duration) ? video.duration : null);
        video.onerror = () => terminar(null);
        video.src = url;
    });
}

// Graba con la cámara de atrás, en calidad baja, hasta `SEGUNDOS_VIDEO`.
function GrabarVideo({ onGrabado, onCerrar }) {
    const vista = useRef(null);
    const flujo = useRef(null);
    const grabadora = useRef(null);
    const [listo, setListo] = useState(false);
    const [error, setError] = useState(null);
    const [segundos, setSegundos] = useState(null);

    useEffect(() => {
        let vigente = true;
        const abrir = (audio) =>
            navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } },
                audio,
            });
        abrir(true)
            .catch(() => abrir(false))
            .then((recibido) => {
                if (!vigente) return recibido.getTracks().forEach((pista) => pista.stop());
                flujo.current = recibido;
                if (vista.current) {
                    vista.current.srcObject = recibido;
                    Promise.resolve()
                        .then(() => vista.current?.play?.())
                        .catch(() => {});
                }
                return undefined;
            })
            .catch(
                (fallo) => vigente && setError(ERRORES[fallo?.name] ?? 'No se pudo abrir la cámara')
            );
        return () => {
            vigente = false;
            if (grabadora.current?.state === 'recording') {
                grabadora.current.onstop = null;
                grabadora.current.stop();
            }
            flujo.current?.getTracks().forEach((pista) => pista.stop());
        };
    }, []);

    // El contador corre mientras graba y corta solo al llegar al tope.
    useEffect(() => {
        if (segundos === null) return undefined;
        if (segundos >= SEGUNDOS_VIDEO) {
            grabadora.current?.stop();
            return undefined;
        }
        const pulso = setTimeout(() => setSegundos((s) => (s === null ? s : s + 1)), 1000);
        return () => clearTimeout(pulso);
    }, [segundos]);

    function empezar() {
        const tipo = formatoDeGrabacion();
        if (!flujo.current || !tipo) {
            setError('Este navegador no graba video');
            return;
        }
        const partes = [];
        const nueva = new MediaRecorder(flujo.current, {
            mimeType: tipo,
            videoBitsPerSecond: 450_000,
            audioBitsPerSecond: 32_000,
        });
        nueva.ondataavailable = (evento) => evento.data.size > 0 && partes.push(evento.data);
        nueva.onstop = () => {
            const base = tipo.split(';')[0];
            const blob = new Blob(partes, { type: base });
            onGrabado(
                new File([blob], `evidencia.${base === 'video/mp4' ? 'mp4' : 'webm'}`, {
                    type: base,
                })
            );
        };
        grabadora.current = nueva;
        nueva.start(1000);
        setSegundos(0);
    }

    const grabando = segundos !== null;

    return (
        <Dialogo
            titulo="Grabar video"
            abierto
            onCerrar={onCerrar}
            ancho="max-w-md"
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    {grabando ? (
                        <Boton variante="peligro" onClick={() => grabadora.current?.stop()}>
                            <Square className="h-4 w-4" aria-hidden="true" /> Detener
                        </Boton>
                    ) : (
                        <Boton onClick={empezar} disabled={!listo || Boolean(error)}>
                            <Circle className="h-4 w-4 fill-current" aria-hidden="true" /> Grabar
                        </Boton>
                    )}
                </>
            }
        >
            <div className="relative mx-auto aspect-[4/3] w-full overflow-hidden rounded-xl bg-slate-900">
                <video
                    ref={vista}
                    className="h-full w-full object-cover"
                    playsInline
                    muted
                    autoPlay
                    aria-label="Vista de la cámara"
                    onLoadedData={() => setListo(true)}
                />
                {grabando && (
                    <span
                        className="absolute left-3 top-3 flex items-center gap-1.5 rounded-full bg-danger-600 px-2.5 py-1 text-xs font-semibold tabular-nums text-white"
                        aria-live="polite"
                    >
                        <span className="h-2 w-2 animate-pulse rounded-full bg-white" />
                        {reloj(segundos)} / {reloj(SEGUNDOS_VIDEO)}
                    </span>
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

GrabarVideo.propTypes = {
    onGrabado: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// Un video corto como prueba: se graba aquí (hasta un minuto) o se elige
// uno del dispositivo (hasta 8 MB y un minuto); se ve antes de enviarlo y se
// quita con la equis. `onCambiar(archivo | null)`.
export default function VideoEvidencia({ video, onCambiar, error, etiqueta = 'Video' }) {
    const entrada = useRef(null);
    const [grabando, setGrabando] = useState(false);
    const [propio, setPropio] = useState(null);
    const url = useMemo(() => (video ? URL.createObjectURL(video) : null), [video]);
    useEffect(() => () => url && URL.revokeObjectURL(url), [url]);
    const mensaje = propio ?? error;

    const aceptar = async (archivo, grabado = false) => {
        setPropio(null);
        if (!archivo) return;
        if (!TIPOS.includes(archivo.type)) return setPropio('Solo MP4, WebM o MOV');
        if (archivo.size > MAXIMO_BYTES_VIDEO) return setPropio('Máximo 8 MB');
        // Lo grabado aquí ya viene cortado al minuto.
        const duracion = grabado ? null : await duracionDe(archivo);
        if (duracion !== null && duracion > SEGUNDOS_VIDEO + 1) {
            return setPropio(`Máximo ${SEGUNDOS_VIDEO} segundos`);
        }
        onCambiar(archivo);
        return undefined;
    };

    return (
        <div>
            <span className="mb-1.5 block text-sm font-medium text-slate-700">{etiqueta}</span>
            {video ? (
                <div className="relative w-full max-w-xs">
                    <video
                        src={url}
                        controls
                        playsInline
                        aria-label="Video elegido"
                        className="aspect-video w-full rounded-lg border border-slate-200 bg-slate-900"
                    />
                    <button
                        type="button"
                        aria-label="Quitar el video"
                        onClick={() => onCambiar(null)}
                        className={`absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-slate-900/70 text-white hover:bg-slate-900 ${FOCO}`}
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-2">
                    {hayCamara() && (
                        <button
                            type="button"
                            onClick={() => setGrabando(true)}
                            className={`flex min-h-11 items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50 ${FOCO}`}
                        >
                            <Video className="h-4 w-4" aria-hidden="true" /> Grabar video
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => entrada.current?.click()}
                        className={`flex min-h-11 items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50 ${FOCO}`}
                    >
                        <Clapperboard className="h-4 w-4" aria-hidden="true" /> Elegir video
                    </button>
                    <input
                        ref={entrada}
                        type="file"
                        accept="video/mp4,video/webm,video/quicktime"
                        className="sr-only"
                        tabIndex={-1}
                        aria-label="Archivo de video"
                        onChange={(e) => {
                            aceptar(e.target.files?.[0] ?? null);
                            e.target.value = '';
                        }}
                    />
                </div>
            )}
            <span className="mt-1 block text-xs text-slate-500">
                Hasta {SEGUNDOS_VIDEO} s · 8 MB
            </span>
            {mensaje && (
                <span role="alert" className="mt-1.5 block text-sm text-danger-600">
                    {mensaje}
                </span>
            )}
            {grabando && (
                <GrabarVideo
                    onCerrar={() => setGrabando(false)}
                    onGrabado={(archivo) => {
                        setGrabando(false);
                        aceptar(archivo, true);
                    }}
                />
            )}
        </div>
    );
}

VideoEvidencia.propTypes = {
    video: PropTypes.object,
    onCambiar: PropTypes.func.isRequired,
    error: PropTypes.string,
    etiqueta: PropTypes.string,
};
