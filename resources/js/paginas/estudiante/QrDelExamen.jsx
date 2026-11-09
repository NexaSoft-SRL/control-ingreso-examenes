import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { Download, Share2 } from 'lucide-react';
import { api } from '../../api/cliente';
import { nombreDeArchivo } from '../../api/descargar';
import Boton from '../../componentes/Boton';

// Si el navegador comparte archivos (el teléfono): Web Share API.
const comparteArchivos = (archivo) =>
    typeof navigator !== 'undefined' &&
    typeof navigator.share === 'function' &&
    typeof navigator.canShare === 'function' &&
    navigator.canShare({ files: [archivo] });

// El código QR de un examen habilitado (GET /estudiante/examenes/{id}/qr):
// se ve, se descarga y, en el teléfono, se comparte.
export default function QrDelExamen({ examenId, nombre }) {
    const [qr, setQr] = useState(null);
    const [fallo, setFallo] = useState(false);

    useEffect(() => {
        let vigente = true;
        let creada = null;
        api.get(`/estudiante/examenes/${examenId}/qr`, { responseType: 'blob', silencioso: true })
            .then((r) => {
                if (!vigente) return;
                creada = URL.createObjectURL(r.data);
                const final = nombreDeArchivo(r.headers?.['content-disposition']) ?? nombre;
                const archivo =
                    typeof File === 'function'
                        ? new File([r.data], final, { type: 'image/png' })
                        : null;
                setQr({ url: creada, nombre: final, archivo });
            })
            .catch(() => vigente && setFallo(true));
        return () => {
            vigente = false;
            if (creada) URL.revokeObjectURL(creada);
        };
    }, [examenId, nombre]);

    if (fallo) return <p className="mt-3 text-sm text-slate-600">QR no disponible</p>;
    if (!qr) return null;

    const compartible = qr.archivo && comparteArchivos(qr.archivo);

    function descargar() {
        const enlace = document.createElement('a');
        enlace.href = qr.url;
        enlace.download = qr.nombre;
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
    }

    async function compartir() {
        try {
            await navigator.share({ files: [qr.archivo], title: 'Código QR' });
        } catch (error) {
            // Cerrar la hoja de compartir no es un error; otro fallo descarga.
            if (error?.name !== 'AbortError') descargar();
        }
    }

    return (
        <div className="mt-3 flex flex-col items-center gap-3 border-t border-slate-100 pt-3 sm:flex-row">
            <img
                src={qr.url}
                alt="Código QR"
                width="144"
                height="144"
                className="h-36 w-36 rounded-lg border border-slate-200 bg-white"
            />
            <div className="flex w-full flex-col gap-2 sm:w-auto">
                <Boton type="button" variante="secundario" onClick={descargar}>
                    <Download className="h-4 w-4 shrink-0" aria-hidden="true" /> Descargar QR
                </Boton>
                {compartible && (
                    <Boton type="button" variante="secundario" onClick={compartir}>
                        <Share2 className="h-4 w-4 shrink-0" aria-hidden="true" /> Compartir
                    </Boton>
                )}
            </div>
        </div>
    );
}

QrDelExamen.propTypes = {
    examenId: PropTypes.number.isRequired,
    // El nombre del archivo si el servidor no lo manda.
    nombre: PropTypes.string.isRequired,
};
