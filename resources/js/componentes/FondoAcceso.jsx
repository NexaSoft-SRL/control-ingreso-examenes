import PropTypes from 'prop-types';
import Escudo from './Escudo';

// La tarjeta blanca de las pantallas de acceso.
export const TARJETA_ACCESO =
    'rounded-2xl border border-slate-200/70 bg-white p-6 shadow-xl shadow-primary-900/10 sm:p-8';

// Arriba de la tarjeta: el escudo, el nombre de la universidad, el título en
// azul marino y, debajo, una etiqueta con el ícono de la pantalla.
export function CabeceraAcceso({ icono: Icono, titulo, subtitulo }) {
    return (
        <div className="mb-6 flex flex-col items-center text-center">
            <Escudo className="h-20" />
            <p className="mt-3 text-[11px] font-semibold uppercase tracking-widest text-slate-500">
                Universidad Mayor de San Simón
            </p>
            <h1 className="mt-1 text-xl font-bold text-umss-marino sm:text-2xl">{titulo}</h1>
            {subtitulo && (
                <p className="mt-2 inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700">
                    <Icono className="h-4 w-4" aria-hidden="true" />
                    {subtitulo}
                </p>
            )}
        </div>
    );
}

CabeceraAcceso.propTypes = {
    icono: PropTypes.elementType.isRequired,
    titulo: PropTypes.node.isRequired,
    subtitulo: PropTypes.node,
};

// El fondo de las pantallas de acceso (login, estudiante y código de aula):
// un celeste muy claro con franjas diagonales translúcidas y, abajo, una franja
// azul marino con una línea roja (los colores de la UMSS) que lleva el crédito
// de la grupo empresa. Las franjas son decoración, fuera del lector de
// pantalla.
export default function FondoAcceso({ children }) {
    return (
        <div className="relative isolate flex min-h-dvh flex-col overflow-hidden bg-primary-50">
            <svg
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 h-full w-full"
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
            >
                <polygon points="38,0 62,0 22,100 -2,100" className="fill-primary-200/40" />
                <polygon points="66,0 78,0 38,100 26,100" className="fill-primary-200/30" />
                <polygon points="84,0 100,0 100,18 66,100 54,100" className="fill-primary-100/70" />
                <polygon points="0,88 100,76 100,77.4 0,89.4" className="fill-umss-rojo" />
                <polygon points="0,90.2 100,78.2 100,100 0,100" className="fill-umss-marino" />
            </svg>
            <main className="flex flex-1 items-center justify-center px-4 pb-28 pt-8 sm:pb-32">
                {children}
            </main>
            <footer className="absolute inset-x-0 bottom-3 text-center text-xs leading-relaxed text-white/80">
                <p className="font-semibold text-white">Desarrollado por NexaSoft SRL</p>
                <p>Taller de Ingeniería de Software · FCyT · {new Date().getFullYear()}</p>
            </footer>
        </div>
    );
}

FondoAcceso.propTypes = { children: PropTypes.node };
