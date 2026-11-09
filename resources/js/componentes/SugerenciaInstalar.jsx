import { useState } from 'react';
import { Download, Share, SquarePlus, X } from 'lucide-react';
import Boton from './Boton';
import Dialogo from './Dialogo';
import { useInstalacion as usarInstalacion } from '../utiles/instalacion';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
// Quien la cierra no la vuelve a ver en este navegador por un mes.
const CLAVE = 'instalar-descartado';
const DIAS = 30;

function descartadaHace() {
    try {
        const cuando = Number(window.localStorage.getItem(CLAVE));
        return cuando ? (Date.now() - cuando) / 86_400_000 : Infinity;
    } catch {
        return Infinity;
    }
}

// Bajo el inicio de sesión: invita a instalar la aplicación si todavía no lo
// está. Con el aviso del navegador (Chrome, Edge) instala ahí mismo; en
// iPhone muestra los dos pasos de Safari.
export default function SugerenciaInstalar() {
    const { forma, instalar } = usarInstalacion();
    const [cerrada, setCerrada] = useState(() => descartadaHace() < DIAS);
    const [pasos, setPasos] = useState(false);
    if (!forma || cerrada) return null;

    const cerrar = () => {
        try {
            window.localStorage.setItem(CLAVE, String(Date.now()));
        } catch {
            // Sin almacenamiento, se oculta solo por ahora.
        }
        setCerrada(true);
    };

    return (
        <>
            <div className="mt-3 flex items-center gap-3 rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 shadow-sm">
                <img
                    src="/iconos/icono-192.png"
                    alt=""
                    className="h-10 w-10 shrink-0 rounded-[0.6rem]"
                />
                <span className="min-w-0 flex-1 text-sm font-semibold leading-tight text-umss-marino">
                    Instalar la aplicación
                </span>
                <Boton
                    tamano="chico"
                    onClick={() => (forma === 'aviso' ? instalar() : setPasos(true))}
                >
                    <Download className="h-4 w-4" aria-hidden="true" /> Instalar
                </Boton>
                <button
                    type="button"
                    onClick={cerrar}
                    aria-label="No instalar"
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 ${FOCO}`}
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
            {pasos && (
                <Dialogo
                    titulo="Instalar en iPhone"
                    onCerrar={() => setPasos(false)}
                    ancho="max-w-xs"
                >
                    <ol className="space-y-3 text-sm text-slate-800">
                        <li className="flex items-center gap-3">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                                <Share className="h-5 w-5" aria-hidden="true" />
                            </span>
                            <span>
                                1. <strong>Compartir</strong>
                            </span>
                        </li>
                        <li className="flex items-center gap-3">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                                <SquarePlus className="h-5 w-5" aria-hidden="true" />
                            </span>
                            <span>
                                2. <strong>Agregar a inicio</strong>
                            </span>
                        </li>
                    </ol>
                </Dialogo>
            )}
        </>
    );
}
